<?php
namespace Dust\Helper;

class SizeTest extends \Dust\DustTestBase {
    public function testSize() {
        $templ = '{@size key=val /}';
        $this->assertTemplate('4', $templ, (object)["val" => [1, 2, 3, 4]]);
        $this->assertTemplate('6', $templ, (object)["val" => 'abcdef']);
        $this->assertTemplate('2', $templ, (object)["val" => (object)["foo" => 12, "bar" => 15]]);
        $this->assertTemplate('23', $templ, (object)["val" => 23]);
        $this->assertTemplate('3.14', $templ, (object)["val" => 3.14]);
        $this->assertTemplate('0', $templ, (object)[]);
        $this->assertTemplate('0', $templ, (object)["val" => '']);
        $this->assertTemplate('1', $templ, (object)["val" => true]);
        $this->assertTemplate('0', $templ, (object)["val" => false]);
    }
    
}