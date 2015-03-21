<?php
namespace Dust\Filter;

class JsonDecode implements Filter {
    public function apply($item) { return json_decode($item); }
    
}