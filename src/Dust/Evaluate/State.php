<?php
namespace Dust\Evaluate;

class State {
    public $value;
    
    public $forcedParent;
    
    public $params;
    
    public function __construct($value) {
        $this->value = $value;
        $this->params = [];
    }
    
}