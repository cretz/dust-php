<?php
namespace Dust\Helper;

use Dust\Evaluate;
class Select {
    public function __invoke(Evaluate\Chunk $chunk, Evaluate\Context $context, Evaluate\Bodies $bodies) {
        //just eval body with some special state
        return $chunk->render($bodies->block, $context->pushState(new Evaluate\State((object)[
            '__selectInfo' => (object)['selectComparisonSatisfied' => false]
        ])));
    }
    
}