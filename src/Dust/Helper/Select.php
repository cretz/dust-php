<?php
namespace Dust\Helper;

use Dust\Evaluate;
class Select {
    public function __invoke(Evaluate\Chunk $chunk, Evaluate\Context $context, Evaluate\Bodies $bodies, Evaluate\Parameters $params) {
        //evaluate key here
        if (!isset($params->{'key'})) $chunk->setError('Key parameter required');
        $key = $params->{'key'};
        //just eval body with some special state
        return $chunk->render($bodies->block, $context->pushState(new Evaluate\State((object)[
            '__selectInfo' => (object)[
                'selectComparisonSatisfied' => false,
                'key' => $key
            ]
        ])));
    }
    
}