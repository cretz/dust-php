<?php
namespace Dust\Ast;

class Section extends Part
{
    public static $acceptableTypes = ['#', '?', '^', '<', '+', '@', '%'];

    public $type;

    public $identifier;

    public $context;

    public $parameters;

    /**
     * @var Body
     */
    public $body;

    public $bodies;

    public function __toString() {
        $str = '{' . $this->type . $this->identifier;
        if($this->context != NULL)
        {
            $str .= $this->context;
        }
        if(!empty($this->parameters))
        {
            foreach($this->parameters as $value)
            {
                $str .= ' ' . $value;
            }
        }
        $str .= '}';
        if($this->body != NULL)
        {
            $str .= $this->body;
        }
        if(!empty($this->bodies))
        {
            foreach($this->bodies as $value)
            {
                $str .= $value;
            }
        }
        $str .= '{/' . $this->identifier;

        return $str;
    }

}