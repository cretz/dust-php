<?php
namespace Dust\Ast;

class Context extends Ast {
    public $identifier;
    
    public function __toString() {
        return ':' . $this->identifier;
    }
    
}