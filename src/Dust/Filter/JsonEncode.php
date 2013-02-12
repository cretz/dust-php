<?php
namespace Dust\Filter;

class JsonEncode implements Filter {
    public function apply($item) { return json_encode($item); }
    
}