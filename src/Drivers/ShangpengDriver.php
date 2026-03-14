<?php

namespace Leapfu\Printer\Drivers;

use Leapfu\Printer\Contracts\PrinterInterface;

/**
 * 商鹏云打印驱动
 * 基于 2026-02-12 最新 API 协议实现
 */
class Shangpeng extends Driver implements PrinterInterface
{
    /**
     * 商鹏 API 根地址
     */
    protected $baseUrl = 'https://open.spyun.net';

    /**
     * 打印订单
     * 
     * @param string $content 打印内容
     * @param int $times 打印次数
     * @return array
     */
    public function print(string $content, int $times = 1)
    {
        return $this->request('printer/print', [
            'sn'      => $this->config['sn'],
            'content' => $content,
            'times'   => (int)$times,
        ]);
    }

    /**
     * 添加打印机
     * 
     * @param array $items ['sn' => '编号', 'key' => '密钥', 'name' => '名称']
     * @return array
     */
    public function addPrinter($items)
    {
        return $this->request('printer/add', [
            'sn'   => $items['sn'],
            'key'  => $items['key'],
            'name' => $items['name'] ?? 'Default',
        ]);
    }

    /**
     * 删除打印机 (扩展方法)
     */
    public function deletePrinter(string $sn)
    {
        return $this->request('printer/delete', ['sn' => $sn]);
    }

    /**
     * 统一请求发送逻辑（含签名生成）
     */
    protected function request(string $endpoint, array $params)
    {
        $params['appid']     = $this->config['appid'];
        $params['timestamp'] = time();
        $params['sign']      = $this->generateSign($params);

        // 使用基类 Driver 的 post 方法发送请求
        $url = $this->baseUrl . ltrim($endpoint, '/');
        return $this->post($url, $params);
    }

    /**
     * 生成签名：ASCII排序 -> 拼接参数 -> 拼接AppSecret -> MD5大写
     */
    protected function generateSign(array $params): string
    {
        // 1. 过滤空值
        $filtered = array_filter($params, function ($v) {
            return $v !== '' && $v !== null;
        });

        // 2. 按 ASCII 码从小到大排序
        ksort($filtered);

        // 3. 拼接 key=value&...
        $stringA = '';
        foreach ($filtered as $key => $value) {
            $stringA .= $key . '=' . $value . '&';
        }
        $stringA = rtrim($stringA, '&');

        // 4. 拼接 AppSecret 并执行 MD5
        $stringSignTemp = $stringA . '&appsecret=' . $this->config['appsecret'];

        return strtoupper(md5($stringSignTemp));
    }
}
