<?php
namespace Dust;

class StoogesContext {
    public $title = 'Famous People';
    
    public function names() {
        return [new StoogeName('Larry'), new StoogeName('Curly'), new StoogeName('Moe')];
    }
    
}