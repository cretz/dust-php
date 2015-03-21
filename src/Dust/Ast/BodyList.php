<?php
namespace Dust\Ast;

class BodyList extends Ast {
    public $key;
    
    public $body;
    
    public function __toString() {
        $str = '{:' . $this->key . '}';
        if ($this->body != null) $str .= $this->body;
        return $str;
    }
    
}