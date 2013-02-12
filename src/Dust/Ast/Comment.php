<?php
namespace Dust\Ast;

class Comment extends Part {
    public $contents;
    
    public function __toString() {
        return '{!' . $this->contents . '!}';
    }
    
}