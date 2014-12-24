<?php
namespace Dust\Ast;

class Body extends Ast
{
    /**
     * @var string
     */
    public $filePath;

    /**
     * @var array
     */
    public $parts;

    /**
     * @return string
     */
    public function __toString() {
        $str = '';
        if(!empty($this->parts))
        {
            foreach($this->parts as $value)
            {
                $str .= $value;
            }
        }

        return $str;
    }

}