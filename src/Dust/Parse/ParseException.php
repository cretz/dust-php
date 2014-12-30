<?php
namespace Dust\Parse
{
    class ParseException extends \Dust\DustException
    {
        /**
         * @param string $message
         * @param int    $line
         * @param int    $col
         */
        public function __construct($message, $line, $col) {

            parent::__construct('(' . $line . ',' . $col . ') ' . $message);
        }
    }
}

