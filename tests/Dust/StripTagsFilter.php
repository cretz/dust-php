<?php
namespace Dust;

class StripTagsFilter implements Filter\Filter {
    public function apply($item) {
        if (!is_string($item)) return $item;
        return strip_tags($item);
    }
    
}