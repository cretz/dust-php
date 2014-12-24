<?php
namespace Dust\Ast;

class IdentifierParameter extends Parameter
{
    /**
     * @var string
     */
    public $value;

    /**
     * @return string
     */
    public function __toString() {
        return $this->key . '=' . $this->value;
    }

}