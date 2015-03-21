<?php
namespace Dust\Ast;

class Filter extends Ast {
    public $key;
    
    public function __toString() {
        return '|' . $this->key;
    }
    
}