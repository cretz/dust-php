<?php
namespace Dust\Ast;

class InlineParameter extends Parameter {
    public $value;
    
    public function __toString() {
        return $this->key . '=' . $this->value;
    }
    
}