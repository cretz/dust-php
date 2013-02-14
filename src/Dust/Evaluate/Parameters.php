<?php
namespace Dust\Evaluate;

class Parameters {
    private $evaluator;
    
    private $ctx;
    
    public function __construct(Evaluator $evaluator, Context $ctx) {
        $this->evaluator = $evaluator;
        $this->ctx = $ctx;
    }
    
    public function __get($name) {
        if (isset($this->ctx->head->params[$name])) {
            $resolved = $this->ctx->head->params[$name];
            $newChunk = new Chunk($this->evaluator);
            $resolved = $this->evaluator->normalizeResolved($this->ctx, $resolved, $newChunk);
            if ($resolved instanceof Chunk) return $resolved->out;
            return $resolved;
        }
        return null;
    }
    
    public function __isset($name) {
        return isset($this->ctx->head->params) && array_key_exists($name, $this->ctx->head->params);
    }
    
}