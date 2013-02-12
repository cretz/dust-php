<?php
namespace Dust\Helper;

use Dust\Evaluate;
class Comparison {
    public function __invoke(Evaluate\Chunk $chunk, Evaluate\Context $context, Evaluate\Bodies $bodies, Evaluate\Parameters $params) {
        if (!isset($params->{'value'})) $chunk->setError('Value parameter required');
        //check
        if ($this->isValid($context->get('key'), $context->get('value'))) {
            $selectInfo = $context->get('__selectInfo');
            if ($selectInfo != null) {
                $selectInfo->selectComparisonSatisfied = true;
            }
            return $chunk->render($bodies->block, $context);
        } elseif (isset($bodies['else'])) {
            return $chunk->render($bodies['else'], $context);
        } else return $chunk;
    }
    
    public function isValid($key, $value) { return false; }
    
}