<?php
namespace Dust\Evaluate;

use Dust\Ast;
class Context {
    public $parent;
    
    public $head;
    
    public function __construct(Context $parent = null, State $head = null) {
        $this->parent = $parent;
        $this->head = $head;
    }
    
    public function get($str) {
        $ident = new Ast\Identifier(-1);
        $ident->key = $str;
        return $this->resolve($ident);
    }
    
    public function push($head, $index = null, $length = null) {
        $state = new State($head);
        if ($index !== null) $state->params['$idx'] = $index;
        if ($length !== null) $state->params['$len'] = $length;
        return $this->pushState($state);
    }
    
    public function pushState(State $head) {
        return new Context($this, $head);
    }
    
    public function resolve(Ast\Identifier $identifier, $forceArrayLookup = false, $mainValue = null) {
        if ($mainValue === null) $mainValue = $this->head->value;
        //try local
        $resolved = $this->resolveLocal($identifier, $mainValue, $forceArrayLookup);
        //forced local?
        if ($identifier->preDot) return $resolved;
        //if it's not there, we can try the forced parent
        if ($resolved === null && $this->head->forcedParent) {
            $resolved = $this->resolveLocal($identifier, $this->head->forcedParent, $forceArrayLookup);
        }
        //if it's still not there, we can try parameters
        if ($resolved === null && count($this->head->params) > 0) {
            //just force an array lookup
            $resolved = $this->resolveLocal($identifier, $this->head->params, true);
        }
        //not there and not forced parent? walk up
        if ($resolved === null && $this->head->forcedParent === null && $this->parent != null) {
            $resolved = $this->parent->resolve($identifier, $forceArrayLookup);
        }
        return $resolved;
    }
    
    public function resolveLocal(Ast\Identifier $identifier, $parentObject, $forceArrayLookup = false) {
        $key = null;
        if ($identifier->key != null) $key = $identifier->key;
        elseif ($identifier->number != null) {
            $key = intval($identifier->number);
            //if this isn't an array lookup, just return the number
            if (!$forceArrayLookup) return $key;
        }
        $result = null;
        //no key, no array, but predot means result is just the parent
        if ($key === null && $identifier->preDot && $identifier->arrayAccess == null) {
            $result = $parentObject;
        }
        //try to find on object (if we aren't forcing array lookup)
        if (!$forceArrayLookup && $key !== null) $result = $this->findInObject($key, $parentObject);
        //now, try to find in array
        if ($result === null && $key !== null) $result = $this->findInArrayAccess($key, $parentObject);
        //if it's there (or has predot) and has array access, try to get array child
        if ($identifier->arrayAccess != null) {
            //find the key
            $arrayKey = $this->resolve($identifier->arrayAccess, false, $parentObject);
            if ($arrayKey !== null) {
                $keyIdent = new Ast\Identifier(-1);
                if (is_numeric($arrayKey)) $keyIdent->number = strval($arrayKey);
                else $keyIdent->key = (string) $arrayKey;
                //lookup by array key
                if ($result !== null) $result = $this->resolveLocal($keyIdent, $result, true);
                elseif ($identifier->preDot) $result = $this->resolveLocal($keyIdent, $parentObject, true);
            }
        }
        //if it's there and has next, use it
        if ($result !== null && $identifier->next && !is_callable($result)) {
            $result = $this->resolveLocal($identifier->next, $result);
        }
        return $result;
    }
    
    public function findInObject($key, $parent) {
        if (is_object($parent) && !is_numeric($key)) {
            //prop or method
            if (array_key_exists($key, $parent)) {
                return $parent->{$key};
            } elseif (method_exists($parent, $key)) {
                return (new \ReflectionMethod($parent, $key))->getClosureThis();
            }
        } else return null;
    }
    
    public function findInArrayAccess($key, $value) {
        if ((is_array($value) || $value instanceof ArrayAccess) && isset($value[$key])) {
            return $value[$key];
        } else return null;
    }
    
    public function current() {
        if ($this->head->forcedParent != null) return $this->head->forcedParent;
        return $this->head->value;
    }
    
    public function rebase($head) {
        return $this->rebaseState(new State($head));
    }
    
    public function rebaseState(State $head) {
        //gotta get top parent
        $topParent = $this;
        while ($topParent->parent != null) $topParent = $topParent->parent;
        //now create
        return new Context($topParent, $head);
    }
    
}