<?php
namespace Dust\Ast;

class Comment extends Part
{
    /**
     * @var string
     */
    public $contents;

    /**
     * @return string
     */
    public function __toString() {
        return '{!' . $this->contents . '!}';
    }

}