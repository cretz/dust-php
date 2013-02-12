<?php
namespace Dust\Filter;

class JavaScriptEscape implements Filter {
    public static $replacers;
    
    public function apply($item) {
        if (!is_string($item)) return $item;
        return str_replace(array_keys(JavaScriptEscape::$replacers), array_values(JavaScriptEscape::$replacers), $item);
    }
    
}
JavaScriptEscape::$replacers = [
    "\\" => "\\\\",
    "\r" => "\\r",
    "\n" => "\\n",
    "\f" => "\\f",
    "'" => "\\'",
    '"' => "\\\"",
    "\t" => "\\t"
];