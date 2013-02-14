<?php
namespace Dust\Helper;

use Dust\Evaluate;
class Comparison {
    public function __invoke(Evaluate\Chunk $chunk, Evaluate\Context $context, Evaluate\Bodies $bodies, Evaluate\Parameters $params) {
        //load value
        if (!isset($params->{'value'})) $chunk->setError('Value parameter required');
        $value = $params->{'value'};
        //load select info
        $selectInfo = $context->get('__selectInfo');
        //load key
        $key = null;
        if (isset($params->{'key'})) $key = $params->{'key'};
        elseif ($selectInfo != null) $key = $selectInfo->key;
        else $chunk->setError('Must be in select or have key parameter');
        //check
        if ($this->isValid($key, $value)) {
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