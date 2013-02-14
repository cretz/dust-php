<?php
namespace Dust\Helper;

class ComparisonTestBase extends \Dust\DustTestBase {
    private $name;
    
    public function __construct($name) { $this->name = $name; parent::__construct(); }
    
    public function assertValidResult($key, $value, $expectsValid) {
        $this->assertTemplate($expectsValid ? 'yes' : 'no', '{@' . $this->name . ' key=one value=two}yes{:else}no{/' . $this->name . '}', (object)["one" => $key, "two" => $value]);
    }
    
}