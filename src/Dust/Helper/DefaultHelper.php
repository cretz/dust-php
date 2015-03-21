<?php
namespace Dust\Helper;

use Dust\Evaluate;
class DefaultHelper {
    public function __invoke(Evaluate\Chunk $chunk, Evaluate\Context $context, Evaluate\Bodies $bodies) {
        $selectInfo = $context->get('__selectInfo');
        if ($selectInfo == null) $chunk->setError('Default must be inside select');
        //check
        if (!$selectInfo->selectComparisonSatisfied) {
            return $chunk->render($bodies->block, $context);
        } else return $chunk;
    }
    
}