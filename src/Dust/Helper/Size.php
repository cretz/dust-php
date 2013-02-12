<?php
namespace Dust\Helper;

use Dust\Evaluate;
class Size {
    public function __invoke(Evaluate\Chunk $chunk, Evaluate\Context $context) {
        $key = $context->get('key');
        if (is_null($key)) return $chunk;
        elseif (is_numeric($key)) return $chunk->write($key);
        elseif (is_string($key)) return $chunk->write(strlen($key));
        elseif (is_array($key) || $key instanceof Countable) return $chunk->write(count($key));
        elseif (is_object($key)) return $chunk->write(count(get_object_vars($key)));
        else return $chunk->write(strlen(strval($key)));
    }
    
}