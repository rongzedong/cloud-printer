# Cloud Printer

**Cloud Printer** 是一款高扩展性、易集成的 PHP 云小票打印 SDK，统一封装了飞鹅云、芯烨云、易联云、快递 100、映美云、佳博云、中午云、优声云等主流云打印服务，支持多驱动切换、主流框架集成、灵活配置和完善的异常处理。

---

## 主要特性

- 🚀 统一 API，屏蔽各家云打印厂商差异
- 🖨️ 支持多种主流云打印机
- 🧩 驱动可扩展，支持自定义云打印服务
- 📝 内置日志、缓存，支持自定义实现
- ⚡ 完善的异常处理体系
- 🛠️ 兼容 Laravel、ThinkPHP 等主流框架
- 📦 Composer 一键安装

---

## 快速上手

### 1. 安装依赖

```bash
composer require leapfu/cloud-printer
```

### 2. 初始化 SDK

```php
use Leapfu\CloudPrinter\CloudPrinter;

$config = [
    // 默认打印机类型
    'default'    => 'feie',
    // 缓存配置
    'cache_path' => __DIR__ . '/../cache',
    // 日志目录
    'log_path'   => __DIR__ . '/../logs',
    // HTTP客户端配置
    'http'       => [
        'timeout'         => 30,      // 请求超时时间(秒)
        'connect_timeout' => 10, // 连接超时时间(秒)
        'verify'          => true,      // 是否验证SSL证书
    ],

    // 打印机配置
    'drivers'    => [
        // 飞鹅云打印
        'feie'      => [
            'user' => '',  // 飞鹅云后台注册的账号
            'ukey' => '',  // 飞鹅云后台生成的UKEY
        ],

        // 易联云打印
        'yilian'    => [
            'client_id'     => '',  // 易联云应用ID
            'client_secret' => '', // 易联云应用密钥
        ],

        // 芯烨云打印
        'xpyun'     => [
            'user'     => '',     // 芯烨云账号
            'user_key' => '', // 芯烨云用户密钥
        ],

        // 快递100云打印
        'kuaidi100' => [
            'key'    => '',    // 快递100应用key
            'secret' => '', // 快递100应用密钥
        ],

        // 优声云打印
        'usheng'    => [
            'app_id'     => '',    // 优声云应用key
            'app_secret' => '', // 优声云应用密钥
        ],

        // 中午云打印
        'zhongwu'   => [
            'app_id'     => '',    // 中午云应用key
            'app_secret' => '', // 中午云应用密钥
        ],


        // 佳博云打印
        'poscom'    => [
            'api_key'     => '',         // 接口密钥
            'member_code' => '',      // 商户编码
        ],

        // 映美云打印
        'jolimark'  => [
            'app_id'     => '',     // 映美云应用ID
            'app_secret' => '', // 映美云应用密钥
        ],

        // 自定义驱动
        'custom'    => [
            'class' => '', // 驱动类的class, 须继承 Leapfu\CloudPrinter\Drivers\BaseDriver类,
            'key'    => '',    // 自定义应用key
            'secret' => '', // 自定义应用密钥
        ],
    ],
];
$printer = new CloudPrinter($config);
```

### 3. 打印文本（所有驱动统一 print 方法，参数为数组）

```php
// 使用默认打印机
$result = $printer->driver()->print([
    'content' => '测试内容',
    'sn' => '打印机SN',
    'copies' => 1
]);

// 指定打印机类型
$result = $printer->driver('feie')->print([
    'content' => '内容',
    'sn' => 'SN',
    'copies' => 1
]);

// 直接调用打印机方法（动态代理）
$result = $printer->print([
    'content' => '内容',
    'sn' => 'SN',
    'copies' => 1
]);

if ($result['success']) {
    echo '打印成功';
} else {
    echo '打印失败：' . $result['message'];
}
```

> 所有驱动都实现 print 方法，参数为数组（如 ['content' => '内容', 'sn' => 'SN', 'copies' => 1]），返回统一格式。其他高级功能请查阅对应驱动扩展方法文档。

### 2026-03 增加了基础标签的相互兼容

>具体在每个driver里实现，下面是简易的例子
>增加了 Support/ReceiptBuilder 用于整理要打印的条目，不仅能处理商品行（formatItems），以后还可以扩展 addHeader（头信息）、addFooter（尾信息）等方法。
```
    /**
     * 构建商品列表（多列对齐排版内容）
     * 
     * @param array $items 商品数据数组。格式：[['title' => '商品名', 'price' => 10.5, 'num' => 2], ...]
     * @param int $A 名称列的字节宽度 (58mm建议14, 80mm建议24)
     * @param int $B 单价列的字节宽度 (建议6)
     * @param int $C 数量列的字节宽度 (建议3)
     * @param int $D 金额列的字节宽度 (建议6)
     * @return string 返回排版后的字符串内容（带 <BR> 换行符）
     */
```
>可以在不同打印机上适配打印机宽度，这个可以后续与SN相关联，写入到 打印机信息表内。

```php
use Leapfu\CloudPrinter\Support\ReceiptBuilder;

// 1. Identify hardware specs based on SN
$sn = '111111111';
$is80mm = true; // Logic to determine if it's an 80mm printer

// 2. Define parameters dynamically
// 58mm: 14, 6, 3, 6 (Total: 32 bytes)
// 80mm: 24, 8, 6, 10 (Total: 51 bytes - adjust as needed for your paper)
$A = $is80mm ? 24 : 14;
$B = $is80mm ? 8  : 6;
$C = $is80mm ? 6  : 3;
$D = $is80mm ? 10 : 6;

// 3. Assemble content
$content = "<CB>云打印测试</CB><BR>";
$content .= ReceiptBuilder::separator($A, $B, $C, $D);
$content .= "名称" . str_repeat(' ', $A - 4) . " 单价  数量 金额<BR>"; // Dynamic header padding
$content .= ReceiptBuilder::formatItems($items, $A, $B, $C, $D);
$content .= ReceiptBuilder::separator($A, $B, $C, $D);
$content .= "<QRCODE>https://www.spyun.net</QRCODE>";
$content .= "<CUT>";

// 4. Send to SDK (The driver's formatContent handles the tag translation)
$sdk->driver()->print(['sn' => $sn, 'content' => $content]);

```

---

## 框架集成用法

### Laravel 集成

1. **自动注册**（支持 Laravel Package Discovery，无需手动配置）
2. **发布配置文件（可选）**

   ```bash
   php artisan vendor:publish --provider="Leapfu\\CloudPrinter\\Laravel\\CloudPrinterServiceProvider" --tag=config
   ```

3. **门面调用**

   ```php
   use CloudPrinter;
   CloudPrinter::driver()->print([
       'content' => '内容',
       'sn' => 'SN',
       'copies' => 1
   ]);
   ```

4. **容器调用**

   ```php
   $printer = app(Leapfu\CloudPrinter\CloudPrinter::class);
   $printer->driver()->print([
       'content' => '内容',
       'sn' => 'SN',
       'copies' => 1
   ]);
   ```

### ThinkPHP 集成

1. 在 `config/cloudprint.php` 配置参数。
2. 使用：

   ```php
   app('cloud_printer')->driver()->print([
       'content' => '内容',
       'sn' => 'SN',
       'copies' => 1
   ]);
   ```

> 如需服务注册模板，可参考 `src/ThinkPHP/provider.php`。

---

## 安全性建议

- 敏感信息建议通过 .env 或环境变量配置，不要硬编码在代码仓库。
- 日志中避免输出账号、密钥等敏感数据。

---

## 贡献与支持

- 欢迎提交 PR 或 Issue 参与共建！
- 如需更多示例或遇到问题，欢迎提交 Issue。

---

## 获取帮助与联系方式

- 📧 <leapfu@hotmail.com>
- 🐧 QQ 群：824070084（备注"云打印 SDK"）
- 🌐 官网：[https://www.leapfu.com](https://www.leapfu.com)
- 📝 Issue 反馈：[GitHub Issues](https://github.com/leapfu/cloud-printer/issues)

如有商务合作、定制开发、技术支持等需求，欢迎通过以上方式联系我们。

---
