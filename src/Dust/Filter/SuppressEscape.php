<?php
namespace Dust\Filter;

class SuppressEscape implements Filter {
    public function apply($item) { return $item; }
    
}