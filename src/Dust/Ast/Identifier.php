<?php
namespace Dust\Ast;

class Identifier extends Ast
{
    /**
     * @var bool
     */
    public $preDot = false;

    /**
     * @var string
     */
    public $key;

    /**
     * @var int
     */
    public $number;

    /**
     * @var string
     */
    public $arrayAccess;

    /**
     * @var
     */
    public $next;

    public function __toString() {
        $str = '';
        if($this->preDot)
        {
            $str .= '.';
        }
        if($this->key != NULL)
        {
            $str .= $this->key;
        }
        elseif($this->number != NULL)
        {
            $str .= $this->number;
        }
        if($this->arrayAccess != NULL)
        {
            $str .= '[' . $this->arrayAccess . ']';
        }
        if($this->next != NULL)
        {
            $str .= $this->next;
        }

        return $str;
    }

}