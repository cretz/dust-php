<?php
namespace Dust\Evaluate
{
    use Dust\Ast;

    class Bodies implements \ArrayAccess
    {
        /**
         * @var \Dust\Ast\Section
         */
        private $section;

        /**
         * @var \Dust\Ast\Body
         */
        public $block;

        /**
         * @param \Dust\Ast\Section $section
         */
        public function __construct(Ast\Section $section) {
            $this->section = $section;
            $this->block = $section->body;
        }

        /**
         * @param mixed $offset
         *
         * @return bool
         */
        public function offsetExists($offset) {
            return $this[ $offset ] != NULL;
        }

        /**
         * @param mixed $offset
         *
         * @return null
         */
        public function offsetGet($offset) {
            for($i = 0; $i < count($this->section->bodies); $i++)
            {
                if($this->section->bodies[ $i ]->key == $offset)
                {
                    return $this->section->bodies[ $i ]->body;
                }
            }

            return NULL;
        }

        /**
         * @param mixed $offset
         * @param mixed $value
         *
         * @throws \Dust\Evaluate\EvaluateException
         */
        public function offsetSet($offset, $value) {
            throw new EvaluateException($this->section, 'Unsupported set on bodies');
        }

        /**
         * @param mixed $offset
         *
         * @throws \Dust\Evaluate\EvaluateException
         */
        public function offsetUnset($offset) {
            throw new EvaluateException($this->section, 'Unsupported unset on bodies');
        }

    }
}

