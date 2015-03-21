<?php
namespace Dust\Ast;

class Partial extends Part {
    public $type;
    
    public $key;
    
    public $inline;
    
    public $context;
    
    public $parameters;
    
    public function __toString() {
        $str = '{' . $this->type;
        if ($this->key != null) $str .= $this->key;
        else $str .= $this->inline;
        if ($this->context != null) $str .= $this->context;
        if (!empty($this->parameters)) {
            foreach ($this->parameters as $value) {
                $str .= ' ' . $value;
            }
        }
        $str .= '/}';
        return $str;
    }
    
}