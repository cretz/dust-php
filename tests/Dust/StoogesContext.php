<?php
namespace Dust;

class StoogesContext {
    public $title = 'Famous People';
    
    public function names() {
        return [new StoogeName('Larry'), new StoogeName('Curly'), new StoogeName('Moe')];
    }

    public function __isset($name)
    {
        if($name === 'genres')
        {
            return true;
        }
    }

    public function __get($key)
    {
        if($key === 'genres')
        {
            return ['Farce', 'slapstick', 'musical comedy'];
        }
    }

}