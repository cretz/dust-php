<?php
namespace Dust\Filter;

class EncodeUri implements Filter {
    public static $replacers;
    
    public function apply($item) {
        if (!is_string($item)) return $item;
        return strtr(rawurlencode($item), EncodeUri::$replacers);
    }
    
}
EncodeUri::$replacers = [
    '%2D' => '-',
    '%5F' => '_',
    '%2E' => '.',
    '%21' => '!',
    '%7E' => '~',
    '%2A' => '*',
    '%27' => "'",
    '%28' => '(',
    '%29' => ')',
    '%3B' => ';',
    '%2C' => ',',
    '%2F' => '/',
    '%3F' => '?',
    '%3A' => ':',
    '%40' => '@',
    '%26' => '&',
    '%3D' => '=',
    '%2B' => '+',
    '%24' => '$',
    '%23' => '#'
];