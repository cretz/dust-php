<?php
namespace Dust\Helper;

class MathTest extends \Dust\DustTestBase {
    public function testMath() {
        //from manual mostly
        $this->assertTemplate('20', '{@math key="16" method="add" operand="4"/}', (object)[]);
        $this->assertTemplate('16', '{@math key="16.5" method="floor"/}', (object)[]);
        $this->assertTemplate('17', '{@math key="16.5" method="ceil"/}', (object)[]);
        $this->assertTemplate('8', '{@math key="-8" method="abs"/}', (object)[]);
        $this->assertTemplate('010101', '{#items}{@math key="{$idx}" method="mod" operand="2"/}{/items}', (object)["items" => [1, 2, 3, 4, 5, 6]]);
    }
    
}