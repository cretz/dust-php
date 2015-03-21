<?php
namespace Dust\Ast;

class Identifier extends Ast {
    public $preDot = false;
    
    public $key;
    
    public $number;
    
    public $arrayAccess;
    
    public $next;
    
    public function __toString() {
        $str = '';
        if ($this->preDot) $str .= '.';
        if ($this->key != null) $str .= $this->key;
        elseif ($this->number != null) $str .= $this->number;
        if ($this->arrayAccess != null) $str .= '[' . $this->arrayAccess . ']';
        if ($this->next != null) $str .= $this->next;
        return $str;
    }
    
}