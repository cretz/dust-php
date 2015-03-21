<?php
namespace Dust\Ast;

class Inline extends Ast {
    public $parts;
    
    public function __toString() {
        $str = '"';
        if (!empty($this->parts)) {
            foreach ($this->parts as $value) { $str .= $value; }
        }
        return $str . '"';
    }
    
}