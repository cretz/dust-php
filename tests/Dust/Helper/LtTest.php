<?php
namespace Dust\Helper;

class LtTest extends ComparisonTestBase {
    public function __construct() { parent::__construct('lt'); }
    
    public function testLt() {
        $this->assertValidResult(12, 12, false);
        $this->assertValidResult(12, '12', false);
        $this->assertValidResult(12, 15, true);
        $this->assertValidResult(15, 12, false);
        $this->assertValidResult([1, 2], [3, 4], true);
        $this->assertValidResult('foo', 'bar', false);
        $this->assertValidResult('bar', 'foo', true);
    }
    
}