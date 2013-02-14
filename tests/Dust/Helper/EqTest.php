<?php
namespace Dust\Helper;

class EqTest extends ComparisonTestBase {
    public function __construct() { parent::__construct('eq'); }
    
    public function testEq() {
        $this->assertValidResult(12, 12, true);
        $this->assertValidResult(12, '12', true);
        $this->assertValidResult(12, false, false);
        $this->assertValidResult([1, 2], [1, 2], true);
        $this->assertValidResult('FOO', 'foo', false);
    }
    
}