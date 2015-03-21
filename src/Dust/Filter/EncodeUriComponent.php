<?php
namespace Dust\Filter;

class EncodeUriComponent implements Filter {
    public static $replacers;
    
    public function apply($item) {
        if (!is_string($item)) return $item;
        return strtr(rawurlencode($item), EncodeUriComponent::$replacers);
    }
    
}
EncodeUriComponent::$replacers = [
    '%21' => '!',
    '%2A' => '*',
    '%27' => "'",
    '%28' => '(',
    '%29' => ')'
];