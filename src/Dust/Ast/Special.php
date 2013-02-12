<?php
namespace Dust\Ast;

class Special extends InlinePart {
    public $key;
    
    public function __toString() {
        return '{~' . $this->key . '}';
    }
    
}