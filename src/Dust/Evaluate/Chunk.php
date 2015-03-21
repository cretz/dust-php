<?php
namespace Dust\Evaluate;

use Dust\Ast;
class Chunk {
    public $evaluator;
    
    public $out = '';
    
    public $tapStack;
    
    public $pendingNamedBlocks;
    
    public $pendingNamedBlockOffset = 0;
    
    public $setNamedStrings;
    
    public function __construct(Evaluator $evaluator) {
        $this->evaluator = $evaluator;
        $this->pendingNamedBlocks = [];
        $this->setNamedStrings = [];
    }
    
    public function newChild() {
        $chunk = new Chunk($this->evaluator);
        $chunk->tapStack = &$this->tapStack;
        $chunk->pendingNamedBlocks = &$this->pendingNamedBlocks;
        return $chunk;
    }
    
    public function write($str) {
        $this->out .= $str;
        return $this;
    }
    
    public function markNamedBlockBegin($name) {
        if (!array_key_exists($name, $this->pendingNamedBlocks)) {
            $this->pendingNamedBlocks[$name] = [];
        }
        $block = (object)["begin" => strlen($this->out), "end" => null];
        $this->pendingNamedBlocks[$name][] = $block;
        return $block;
    }
    
    public function markNamedBlockEnd($block) {
        $block->end = strlen($this->out);
    }
    
    public function replaceNamedBlock($name) {
        //we need to replace inside of chunk the begin/end
        if (array_key_exists($name, $this->pendingNamedBlocks) && array_key_exists($name, $this->setNamedStrings)) {
            $namedString = $this->setNamedStrings[$name];
            //get all blocks
            $blocks = $this->pendingNamedBlocks[$name];
            //we need to reverse the order to replace backwards first to keep line counts right
            usort($blocks, function ($a, $b) {
                return $a->begin > $b->begin ? -1 : 1;
            });
            //hold on to pre-count
            $preCount = strlen($this->out);
            //loop and splice string
            foreach ($blocks as $value) {
                $text = substr($this->out, 0, $value->begin + $this->pendingNamedBlockOffset) . $namedString;
                if ($value->end != null) $text .= substr($this->out, $value->end + $this->pendingNamedBlockOffset);
                else $text .= substr($this->out, $value->begin + $this->pendingNamedBlockOffset);
                $this->out = $text;
            }
            //now we have to update all the pending offset
            $this->pendingNamedBlockOffset += strlen($this->out) - $preCount;
        }
    }
    
    public function setAndReplaceNamedBlock(Ast\Section $section, Context $ctx) {
        $output = '';
        //if it has no body, we don't do anything
        if ($section != null && $section->body != null) {
            //run the body
            $output = $this->evaluator->evaluateBody($section->body, $ctx, $this->newChild())->out;
        }
        //save it
        $this->setNamedStrings[$section->identifier->key] = $output;
        //try and replace
        $this->replaceNamedBlock($section->identifier->key);
    }
    
    public function setError($error, Ast\Body $ast = null) {
        $this->evaluator->error($ast, $error);
        return $this;
    }
    
    public function render(Ast\Body $ast, Context $context) {
        $text = $this;
        if ($ast != null) {
            $text = $this->evaluator->evaluateBody($ast, $context, $this);
            if ($this->tapStack != null) {
                foreach ($this->tapStack as $value) {
                    $text->out = $value($text->out);
                }
            }
        }
        return $text;
    }
    
    public function tap(callable $callback) {
        $this->tapStack[] = $callback;
        return $this;
    }
    
    public function untap() {
        array_pop($this->tapStack);
        return $this;
    }
    
}