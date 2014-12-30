<?php
namespace Dust\Evaluate
{

    use Dust\Ast;

    class Chunk
    {
        /**
         * @var \Dust\Evaluate\Evaluator
         */
        protected $evaluator;

        /**
         * @var string
         */
        protected $out = '';

        public $tapStack;

        public $pendingNamedBlocks;

        public $pendingNamedBlockOffset = 0;

        public $setNamedStrings;

        /**
         * @param \Dust\Evaluate\Evaluator $evaluator
         */
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

        /**
         * @param string $str
         *
         * @return $this
         */
        public function write($str) {
            $this->out .= $str;

            return $this;
        }

        /**
         * @param string $name
         *
         * @return object
         */
        public function markNamedBlockBegin($name) {
            if(!array_key_exists($name, $this->pendingNamedBlocks))
            {
                $this->pendingNamedBlocks[ $name ] = [];
            }
            $block = (object) [
                "begin" => strlen($this->out),
                "end"   => NULL
            ];
            $this->pendingNamedBlocks[ $name ][] = $block;

            return $block;
        }

        /**
         * @param $block
         */
        public function markNamedBlockEnd($block) {
            $block->end = strlen($this->out);
        }

        /**
         * @param $name
         */
        public function replaceNamedBlock($name) {
            //we need to replace inside of chunk the begin/end
            if(array_key_exists($name, $this->pendingNamedBlocks) && array_key_exists($name, $this->setNamedStrings))
            {
                $namedString = $this->setNamedStrings[ $name ];
                //get all blocks
                $blocks = $this->pendingNamedBlocks[ $name ];
                //we need to reverse the order to replace backwards first to keep line counts right
                usort($blocks, function ($a, $b)
                {
                    return $a->begin > $b->begin ? -1 : 1;
                });
                //hold on to pre-count
                $preCount = strlen($this->out);
                //loop and splice string
                foreach($blocks as $value)
                {
                    $text = substr($this->out, 0, $value->begin + $this->pendingNamedBlockOffset) . $namedString;
                    if($value->end != NULL)
                    {
                        $text .= substr($this->out, $value->end + $this->pendingNamedBlockOffset);
                    }
                    else
                    {
                        $text .= substr($this->out, $value->begin + $this->pendingNamedBlockOffset);
                    }
                    $this->out = $text;
                }
                //now we have to update all the pending offset
                $this->pendingNamedBlockOffset += strlen($this->out) - $preCount;
            }
        }

        public function setAndReplaceNamedBlock(Ast\Section $section, Context $ctx) {
            $output = '';
            //if it has no body, we don't do anything
            if($section != NULL && $section->body != NULL)
            {
                //run the body
                $output = $this->evaluator->evaluateBody($section->body, $ctx, $this->newChild())->out;
            }
            //save it
            $this->setNamedStrings[ $section->identifier->key ] = $output;
            //try and replace
            $this->replaceNamedBlock($section->identifier->key);
        }

        public function setError($error, Ast\Body $ast = NULL) {
            $this->evaluator->error($ast, $error);

            return $this;
        }

        public function render(Ast\Body $ast, Context $context) {
            $text = $this;
            if($ast != NULL)
            {
                $text = $this->evaluator->evaluateBody($ast, $context, $this);
                if($this->tapStack != NULL)
                {
                    foreach($this->tapStack as $value)
                    {
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

        /**
         * @return string
         */
        public function getOut() {
            return $this->out;
        }
    }
}