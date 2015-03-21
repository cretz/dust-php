<?php
namespace Dust\Helper;

use Dust\Evaluate;
class IfHelper {
    public function __invoke(Evaluate\Chunk $chunk, Evaluate\Context $context, Evaluate\Bodies $bodies) {
        //scary and dumb! won't include in default...
        $cond = $context->get('cond');
        if ($cond === null) $chunk->setError('Unable to find cond for if');
        if (eval('return ' . $cond . ';')) return $chunk->render($bodies->block, $context);
        elseif (isset($bodies['else'])) {
            return $chunk->render($bodies['else'], $context);
        } else return $chunk;
    }
    
}