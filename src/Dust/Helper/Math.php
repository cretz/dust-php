<?php
namespace Dust\Helper;

use Dust\Evaluate;
class Math {
    public function __invoke(Evaluate\Chunk $chunk, Evaluate\Context $context, Evaluate\Bodies $bodies) {
        $result = $context->get('key');
        if ($result === null) $chunk->setError('Key required');
        $method = $context->get('method');
        if ($method === null) $chunk->setError('Method required');
        $operand = $context->get('operand');
        switch ($method) {
            case 'add':
                $result += $operand;
                break;
            case 'subtract':
                $result -= $operand;
                break;
            case 'multiply':
                $result *= $operand;
                break;
            case 'divide':
                $result /= $operand;
                break;
            case 'mod':
                $result %= $operand;
                break;
            case 'abs':
                $result = abs($result);
                break;
            case 'floor':
                $result = floor($result);
                break;
            case 'ceil':
                $result = ceil($result);
                break;
            default:
                $chunk->setError('Unknown method: ' . $method);
        }
        //no bodies means just write
        if ($bodies == null || $bodies->block == null) return $chunk->write($result);
        else {
            //just eval body with some special state
            return $chunk->render($bodies->block, $context->pushState(new Evaluate\State((object)[
                '__selectInfo' => (object)['selectComparisonSatisfied' => false],
                'key' => $result
            ])));
        }
    }
    
}