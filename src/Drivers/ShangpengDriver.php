<?php

namespace Leapfu\CloudPrinter\Drivers;

/**
 * 商鹏云打印机驱动 (完整功能版)
 * 兼容 Leapfu SDK 标准并保留商鹏所有原生 API 接口
 * 基于 2026-02-12 最新 API 协议实现
 */
class ShangpengDriver extends BaseDriver
{
    /**
     * @var string 商鹏 API 基础 URL
     */
    protected string $baseUrl = 'https://open.spyun.net';

    /**
     * @var array 配置数组
     */
    protected array $config = [
        'appid'     => '',  
        'appsecret' => '',  
    ];

    /**
     * 获取驱动名称
     * @return string
     */
    public function getDriverName(): string
    {
        return 'shangpeng';
    }

    /**
     * 格式化打印内容（标签兼容性处理）
     * 商鹏原生支持 <CB>, <BR>, <QR>, <CUT> 等飞鹅系标准标签
     * @param string $content 原始打印内容
     * @return string 处理后的打印内容
     */
    protected function formatContent(string $content): string
    {
        // 1. 二维码兼容：将通用的 <QRCODE> 或易联云的 <QR2> 统一转换为商鹏支持的 <QR>
        $content = preg_replace('/<(QRCODE|QR2)[^>]*>(.*)<\/\1>/iU', '<QR>$2</QR>', $content);

        // 2. 条形码兼容：将通用的 <BARCODE> 转换为商鹏标准的 BC128 标签
        // 混合模式 (B)
        $content = preg_replace('/<BARCODE type="B">(.*)<\/BARCODE>/iU', '<BC128_B>$1</BC128_B>', $content);
        // 纯数字模式 (C)
        $content = preg_replace('/<BARCODE type="C">(.*)<\/BARCODE>/iU', '<BC128_C>$1</BC128_C>', $content);

        // 3. 对齐标签自动修剪：防止从芯烨等驱动迁移过来的 <BR> 位置导致的冗余
        // 商鹏对标签内外位置较宽容，此处不做强制修改，保持原样即可
        
        return $content;
    }

    // --- 1. 基础打印接口 ---

    /**
     * 打印文本订单 (对应 prints)
     * @param array $params ['sn', 'content', 'times']
     */
    public function print(array $params): array
    {
        return $this->request('printer/print', $params);
    }

    // --- 2. 设备管理接口 ---

    /**
     * 添加打印机 (对应 addPrinter)
     * @param array $params ['sn', 'key', 'name']
     */
    public function printerAddlist(array $params): array
    {
        return $this->request('printer/add', $params);
    }

    /**
     * 删除打印机 (对应 deletePrinter)
     * @param array $params ['sn']
     */
    public function deletePrinter(array $params): array
    {
        return $this->request('printer/delete', $params);
    }

    /**
     * 修改打印机名称 (对应 updatePrinter)
     * @param array $params ['sn', 'name']
     */
    public function modifyPrinter(array $params): array
    {
        return $this->request('printer/update', $params);
    }

    /**
     * 【商鹏特有】修改打印机参数 (对应 updatePrinterSetting)
     * @param array $params ['sn', 'voice', 'speed']
     */
    public function updatePrinterSetting(array $params): array
    {
        return $this->request('printer/updateSetting', $params);
    }

    // --- 3. 状态与查询接口 ---

    /**
     * 查询打印机实时信息 (对应 getPrinter)
     */
    public function queryPrinterStatus(array $params): array
    {
        return $this->request('printer/getPrinter', $params);
    }

    /**
     * 查询订单打印状态 (对应 getPrintsStatus)
     * @param array $params ['orderid']
     */
    public function queryOrderStatus(array $params): array
    {
        return $this->request('printer/getPrintsStatus', $params);
    }

    /**
     * 清空待打印队列 (对应 deletePrints)
     */
    public function clearPrinterQueue(array $params): array
    {
        return $this->request('printer/deletePrints', $params);
    }

    /**
     * 【商鹏特有】查询历史打印订单数 (对应 getPrintsOrders)
     * @param array $params ['sn', 'date']
     */
    public function orderInfoByDate(array $params): array
    {
        return $this->request('printer/getPrintsOrders', $params);
    }

    // --- 4. 核心请求与签名逻辑 ---

    /**
     * 通用请求封装
     */
    public function request(string $endpoint, array $params, string $method = 'POST'): array
    {
        $data = array_merge([
            'appid'     => $this->config['appid'],
            'timestamp' => time(),
        ], $params);

        // 调用 BaseDriver 的 handleRequest，这会自动触发 formatContent
        $url = $this->baseUrl . ltrim($endpoint, '/');
        $result = $this->handleRequest($url, $data, $method);

        // 注意：签名逻辑应在 handleRequest 之前或内部处理内容格式化后重新计算
        // 此处重新提取格式化后的内容参与签名
        $data['sign'] = $this->generateSignature($data);

        // 二次请求确保带上签名
        $result = $this->handleRequest($url, $data, $method);

        // 严格匹配商鹏返回格式：errorcode=0 为成功
        if (isset($result['errorcode']) && $result['errorcode'] == 0) {
            return $this->formatResult(true, 'Success', $result['data'] ?? $result);
        }

        return $this->formatResult(false, $result['errormsg'] ?? '未知错误', $result);
    }

    /**
     * 严格按照商鹏 ASCII 排序 MD5 签名逻辑
     */
    private function generateSignature(array $params): string
    {
        // 过滤空值及 sign 字段
        $filtered = array_filter($params, function ($v, $k) {
            return $k !== 'sign' && $v !== '' && $v !== null;
        }, ARRAY_FILTER_USE_BOTH);

        ksort($filtered);

        $stringA = '';
        foreach ($filtered as $key => $value) {
            $stringA .= $key . '=' . $value . '&';
        }
        $stringA = rtrim($stringA, '&');

        // 拼接密钥
        $stringSignTemp = $stringA . '&appsecret=' . $this->config['appsecret'];

        return strtoupper(md5($stringSignTemp));
    }
}
