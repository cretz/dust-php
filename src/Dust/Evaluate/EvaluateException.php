<?php
namespace Dust\Evaluate;

use Dust\Ast;

class EvaluateException extends \Exception
{
    public $ast;

    public function __construct(Ast\Ast $ast = NULL, $message = NULL) {
        $this->ast = $ast;
        parent::__construct($message);
    }

}