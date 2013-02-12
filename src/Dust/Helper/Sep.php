<?php
namespace Dust\Helper;

use Dust\Evaluate;
class Sep {
    public function __invoke(Evaluate\Chunk $chunk, Evaluate\Context $context, Evaluate\Bodies $bodies) {
        $index = $context->get('idx');
        if ($index === null) $chunk->setError('Sep must be inside array');
        $len = $context->get('len');
        if ($index < $len - 1) $chunk->render($bodies->block, $context);
        else return $chunk;
    }
    
}