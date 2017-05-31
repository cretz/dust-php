<?php
namespace Dust;

class StandardTest extends DustTestBase {
    public function testContextTypes() {
        $template = '{title}' . '<ul>' . '{#names}' . '  <li>{name}</li>{~n}' . '{/names}' . '</ul>';
        $expected = 'Famous People' . '<ul>' . "  <li>Larry</li>\n" . "  <li>Curly</li>\n" . "  <li>Moe</li>\n" . '</ul>';
        //ok, test w/ associative array
        $this->assertTemplate($expected, $template, [
            "title" => 'Famous People',
            "names" => [
                ["name" => 'Larry'],
                ["name" => 'Curly'],
                ["name" => 'Moe']
            ]
        ]);
        //dynamic object
        $this->assertTemplate($expected, $template, (object)[
            "title" => 'Famous People',
            "names" => [
                (object)["name" => 'Larry'],
                (object)["name" => 'Curly'],
                (object)["name" => 'Moe']
            ]
        ]);
        //json (basically same as above)
        $this->assertTemplate($expected, $template, json_decode('{' . '"title": "Famous People",' . '"names": [' . '{ "name": "Larry" },' . '{ "name": "Curly" },' . '{ "name": "Moe" }' . ']' . '}'));
        //class
        $this->assertTemplate($expected, $template, new StoogesContext());
    }
    
    public function testArrayAccess() {
        $this->assertTemplate('123', '{#items}{.}{/items}', (object)["items" => new \ArrayObject([1, 2, 3])]);
    }
    
    public function testStringIndex() {
        $this->assertTemplate('a => b,2 => c,foo => blah', '{#items}{$idx} => {.}{@sep},{/sep}{/items}', ["items" => ["a" => 'b', 2 => 'c', "foo" => 'blah']]);
    }
    
    public function testAutoloaderOverride() {
        //override
        $autoloaderInvoked = false;
        $this->dust->autoloaderOverride = function ($name) use (&$autoloaderInvoked) {
            $autoloaderInvoked = true;
            $this->assertEquals('testCustom', $name);
            return $this->dust->compile('{#children}Child: {.}, {/children}');
        };
        //now run partial w/ expectation
        $this->assertTemplate('Before, Child: foo, Child: bar, After', '{#item}Before, {>testCustom/}After{/item}', (object)[
            "item" => (object)[
                "children" => ['foo', 'bar']
            ]
        ]);
        $this->assertTrue($autoloaderInvoked);
    }
    
    public function testCustomFilter() {
        $this->dust->filters['stripTags'] = new StripTagsFilter();
        $this->assertTemplate('Value: foo, bar', 'Value: {contents|stripTags}', (object)["contents" => '<div>foo, <br /><strong>bar</strong></div>']);
    }
    
    public function testCustomHelper() {
        //from manual
        $this->dust->helpers['substr'] = function (Evaluate\Chunk $chunk, Evaluate\Context $ctx, Evaluate\Bodies $bodies, Evaluate\Parameters $params) {
            //make sure str is present
            if (!isset($params->{'str'})) return $chunk->setError('Parameter required: str');
            //parse parameters
            $str = $params->{'str'};
            $begin = isset($params->{'begin'}) ? $params->{'begin'} : 0;
            $end = isset($params->{'end'}) ? $params->{'end'} : null;
            $len = isset($params->{'len'}) ? $params->{'len'} : null;
            //if len is set, use it instead of end
            if ($len !== null) return $chunk->write(substr($str, $begin, $len));
            if ($end !== null) return $chunk->write(substr($str, $begin, $end - $begin));
            return $chunk->write(substr($str, $begin));
        };
        //test some things (kinda taken from PHP manual)
        $this->assertTemplate('bcdef,bcd,abcd,abcdef,bc', '{@substr str="abcdef" begin=1 /},' . '{@substr str="abcdef" begin=1 len=3 /},' . '{@substr str="abcdef" len=4 /},' . '{@substr str="abcdef" len=8 /},' . '{@substr str="abcdef" begin=1 end=3 /}', (object)[]);
    }

    public function testIssetAccess() {
        $this->assertTemplate('Farce,slapstick,musical comedy', '{#genres}{.}{@sep},{/sep}{/genres}', new StoogesContext());
    }
    
}