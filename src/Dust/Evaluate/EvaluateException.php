<?php
namespace Dust\Evaluate;

use Dust\Ast;
class EvaluateException extends \Exception {
    public $ast;
    
    public function __construct(Ast\Ast $ast = null, $message = null) {
        $this->ast = $ast;
        parent::__construct($message);
    }
    
}