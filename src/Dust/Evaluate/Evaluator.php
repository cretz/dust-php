<?php
namespace Dust\Evaluate;

use Dust\Ast;
use Dust\Filter;

class Evaluator
{
    public $dust;

    public $options;

    public function __construct(\Dust\Dust $dust, $options = NULL) {
        if($options === NULL)
        {
            $options = new EvaluatorOptions();
        }
        $this->dust = $dust;
        $this->options = $options;
    }

    public function error(Ast\Ast $ast = NULL, $message = NULL) {
        throw new EvaluateException($ast, $message);
    }

    public function evaluate(Ast\Body $source, $state) {
        return trim($this->evaluateBody($source, new Context($this, NULL, new State($state)), new Chunk($this))->getOut());
    }

    public function evaluateBody(Ast\Body $body, Context $ctx, Chunk $chunk) {
        //go ahead and set the file path on the current context
        if($body->filePath !== NULL)
        {
            $ctx->currentFilePath = $body->filePath;
        }
        foreach($body->parts as $part)
        {
            if($part instanceof Ast\Comment)
            {
            }
            elseif($part instanceof Ast\Section)
            {
                $chunk = $this->evaluateSection($part, $ctx, $chunk);
            }
            elseif($part instanceof Ast\Partial)
            {
                $chunk = $this->evaluatePartial($part, $ctx, $chunk);
            }
            elseif($part instanceof Ast\Special)
            {
                $chunk = $this->evaluateSpecial($part, $ctx, $chunk);
            }
            elseif($part instanceof Ast\Reference)
            {
                $chunk = $this->evaluateReference($part, $ctx, $chunk);
            }
            elseif($part instanceof Ast\Buffer)
            {
                $chunk = $this->evaluateBuffer($part, $ctx, $chunk);
            }
        }

        return $chunk;
    }

    public function evaluateSection(Ast\Section $section, Context $ctx, Chunk $chunk) {
        //stuff that doesn't need resolution
        if($section->type == '+')
        {
            if($section->identifier->key == NULL)
            {
                $this->error($section->identifier, 'Evaluated identifier for partial not supported');
            }
            //mark beginning
            $block = $chunk->markNamedBlockBegin($section->identifier->key);
            //render default contents
            if($section->body != NULL)
            {
                $chunk = $this->evaluateBody($section->body, $ctx, $chunk);
                //mark ending
                $chunk->markNamedBlockEnd($block);
            }
            //go ahead and try to replace
            $chunk->replaceNamedBlock($section->identifier->key);
        }
        elseif($section->type == '<')
        {
            if($section->identifier->key == NULL)
            {
                $this->error($section->identifier, 'Evaluated identifier for partial not supported');
            }
            $chunk->setAndReplaceNamedBlock($section, $ctx);
        }
        elseif($section->type == '@')
        {
            if($section->identifier->key == NULL)
            {
                $this->error($section->identifier, 'Evaluated identifier for helper not supported');
            }
            //do we have the helper?
            if(!isset($this->dust->helpers[ $section->identifier->key ]))
            {
                $this->error($section->identifier, 'Unable to find helper');
            }
            $helper = $this->dust->helpers[ $section->identifier->key ];
            //build state w/ no current value
            $state = new State(NULL);
            //do we have an explicit context?
            if($section->context != NULL)
            {
                $state->forcedParent = $ctx->resolve($section->context->identifier);
            }
            //how about params?
            if(!empty($section->parameters))
            {
                $state->params = $this->evaluateParameters($section->parameters, $ctx);
            }
            //now run the helper
            $chunk = $this->handleCallback($ctx->pushState($state), $helper, $chunk, $section);
        }
        else
        {
            //build a new state set
            $resolved = $ctx->resolve($section->identifier);
            //build state if not empty
            $state = new State($resolved);
            //do we have an explicit context?
            if($section->context != NULL)
            {
                $state->forcedParent = $ctx->resolve($section->context->identifier);
            }
            //how about params?
            if(!empty($section->parameters))
            {
                $state->params = $this->evaluateParameters($section->parameters, $ctx);
            }
            //normalize resolution
            $resolved = $this->normalizeResolved($ctx->pushState($state), $resolved, $chunk, $section);
            //do the needful per type
            switch($section->type)
            {
                case '#':
                    //empty means try else
                    if($this->isEmpty($resolved))
                    {
                        $chunk = $this->evaluateElseBody($section, $ctx, $chunk);
                    }
                    elseif(is_array($resolved) || $resolved instanceof \Traversable)
                    {
                        //array means loop
                        $iterationCount = -1;
                        foreach($resolved as $index => $value)
                        {
                            //run body
                            $chunk = $this->evaluateBody($section->body, $ctx->push($value, $index, count($resolved), ++$iterationCount), $chunk);
                        }
                    }
                    elseif($resolved instanceof Chunk)
                    {
                        $chunk = $resolved;
                    }
                    else
                    {
                        //otherwise, just do the body
                        $chunk = $this->evaluateBody($section->body, $ctx->pushState($state), $chunk);
                    }
                    break;
                case '?':
                    //only if it exists
                    if($this->exists($resolved))
                    {
                        $chunk = $this->evaluateBody($section->body, $ctx, $chunk);
                    }
                    else
                    {
                        $chunk = $this->evaluateElseBody($section, $ctx, $chunk);
                    }
                    break;
                case '^':
                    //only if it doesn't exist
                    if(!$this->exists($resolved))
                    {
                        $chunk = $this->evaluateBody($section->body, $ctx, $chunk);
                    }
                    else
                    {
                        $chunk = $this->evaluateElseBody($section, $ctx, $chunk);
                    }
                    break;
                default:
                    throw new EvaluateException($section, 'Unrecognized type: ' . $section->type);
            }
        }

        return $chunk;
    }

    public function evaluateElseBody(Ast\Section $section, Context $ctx, Chunk $chunk) {
        if($section->bodies != NULL && count($section->bodies) > 0)
        {
            foreach($section->bodies as $value)
            {
                if($value->key == 'else')
                {
                    $chunk = $this->evaluateBody($value->body, $ctx, $chunk);
                }
            }
        }

        return $chunk;
    }

    public function evaluatePartial(Ast\Partial $partial, Context $ctx, Chunk $chunk) {
        $partialName = $partial->key;
        if($partialName == NULL)
        {
            $partialName = $this->toDustString($this->normalizeResolved($ctx, $partial->inline, $chunk));
        }
        if($partialName == NULL)
        {
            return $chunk;
        }
        //+ is a named block
        if($partial->type == '+')
        {
            //mark beginning
            $chunk->markNamedBlockBegin($partialName);
            //go ahead and try to replace
            $chunk->replaceNamedBlock($partialName);

            return $chunk;
        }
        //otherwise, we're >
        //get base directory
        $basePath = $ctx->currentFilePath;
        if($basePath != NULL)
        {
            $basePath = dirname($basePath);
        }
        //load partial
        $partialBody = $this->dust->loadTemplate($partialName, $basePath);
        if($partialBody == NULL)
        {
            return $chunk;
        }
        //null main state
        $state = new State(NULL);
        //partial context?
        if($partial->context != NULL)
        {
            $state->forcedParent = $ctx->resolve($partial->context->identifier);
        }
        //params?
        if(!empty($partial->parameters))
        {
            $state->params = $this->evaluateParameters($partial->parameters, $ctx);
        }

        //render the partial then
        return $this->evaluateBody($partialBody, $ctx->pushState($state), $chunk);
    }

    public function evaluateSpecial(Ast\Special $spl, Context $ctx, Chunk $chunk) {
        switch($spl->key)
        {
            case 'n':
                $chunk->write("\n");
                break;
            case 'r':
                $chunk->write("\r");
                break;
            case 'lb':
                $chunk->write('{');
                break;
            case 'rb':
                $chunk->write('}');
                break;
            case 's':
                $chunk->write(' ');
                break;
            default:
                throw new EvaluateException($spl, 'Unrecognized special: ' . $spl->key);
        }

        return $chunk;
    }

    public function evaluateReference(Ast\Reference $ref, Context $ctx, Chunk $chunk) {
        //resolve
        $resolved = $this->normalizeResolved($ctx, $ctx->resolve($ref->identifier), $chunk);

        if(!$this->isEmpty($resolved))
        {
            if($resolved instanceof Chunk)
            {
                return $resolved;
            }
            //make the string
            if(empty($ref->filters))
            {
                //default filters
                $resolved = array_reduce($this->dust->automaticFilters, function ($prev, Filter\Filter $filter)
                {
                    return $filter->apply($prev);
                }, $resolved);
            }
            else
            {
                //apply filters in order...
                $resolved = array_reduce($ref->filters, function ($prev, Ast\Filter $curr)
                {
                    if(array_key_exists($curr->key, $this->dust->filters))
                    {
                        $filter = $this->dust->filters[ $curr->key ];

                        return $filter->apply($prev);
                    }
                    else
                    {
                        return $prev;
                    }
                }, $resolved);
            }
            $chunk->write($this->toDustString($resolved));
        }

        return $chunk;
    }

    public function evaluateBuffer(Ast\Buffer $buffer, Context $ctx, Chunk $chunk) {
        $chunk->write($buffer->contents);

        return $chunk;
    }

    public function evaluateParameters(array $params, Context $ctx) {
        $ret = [];
        foreach($params as $value)
        {
            if($value instanceof Ast\NumericParameter)
            {
                if(strpos($value->value, '.') === false)
                {
                    $ret[ $value->key ] = intval($value->value);
                }
                else
                {
                    $ret[ $value->key ] = floatval($value->value);
                }
            }
            elseif($value instanceof Ast\IdentifierParameter)
            {
                $ret[ $value->key ] = $ctx->resolve($value->value);
            }
            else
            {
                //we just set this as the actual AST since it is resolved where it's emitted
                $ret[ $value->key ] = $value->value;
            }
        }

        return $ret;
    }

    public function normalizeResolved(Context $ctx, $resolved, Chunk $chunk, Ast\Section $section = NULL) {
        $handledSpecial = true;
        while($handledSpecial)
        {
            if(is_callable($resolved) && !is_string($resolved))
            {
                //call callback
                $resolved = $this->handleCallback($ctx, $resolved, $chunk, $section);
            }
            elseif($resolved instanceof Ast\Inline)
            {
                //resolve full inline parameter
                $newChunk = $chunk->newChild();
                foreach($resolved->parts as $value)
                {
                    if($value instanceof Ast\Reference)
                    {
                        $newChunk = $this->evaluateReference($value, $ctx, $newChunk);
                    }
                    elseif($value instanceof Ast\Special)
                    {
                        $newChunk = $this->evaluateSpecial($value, $ctx, $newChunk);
                    }
                    else
                    {
                        $newChunk->write(strval($value));
                    }
                }
                $resolved = $newChunk->getOut();
                break;
            }
            else
            {
                $handledSpecial = false;
            }
        }

        return $resolved;
    }

    public function isEmpty($val) {
        //numeric not empty
        if(is_numeric($val))
        {
            return false;
        }

        //otherwise, normal empty check
        return empty($val);
    }

    public function exists($val) {
        //object exists
        if(is_object($val))
        {
            return true;
        }
        //numeric exists
        if(is_numeric($val))
        {
            return true;
        }
        //empty string does not exist
        if(is_string($val))
        {
            return !empty($val);
        }
        //false does not exist
        if(is_bool($val))
        {
            return $val;
        }
        //empty arrays do not exist
        if(is_array($val))
        {
            return !empty($val);
        }

        //nulls do not exist
        return !is_null($val);
    }

    public function toDustString($val) {
        if(is_bool($val))
        {
            return $val ? 'true' : 'false';
        }
        if(is_array($val))
        {
            return implode(',', $val);
        }
        if(is_object($val) && !method_exists($val, '__toString'))
        {
            return get_class($val);
        }

        return (string) $val;
    }

    public function handleCallback(Context $ctx, $callback, Chunk $chunk, Ast\Section $section = NULL) {
        //reset "this" on closures
        if($callback instanceof \Closure)
        {
            //find non-closure new "this"
            $newThis = $ctx->head->value;
            if($newThis instanceof \Closure)
            {
                //forced parent?
                if($ctx->head->forcedParent !== NULL)
                {
                    $newThis = $ctx->head->forcedParent;
                }
                elseif($ctx->parent !== NULL)
                {
                    $newThis = $ctx->parent->head->value;
                }
            }
            //must be non-closure object
            if(is_object($newThis) && !($newThis instanceof \Closure))
            {
                $callback = \Closure::bind($callback, $newThis);
            }
        }
        if(is_object($callback) && method_exists($callback, '__invoke'))
        {
            $reflected = new \ReflectionMethod($callback, '__invoke');
        }
        else
        {
            $reflected = new \ReflectionFunction($callback);
        }
        $paramCount = $reflected->getNumberOfParameters();
        $args = [];
        if($paramCount > 0)
        {
            $args[] = $chunk;
            if($paramCount > 1)
            {
                $args[] = $ctx;
                if($paramCount > 2 && $section != NULL)
                {
                    $args[] = new Bodies($section);
                    if($paramCount > 3)
                    {
                        $args[] = new Parameters($this, $ctx);
                    }
                }
            }
        }

        //invoke
        return call_user_func_array($callback, $args);
    }

}
