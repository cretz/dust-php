<?php
namespace Dust\Helper;

class Gte extends Comparison {
    public function isValid($key, $value) { return $key >= $value; }
    
}