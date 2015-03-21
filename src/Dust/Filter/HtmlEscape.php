<?php
namespace Dust\Filter;

class HtmlEscape implements Filter {
    public static $replacers;
    
    public function apply($item) {
        if (!is_string($item)) return $item;
        return str_replace(array_keys(HtmlEscape::$replacers), array_values(HtmlEscape::$replacers), $item);
    }
    
}
HtmlEscape::$replacers = [
    '&' => '&amp;',
    '<' => '&lt;',
    '>' => '&gt;',
    '"' => '&quot;',
    "'" => '&#39;'
];