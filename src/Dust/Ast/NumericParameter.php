<?php
namespace Dust\Ast;

class NumericParameter extends Parameter {
    public $value;
    
    public function __toString() {
        return $this->key . '=' . $this->value;
    }
    
}