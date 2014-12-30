<?php
namespace Dust\Evaluate;

use Dust\Ast;

class Context
{
    public $evaluator;

    public $parent;

    public $head;

    public $currentFilePath;

    public function __construct(Evaluator $evaluator, Context $parent = NULL, State $head = NULL) {
        $this->evaluator = $evaluator;
        $this->parent = $parent;
        $this->head = $head;
        if($parent != NULL)
        {
            $this->currentFilePath = $parent->currentFilePath;
        }
    }

    public function get($str) {
        $ident = new Ast\Identifier(-1);
        $ident->key = $str;
        $resolved = $this->resolve($ident);
        $resolved = $this->evaluator->normalizeResolved($this, $resolved, new Chunk($this->evaluator));
        if($resolved instanceof Chunk)
        {
            return $resolved->getOut();
        }

        return $resolved;
    }

    public function push($head, $index = NULL, $length = NULL, $iterationCount = NULL) {
        $state = new State($head);
        if($index !== NULL)
        {
            $state->params['$idx'] = $index;
        }
        if($length !== NULL)
        {
            $state->params['$len'] = $length;
        }
        if($iterationCount !== NULL)
        {
            $state->params['$iter'] = $iterationCount;
        }

        return $this->pushState($state);
    }

    public function pushState(State $head) {
        return new Context($this->evaluator, $this, $head);
    }

    public function resolve(Ast\Identifier $identifier, $forceArrayLookup = false, $mainValue = NULL) {
        if($mainValue === NULL)
        {
            $mainValue = $this->head->value;
        }
        //try local
        $resolved = $this->resolveLocal($identifier, $mainValue, $forceArrayLookup);
        //forced local?
        if($identifier->preDot)
        {
            return $resolved;
        }
        //if it's not there, we can try the forced parent
        if($resolved === NULL && $this->head->forcedParent)
        {
            $resolved = $this->resolveLocal($identifier, $this->head->forcedParent, $forceArrayLookup);
        }
        //if it's still not there, we can try parameters
        if($resolved === NULL && count($this->head->params) > 0)
        {
            //just force an array lookup
            $resolved = $this->resolveLocal($identifier, $this->head->params, true);
        }
        //not there and not forced parent? walk up
        if($resolved === NULL && $this->head->forcedParent === NULL && $this->parent != NULL)
        {
            $resolved = $this->parent->resolve($identifier, $forceArrayLookup);
        }

        return $resolved;
    }

    public function resolveLocal(Ast\Identifier $identifier, $parentObject, $forceArrayLookup = false) {
        $key = NULL;
        if($identifier->key != NULL)
        {
            $key = $identifier->key;
        }
        elseif($identifier->number != NULL)
        {
            $key = intval($identifier->number);
            //if this isn't an array lookup, just return the number
            if(!$forceArrayLookup)
            {
                return $key;
            }
        }
        $result = NULL;
        //no key, no array, but predot means result is just the parent
        if($key === NULL && $identifier->preDot && $identifier->arrayAccess == NULL)
        {
            $result = $parentObject;
        }
        //try to find on object (if we aren't forcing array lookup)
        if(!$forceArrayLookup && $key !== NULL)
        {
            $result = $this->findInObject($key, $parentObject);
        }
        //now, try to find in array
        if($result === NULL && $key !== NULL)
        {
            $result = $this->findInArrayAccess($key, $parentObject);
        }
        //if it's there (or has predot) and has array access, try to get array child
        if($identifier->arrayAccess != NULL)
        {
            //find the key
            $arrayKey = $this->resolve($identifier->arrayAccess, false, $parentObject);
            if($arrayKey !== NULL)
            {
                $keyIdent = new Ast\Identifier(-1);
                if(is_numeric($arrayKey))
                {
                    $keyIdent->number = strval($arrayKey);
                }
                else
                {
                    $keyIdent->key = (string) $arrayKey;
                }
                //lookup by array key
                if($result !== NULL)
                {
                    $result = $this->resolveLocal($keyIdent, $result, true);
                }
                elseif($identifier->preDot)
                {
                    $result = $this->resolveLocal($keyIdent, $parentObject, true);
                }
            }
        }
        //if it's there and has next, use it
        if($result !== NULL && $identifier->next && !is_callable($result))
        {
            $result = $this->resolveLocal($identifier->next, $result);
        }

        return $result;
    }

    public function findInObject($key, $parent) {
        if(is_object($parent) && !is_numeric($key))
        {
            //prop or method
            if(array_key_exists($key, $parent))
            {
                return $parent->{$key};
            }
            elseif(method_exists($parent, $key))
            {
                return (new \ReflectionMethod($parent, $key))->getClosure($parent);
            }
            elseif(is_callable([
                $parent,
                'get' . ucfirst($key)
            ]))
            {
                $getter = 'get' . ucfirst($key);

                return $parent->$getter();
            }
        }
        else
        {
            return NULL;
        }
    }

    public function findInArrayAccess($key, $value) {
        if((is_array($value) || $value instanceof \ArrayAccess) && isset($value[ $key ]))
        {
            return $value[ $key ];
        }
        else
        {
            return NULL;
        }
    }

    public function current() {
        if($this->head->forcedParent != NULL)
        {
            return $this->head->forcedParent;
        }

        return $this->head->value;
    }

    public function rebase($head) {
        return $this->rebaseState(new State($head));
    }

    public function rebaseState(State $head) {
        //gotta get top parent
        $topParent = $this;
        while($topParent->parent != NULL)
            $topParent = $topParent->parent;

        //now create
        return new Context($this->evaluator, $topParent, $head);
    }

}
