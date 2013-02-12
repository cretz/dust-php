<?php
namespace Dust\Helper;

class Lt extends Comparison {
    public function isValid($key, $value) { return $key < $value; }
    
}