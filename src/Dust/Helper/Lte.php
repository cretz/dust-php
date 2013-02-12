<?php
namespace Dust\Helper;

class Lte extends Comparison {
    public function isValid($key, $value) { return $key <= $value; }
    
}