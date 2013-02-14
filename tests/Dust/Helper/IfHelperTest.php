<?php
namespace Dust\Helper;

use Dust\Helper;
class IfHelperTest extends \Dust\DustTestBase {
    public function testIfHelper() {
        $this->dust->helpers['if'] = new Helper\IfHelper();
        $templ = '{@if cond="{x} < {y} || {x} < 3"}yes{:else}no{/if}';
        $this->assertTemplate('yes', $templ, (object)["x" => 12, "y" => 15]);
        $this->assertTemplate('yes', $templ, (object)["x" => 2, "y" => 1]);
        $this->assertTemplate('no', $templ, (object)["x" => 7, "y" => 5]);
    }
    
}