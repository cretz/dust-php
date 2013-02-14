<?php
namespace Dust\Helper;

class SelectTest extends \Dust\DustTestBase {
    public function testSelect() {
        $templ = '{@select key="{value}"}{@eq value=5}five{/eq}{@eq value=6}six{/eq}{@default}def{/default}{/select}';
        $this->assertTemplate('five', $templ, (object)["value" => 5]);
        $this->assertTemplate('six', $templ, (object)["value" => 6]);
        $this->assertTemplate('def', $templ, (object)["value" => 'yourmom']);
    }
    
}