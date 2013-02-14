<?php
namespace Dust\Ast;

class Body extends Ast {
    public $filePath;
    
    public $parts;
    
    public function __toString() {
        $str = '';
        if (!empty($this->parts)) {
            foreach ($this->parts as $value) { $str .= $value; }
        }
        return $str;
    }
    
}