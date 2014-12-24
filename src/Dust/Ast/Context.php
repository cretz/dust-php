<?php
namespace Dust\Ast;

class Context extends Ast
{
    /**
     * @var string
     */
    public $identifier;

    /**
     * @return string
     */
    public function __toString() {
        return ':' . $this->identifier;
    }

}