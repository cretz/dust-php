<?php
namespace Dust\Evaluate;

use Dust\Ast;
class Bodies implements \ArrayAccess {
    private $section;
    
    public $block;
    
    public function __construct(Ast\Section $section) {
        $this->section = $section;
        $this->block = $section->body;
    }
    
    public function offsetExists($offset) {
        return $this[$offset] != null;
    }
    
    public function offsetGet($offset) {
        for ($i = 0; $i < count($this->section->bodies); $i++) {
            if ($this->section->bodies[$i]->key == $offset) {
                return $this->section->bodies[$i]->body;
            }
        }
        return null;
    }
    
    public function offsetSet($offset, $value) {
        throw new EvaluateException($this->section, 'Unsupported set on bodies');
    }
    
    public function offsetUnset($offset) {
        throw new EvaluateException($this->section, 'Unsupported unset on bodies');
    }
    
}