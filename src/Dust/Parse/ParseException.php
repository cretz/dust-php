<?php
namespace Dust\Parse;

class ParseException extends \Dust\DustException {
    public $line;
    
    public $col;
    
    public function __construct($message, $line, $col) {
        $this->line = $line;
        $this->col = $col;
        parent::__construct('(' . $line . ',' . $col . ') ' . $message);
    }
    
}