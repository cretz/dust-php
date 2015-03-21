<?php
namespace Dust\Ast;

class Reference extends InlinePart {
    public $identifier;
    
    public $filters;
    
    public function __toString() {
        $str = '{' . $this->identifier;
        if (!empty($this->filters)) {
            foreach ($this->filters as $value) { $str .= $value; }
        }
        return $str . '}';
    }
    
}