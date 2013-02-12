<?php
namespace Dust\Ast;

class IdentifierParameter extends Parameter {
    public $value;
    
    public function __toString() {
        return $this->key . '=' . $this->value;
    }
    
}