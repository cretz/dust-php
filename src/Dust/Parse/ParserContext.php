<?php
namespace Dust\Parse;

class ParserContext {
    public $str;
    
    public $offset = 0;
    
    public $offsetTransactionStack = [];
    
    public function __construct($str) { $this->str = $str; }
    
    public function beginTransaction() {
        $this->offsetTransactionStack[] = $this->offset;
    }
    
    public function rollbackTransaction() {
        $this->offset = array_pop($this->offsetTransactionStack);
    }
    
    public function commitTransaction() {
        array_pop($this->offsetTransactionStack);
    }
    
    public function getCurrentLineAndCol() {
        return $this->getLineAndColFromOffset($this->offset);
    }
    
    public function getLineAndColFromOffset($offset) {
        $line = 0;
        $prev = -1;
        while (true) {
            $newPrev = strpos($this->str, "\n", $prev + 1);
            if ($newPrev === false || $newPrev > $offset) break;
            $prev = $newPrev;
            $line++;
        }
        if ($prev == -1) $prev = 0;
        return (object)[
            "line" => $line + 1,
            "col" => $offset - $prev
        ];
    }
    
    public function peek($offset = 1) {
        if (strlen($this->str) <= $this->offset + ($offset - 1)) return null;
        return $this->str[$this->offset + ($offset - 1)];
    }
    
    public function next() {
        $this->offset++;
        if (strlen($this->str) <= ($this->offset - 1)) return null;
        return $this->str[($this->offset - 1)];
    }
    
    public function skipWhitespace() {
        $found = false;
        while (in_array($this->peek(), [' ', "\t", "\v", "\f", "\r", "\n"])) {
            $this->offset++;
            $found = true;
        }
        return $found;
    }
    
}