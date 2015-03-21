<?php
namespace Dust\Helper;

class Eq extends Comparison {
    public function isValid($key, $value) { return $key == $value; }
    
}