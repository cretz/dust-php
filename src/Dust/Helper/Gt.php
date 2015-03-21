<?php
namespace Dust\Helper;

class Gt extends Comparison {
    public function isValid($key, $value) { return $key > $value; }
    
}