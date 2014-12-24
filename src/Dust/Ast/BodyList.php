<?php
namespace Dust\Ast;

class BodyList extends Ast
{
    /**
     * @var
     */
    public $key;

    /**
     * @var
     */
    public $body;

    /**
     * @return string
     */
    public function __toString() {
        $str = '{:' . $this->key . '}';
        if($this->body != NULL)
        {
            $str .= $this->body;
        }

        return $str;
    }

}