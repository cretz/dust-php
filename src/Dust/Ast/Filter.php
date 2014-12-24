<?php
namespace Dust\Ast;

class Filter extends Ast
{
    /**
     * @var string
     */
    public $key;

    /**
     * @return string
     */
    public function __toString() {
        return '|' . $this->key;
    }

}