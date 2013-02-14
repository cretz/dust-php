<?php
namespace Dust;

class DustTestBase extends \PHPUnit_Framework_TestCase {
    public $dust;
    
    public function setUp() {
        $this->dust = new Dust();
    }
    
    public function assertTemplate($expected, $template, $context) {
        $compiled = $this->dust->compile($template);
        $this->assertEquals($expected, $this->dust->renderTemplate($compiled, $context));
    }
    
}