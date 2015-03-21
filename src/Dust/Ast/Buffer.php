<?php
namespace Dust\Ast;

class Buffer extends Part {
    public $contents;
    
    public function __toString() {
        return $this->contents;
    }
    
}