<?php
// +----------------------------------------------------------------------
// | 蓝斧LEAPFU [ 探索不止，步履不停 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2024 https://www.leapfu.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed Cloud Printer SDK is open source under the MIT license
// +----------------------------------------------------------------------
// | Author: Leapfu  <leapfu@hotmail.com>
// +----------------------------------------------------------------------

namespace Leapfu\CloudPrinter\Drivers;

/**
 * 芯烨云打印机驱动
 *
 * 芯烨云打印接口文档：https://www.xpyun.net/open/index.html
 */
class XpyunDriver extends BaseDriver
{
    /**
     * @var string API基础URL
     */
    protected string $baseUrl = 'https://open.xpyun.net/api/openapi/xprinter/';

    /**
     * @var array 配置数组
     */
    protected array $config = [
        'user'     => '',     // 芯烨云账号
        'user_key' => '', // 芯烨云用户密钥
    ];

    /**
     * 获取打印机名称
     *
     * @return string
     */
    public function getDriverName(): string
    {
        return 'xpyun';
    }

    /**
     * 格式化打印内容（标签兼容性处理）
     * 芯烨云标签规范要求对齐标签如 <C></C> 内部必须包含 <BR> 才能实现换行对齐
     * @param string $content 原始打印内容
     * @return string 处理后的打印内容
     */
    protected function formatContent(string $content): string
    {
        // 1. 二维码兼容：将 <QR> 或简易 <QRCODE> 统一转换为芯烨带参数的标准格式
        // 芯烨建议：s=6(大小) e=L(纠错) l=center(居中)
        $content = preg_replace('/<(QR|QRCODE|QR2)[^>]*>(.*)<\/\1>/iU', '<QRCODE s=6 e=L l=center>$2</QRCODE>', $content);

        // 2. 条形码兼容：将通用 <BARCODE> 转换为芯烨标准格式，并在前面强加 <BR> 以确保正常打印
        $content = preg_replace('/<BARCODE.*>(.*)<\/BARCODE>/iU', '<BR><BARCODE t=CODE128 w=2 h=100 p=2>$1</BARCODE>', $content);

        // 3. 对齐标签修正：芯烨要求换行符 <BR> 必须在闭合标签内，例如 <C>内容<BR></C>
        // 将飞鹅等习惯的 </C><BR> 自动修正为 <BR></C>
        $search = ['</C><BR>', '</CB><BR>', '</R><BR>', '</L><BR>', '</BOLD><BR>'];
        $replace = ['<BR></C>', '<BR></CB>', '<BR></R>', '<BR></L>', '<BR></BOLD>'];
        $content = str_replace($search, $replace, $content);
        
        // 4. 行间距标签兼容：将旧版 <RH n="x"> 转换为新版 <LH h="x">
        $content = preg_replace('/<RH n="(\d)">(.*)<\/RH>/iU', '<LH h="$1">$2</LH>', $content);

        return $content;
    }

    /**
     * 添加打印机到开发者账户（可批量） 【必接】.
     * @param array $params
     * @return array
     */
    public function addPrinters(array $params): array
    {
        return $this->request('addPrinters', $params);
    }

    /**
     * 删除批量打印机.
     * @param array $params
     * @return array
     */
    public function delPrinters(array $params): array
    {
        return $this->request('delPrinters', $params);
    }

    /**
     * 修改打印机信息.
     * @param array $params
     * @return array
     */
    public function updPrinter(array $params): array
    {
        return $this->request('updPrinter', $params);
    }

    /**
     * 获取打印机状态
     * @param array $params
     * @return array
     */
    public function queryPrinterStatus(array $params): array
    {
        return $this->request('queryPrinterStatus', $params);
    }

    /**
     * 打印订单
     * @param array $params
     * @return array
     */
    public function print(array $params): array
    {
        return $this->request('print', $params);
    }

    /**
     * 标签机打印订单
     * @param array $params
     * @return array
     */
    public function printLabel(array $params): array
    {
        return $this->request('printLabel', $params);
    }

    /**
     * 清空待打印队列.
     * @param array $params
     * @return array
     */
    public function delPrinterQueue(array $params): array
    {
        return $this->request('delPrinterQueue', $params);
    }

    /**
     * 查询订单是否打印成功
     * @param array $params
     * @return array
     */
    public function queryOrderState(array $params): array
    {
        return $this->request('queryOrderState', $params);
    }

    /**
     * 查询指定打印机某天的订单统计数.
     * @param array $params
     * @return array
     */
    public function queryOrderStatis(array $params): array
    {
        return $this->request('queryOrderStatis', $params);
    }

    /**
     * 获取打印机状态
     * @param array $params
     * @return array
     */
    public function queryPrintersStatus(array $params): array
    {
        return $this->request('queryPrintersStatus', $params);
    }

    /**
     * 设置打印机语音类型.
     * @param array $params
     * @return array
     */
    public function setVoiceType(array $params): array
    {
        return $this->request('setVoiceType', $params);
    }

    /**
     * 金额播报.
     * @param array $params
     * @return array
     */
    public function playVoice(array $params): array
    {
        return $this->request('playVoice', $params);
    }

    /**
     * 发送请求
     * @param string $action 接口名称
     * @param array $params 参数
     * @param string $method 请求方式
     * @return array
     */
    public function request(string $action, array $params, string $method = 'POST'): array
    {
        // 获取当前时间戳
        $timestamp = time();
        // 生成请求数据
        $data = array_merge([
            'user'      => $this->config['user'],
            'timestamp' => $timestamp,
            'sign'      => $this->generateSign($timestamp),
        ], $params);
        
        // 构建请求URL
        $url = $this->baseUrl . $action;
        // 发送请求
        $result = $this->handleRequest($url, $data, $method);
        // 验证返回结果
        if ($result && isset($result['code']) && $result['code'] == 0) {
            return $this->formatResult(true, 'Success', $result['data'] ?? []);
        }
        // 如果响应失败，返回错误信息
        return $this->formatResult(false, $result['msg'] ?? 'Unknown error', $result['data'] ?? []);
    }

    /**
     * 生成签名
     * @param int $timestamp 时间戳
     * @return string
     */
    protected function generateSign(int $timestamp): string
    {
        return sha1($this->config['user'] . $this->config['user_key'] . $timestamp);
    }
}
