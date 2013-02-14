<?php
namespace Dust\Helper;

class SepTest extends \Dust\DustTestBase {
    public function testSep() {
        $this->assertTemplate('1, 2, 3, 4', '{#items}{.}{@sep}, {/sep}{/items}', (object)["items" => [1, 2, 3, 4]]);
    }
    
}