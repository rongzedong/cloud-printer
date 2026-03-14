<?php
// by rongzedong 2026.

namespace Leapfu\CloudPrinter\Support;

/**
 * 小票内容构建器 (Receipt Content Builder)
 * 
 * 本类负责处理热敏打印机的核心排版逻辑：
 * 1. 处理中英文混排的字节对齐 (基于 GBK 编码计算宽度)
 * 2. 自动处理商品名称超长换行，并保持右侧单价、数量、金额列对齐
 * 3. 动态适配 58mm (32字节) 和 80mm (48字节) 打印规格
 */
class ReceiptBuilder
{
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
    public static function formatItems(array $items, int $A = 14, int $B = 6, int $C = 3, int $D = 6): string
    {
        $content = '';

        foreach ($items as $item) {
            $name   = (string)$item['title'];
            $price  = (string)$item['price'];
            $num    = (string)$item['num'];
            // 计算小计并格式化为1位小数
            $total  = number_format($item['price'] * $item['num'], 1, '.', '');

            // 1. 右侧三列预处理：补齐空格确保垂直对齐
            // STR_PAD_RIGHT 保证靠左对齐并向右补齐空格
            $priceStr  = str_pad($price, $B, ' ', STR_PAD_RIGHT);
            $numStr    = str_pad($num, $C, ' ', STR_PAD_RIGHT);
            $totalStr  = str_pad($total, $D, ' ', STR_PAD_RIGHT);

            // 2. 名称列处理：根据 $A 宽度进行自动切分换行
            $nameLines = self::splitTextByGbkWidth($name, $A);

            foreach ($nameLines as $index => $line) {
                if ($index === 0) {
                    // 第一行：打印 [名称首行] [单价] [数量] [金额]
                    // 注意列与列之间手动加一个空格以防粘连
                    $content .= $line . ' ' . $priceStr . ' ' . $numStr . ' ' . $totalStr . "<BR>";
                } else {
                    // 换行行：仅打印名称后续部分
                    $content .= $line . "<BR>";
                }
            }
        }

        return $content;
    }

    /**
     * 生成动态分割线
     * 
     * 根据列宽参数自动计算总宽度并生成横线
     * 
     * @param int $A, $B, $C, $D 列宽参数
     * @param string $char 分割线字符，默认为 "-"
     * @return string 包含换行符的分割线字符串
     */
    public static function separator(int $A = 14, int $B = 6, int $C = 3, int $D = 6, string $char = '-'): string
    {
        // 总宽度 = 各列宽度 + 列间空格(3个)
        $totalWidth = $A + $B + $C + $D + 3;
        return str_repeat($char, $totalWidth) . "<BR>";
    }

    /**
     * 核心对齐算法：按照 GBK 字节宽度切分字符串
     * 
     * 解决中英文混排对齐问题的关键：
     * 中文字符在热敏打印机占 2 字节宽度，英文字符占 1 字节宽度。
     * 
     * @param string $text 原始字符串
     * @param int $maxWidth 目标字节宽度
     * @return array 切分后的行数组
     */
    private static function splitTextByGbkWidth(string $text, int $maxWidth): array
    {
        $lines = [];
        $currentLine = '';
        $textLength = mb_strlen($text, 'utf-8');

        for ($i = 0; $i < $textLength; $i++) {
            $char = mb_substr($text, $i, 1, 'utf-8');
            
            // 计算当前字符在打印机上的显示宽度 (GBK 字节数)
            $charWidth = strlen(iconv("UTF-8", "GBK//IGNORE", $char));
            $currentLineWidth = strlen(iconv("UTF-8", "GBK//IGNORE", $currentLine));

            if ($currentLineWidth + $charWidth <= $maxWidth) {
                $currentLine .= $char;
            } else {
                // 当前行已满，补齐空格并存入数组
                $lines[] = self::padGbkSpace($currentLine, $maxWidth);
                $currentLine = $char;
            }
        }

        // 处理最后剩余的字符
        if ($currentLine !== '') {
            $lines[] = self::padGbkSpace($currentLine, $maxWidth);
        }

        return $lines;
    }

    /**
     * GBK 字节宽度补齐
     * 
     * @param string $str 原始字符串
     * @param int $targetWidth 目标补齐宽度
     * @return string 补齐空格后的字符串
     */
    private static function padGbkSpace(string $str, int $targetWidth): string
    {
        $currentWidth = strlen(iconv("UTF-8", "GBK//IGNORE", $str));
        $diff = $targetWidth - $currentWidth;
        
        return ($diff > 0) ? $str . str_repeat(' ', $diff) : $str;
    }
}
