<?php
namespace Dust;

class SpecTest extends \PHPUnit_Framework_TestCase {
    private static $dust;
    
    public static function setUpBeforeClass() {
        SpecTest::$dust = new Dust();
    }
    
    public static function tearDownAfterClass() {
        SpecTest::$dust = null;
    }
    
    private function runSpecTest($test) {
        //error means expected exception
        if (isset($test->error)) $this->setExpectedException('\Dust\DustException');
        //create parser
        $compiled = SpecTest::$dust->compile($test->source, $test->name);
        $evald = SpecTest::$dust->renderTemplate($compiled, $test->context);
        $this->assertEquals(trim($test->expected), trim($evald));
    }
    
    
    public function testHelloWorld() {
        $test = (object)[
            "name" => "hello_world",
            "source" => "Hello World!",
            "context" => (object)[],
            "expected" => "Hello World!",
            "message" => "should test basic"
        ];
        $this->runSpecTest($test);
    }
    
    public function testShouldTestOneBasicReference() {
        $test = (object)[
            "name" => "should test one basic reference",
            "source" => "{?one}{one}{/one}",
            "context" => (object)["one" => 0],
            "expected" => "0",
            "message" => "should test one basic reference"
        ];
        $this->runSpecTest($test);
    }
    
    public function testImplicit() {
        $test = (object)[
            "name" => "implicit",
            "source" => "{#names}{.}{~n}{/names}",
            "context" => (object)[
                "names" => [
                    "Moe",
                    "Larry",
                    "Curly"
                ]
            ],
            "expected" => "Moe\nLarry\nCurly\n",
            "message" => "should test an implicit array"
        ];
        $this->runSpecTest($test);
    }
    
    public function testRenameKey() {
        $test = (object)[
            "name" => "rename_key",
            "source" => "{#person foo=root}{foo}: {name}, {age}{/person}",
            "context" => (object)[
                "root" => "Subject",
                "person" => (object)[
                    "name" => "Larry",
                    "age" => 45
                ]
            ],
            "expected" => "Subject: Larry, 45",
            "message" => "should test renaming a key"
        ];
        $this->runSpecTest($test);
    }
    
    public function testForceLocal() {
        $test = (object)[
            "name" => "force_local",
            "source" => "{#person}{.root}: {name}, {age}{/person}",
            "context" => (object)[
                "root" => "Subject",
                "person" => (object)[
                    "name" => "Larry",
                    "age" => 45
                ]
            ],
            "expected" => ": Larry, 45",
            "message" => "should test force a key"
        ];
        $this->runSpecTest($test);
    }
    
    public function testEscaped() {
        $test = (object)[
            "name" => "escaped",
            "source" => "{safe|s}{~n}{unsafe}",
            "context" => (object)[
                "safe" => "<script>alert('Hello!')</script>",
                "unsafe" => "<script>alert('Goodbye!')</script>"
            ],
            "expected" => "<script>alert('Hello!')</script>\n&lt;script&gt;alert(&#39;Goodbye!&#39;)&lt;/script&gt;",
            "message" => "should test escaped characters"
        ];
        $this->runSpecTest($test);
    }
    
    public function testUseForCreatingABlockAndUseItToSetParams() {
        $test = (object)[
            "name" => "use . for creating a block and use it to set params",
            "source" => "{#. test=\"you\"}{name} {test}{/.}",
            "context" => (object)[
                "name" => "me"
            ],
            "expected" => "me you",
            "message" => ". creating a block"
        ];
        $this->runSpecTest($test);
    }
    
    public function testSyncKey() {
        $test = (object)[
            "name" => "sync_key",
            "source" => "Hello {type} World!",
            "context" => (object)[
                "type" => function ($chunk) {
                    return "Sync";
                }
            ],
            "expected" => "Hello Sync World!",
            "message" => "should test sync key"
        ];
        $this->runSpecTest($test);
    }
    
    public function testSyncChunk() {
        $test = (object)[
            "name" => "sync_chunk",
            "source" => "Hello {type} World!",
            "context" => (object)[
                "type" => function ($chunk) {
                    return $chunk->write("Chunky");
                }
            ],
            "expected" => "Hello Chunky World!",
            "message" => "should test sync chunk"
        ];
        $this->runSpecTest($test);
    }
    
    public function testBaseTemplate() {
        $test = (object)[
            "name" => "base_template",
            "source" => "Start{~n}{+title}Base Title{/title}{~n}{+main}Base Content{/main}{~n}End",
            "context" => (object)[
                
            ],
            "expected" => "Start\nBase Title\nBase Content\nEnd",
            "message" => "should test base template"
        ];
        $this->runSpecTest($test);
    }
    
    /**
     * @depends testBaseTemplate
     */
    public function testChildTemplate() {
        $test = (object)[
            "name" => "child_template",
            "source" => "{^xhr}{>base_template/}{:else}{+main/}{/xhr}{<title}Child Title{/title}{<main}Child Content{/main}",
            "context" => (object)["xhr" => false],
            "expected" => "Start\nChild Title\nChild Content\nEnd",
            "message" => "should test child template"
        ];
        $this->runSpecTest($test);
    }
    
    /**
     * @depends testRecursion
     */
    public function testRecursion() {
        $test = (object)[
            "name" => "recursion",
            "source" => "{name}{~n}{#kids}{>recursion:./}{/kids}",
            "context" => (object)[
                "name" => "1",
                "kids" => [
                    (object)[
                        "name" => "1.1",
                        "kids" => [
                            (object)[
                                "name" => "1.1.1"
                            ]
                        ]
                    ]
                ]
            ],
            "expected" => "1\n1.1\n1.1.1\n",
            "message" => "should test recursion"
        ];
        $this->runSpecTest($test);
    }
    
    public function testComments() {
        $test = (object)[
            "name" => "comments",
            "source" => "{!\n  Multiline\n  {#foo}{bar}{/foo}\n!}\n{!before!}Hello{!after!}",
            "context" => (object)[],
            "expected" => "Hello",
            "message" => "should test comments"
        ];
        $this->runSpecTest($test);
    }
    
    public function testContext() {
        $test = (object)[
            "name" => "context",
            "source" => "{#list:projects}{name}{:else}No Projects!{/list}",
            "context" => (object)[
                "list" => function ($chunk, $context, $bodies) {
                    {
                        $items = $context->current();
                        $len = count($items);
                    }
                    if ($len) {
                        $chunk->write("<ul>\n");
                        for ($i = 0; $i < $len; $i++) {
                            $chunk = $chunk->write("<li>")->render($bodies->block, $context->push($items[$i]))->write("</li>\n");
                        }
                        return $chunk->write("</ul>");
                    } elseif ($bodies->{'else'}) {
                        return $chunk->render($bodies->{'else'}, $context);
                    }
                    return $chunk;
                },
                "projects" => [
                    (object)["name" => "Mayhem"],
                    (object)["name" => "Flash"],
                    (object)["name" => "Thunder"]
                ]
            ],
            "expected" => "<ul>\n<li>Mayhem</li>\n<li>Flash</li>\n<li>Thunder</li>\n</ul>",
            "message" => "should test the context"
        ];
        $this->runSpecTest($test);
    }
    
    public function testPartial() {
        $test = (object)[
            "name" => "partial",
            "source" => "Hello {name}! You have {count} new messages.",
            "context" => (object)[
                "name" => "Mick",
                "count" => 30
            ],
            "expected" => "Hello Mick! You have 30 new messages.",
            "message" => "should test a basic replace"
        ];
        $this->runSpecTest($test);
    }
    
    public function testPartialWithBlocks() {
        $test = (object)[
            "name" => "partial_with_blocks",
            "source" => "{+header}default header {/header}Hello {name}! You have {count} new messages.",
            "context" => (object)[
                "name" => "Mick",
                "count" => 30
            ],
            "expected" => "default header Hello Mick! You have 30 new messages.",
            "message" => "should test a partial with blocks"
        ];
        $this->runSpecTest($test);
    }
    
    public function testPartialWithBlocksAndNoDefaults() {
        $test = (object)[
            "name" => "partial_with_blocks_and_no_defaults",
            "source" => "{+header/}Hello {name}! You have {count} new messages.",
            "context" => (object)[
                "name" => "Mick",
                "count" => 30
            ],
            "expected" => "Hello Mick! You have 30 new messages.",
            "message" => "should test a partial with blocks and no defaults"
        ];
        $this->runSpecTest($test);
    }
    
    public function testFalseValueInContextIsTreatedAsEmptySameAsUndefined() {
        $test = (object)[
            "name" => "false value in context is treated as empty, same as undefined",
            "source" => "{false}",
            "context" => (object)[
                "false" => false
            ],
            "expected" => "",
            "message" => "should test for false in the context, evaluated and prints nothing"
        ];
        $this->runSpecTest($test);
    }
    
    public function testNumeric0ValueInContextIsTreatedAsNonEmpty() {
        $test = (object)[
            "name" => "numeric 0 value in context is treated as non empty",
            "source" => "{zero}",
            "context" => (object)[
                "zero" => 0
            ],
            "expected" => "0",
            "message" => "should test for numeric zero in the context, prints the numeric zero"
        ];
        $this->runSpecTest($test);
    }
    
    public function testEmptystringContextIsTreatedAsEmpty() {
        $test = (object)[
            "name" => "emptyString context is treated as empty",
            "source" => "{emptyString}",
            "context" => (object)[
                "emptyString" => ""
            ],
            "expected" => "",
            "message" => "should test emptyString, prints nothing"
        ];
        $this->runSpecTest($test);
    }
    
    public function testEmptystringSingleQuotedInContextIsTreatedAsEmpty() {
        $test = (object)[
            "name" => "emptyString, single quoted in context is treated as empty",
            "source" => "{emptyString}",
            "context" => (object)[
                "emptyString" => ""
            ],
            "expected" => "",
            "message" => "should test emptyString single quoted, prints nothing"
        ];
        $this->runSpecTest($test);
    }
    
    public function testNullInTheContextTreatedAsEmpty() {
        $test = (object)[
            "name" => "null in the context treated as empty",
            "source" => "{NULL}",
            "context" => (object)["NULL" => null],
            "expected" => "",
            "message" => "should test null in the context treated as empty"
        ];
        $this->runSpecTest($test);
    }
    
    public function testUndefinedInTheContextTreatedAsEmpty() {
        $test = (object)[
            "name" => "undefined in the context treated as empty",
            "source" => "{UNDEFINED}",
            "context" => (object)[
                "UNDEFINED" => null
            ],
            "expected" => "",
            "message" => "should test undefined in the context treated as empty"
        ];
        $this->runSpecTest($test);
    }
    
    public function testUndefinedStringInTheContextTreatedAsNonEmpty() {
        $test = (object)[
            "name" => "undefined string in the context treated as non empty",
            "source" => "{UNDEFINED}",
            "context" => (object)[
                "UNDEFINED" => "undefined"
            ],
            "expected" => "undefined",
            "message" => "should test string undefined in the context as non empty"
        ];
        $this->runSpecTest($test);
    }
    
    public function testNullIsTreatedAsEmptyInExists() {
        $test = (object)[
            "name" => "null is treated as empty in exists",
            "source" => "{?scalar}true{:else}false{/scalar}",
            "context" => (object)["scalar" => null],
            "expected" => "false",
            "message" => "should test null as empty in exists section"
        ];
        $this->runSpecTest($test);
    }
    
    public function testUndefinedIsTreatedAsEmptyInExists() {
        $test = (object)[
            "name" => "undefined is treated as empty in exists",
            "source" => "{?scalar}true{:else}false{/scalar}",
            "context" => (object)[
                "scalar" => null
            ],
            "expected" => "false",
            "message" => "should test null treated as empty in exists"
        ];
        $this->runSpecTest($test);
    }
    
    public function testNullIsTreatedAsTruthyInNotExists() {
        $test = (object)[
            "name" => "null is treated as truthy in not exists",
            "source" => "{^scalar}true{:else}false{/scalar}",
            "context" => (object)[
                "scalar" => null
            ],
            "expected" => "true",
            "message" => "should test null as truthy in not exists"
        ];
        $this->runSpecTest($test);
    }
    
    public function testUndefinedIsTreatedAsTruthyInNotExists() {
        $test = (object)[
            "name" => "undefined is treated as truthy in not exists",
            "source" => "{^scalar}true{:else}false{/scalar}",
            "context" => (object)[
                "scalar" => null
            ],
            "expected" => "true",
            "message" => "should test undefined as truthy in not exists"
        ];
        $this->runSpecTest($test);
    }
    
    public function testUndefinedIsTreatedAsEmptyInExists2() {
        $test = (object)[
            "name" => "undefined is treated as empty in exists",
            "source" => "{?scalar}true{:else}false{/scalar}",
            "context" => (object)[
                "scalar" => null
            ],
            "expected" => "false",
            "message" => "should test null treated as empty in exists"
        ];
        $this->runSpecTest($test);
    }
    
    public function testDustSyntaxError() {
        $test = (object)[
            "name" => "Dust syntax error",
            "source" => "RRR {##}",
            "context" => (object)["name" => "Mick", "count" => 30],
            "error" => "Expected buffer, comment, partial, reference, section or special but \"{\" found. At line : 1, column : 5",
            "message" => "should test that the error message shows line and column."
        ];
        $this->runSpecTest($test);
    }
    
    public function testScalarNullInASection() {
        $test = (object)[
            "name" => "scalar null in a # section",
            "source" => "{#scalar}true{:else}false{/scalar}",
            "context" => (object)[
                "scalar" => null
            ],
            "expected" => "false",
            "message" => "should test for a scalar null in a # section"
        ];
        $this->runSpecTest($test);
    }
    
    public function testScalarNumeric0InASection() {
        $test = (object)[
            "name" => "scalar numeric 0 in a # section",
            "source" => "{#scalar}true{:else}false{/scalar}",
            "context" => (object)["scalar" => 0],
            "expected" => "true",
            "message" => "should test for a scalar numeric 0 in a # section"
        ];
        $this->runSpecTest($test);
    }
    
    public function testScalarNumericNonzeroInASection() {
        $test = (object)[
            "name" => "scalar numeric non-zero in a # section",
            "source" => "{#scalar}true{:else}false{/scalar}",
            "context" => (object)[
                "scalar" => 42
            ],
            "expected" => "true",
            "message" => "should test for a scalar numeric non-zero in a # section"
        ];
        $this->runSpecTest($test);
    }
    
    public function testScalarNonEmptyStringInASection() {
        $test = (object)[
            "name" => "scalar non empty string in a # section",
            "source" => "{#scalar}true{:else}false{/scalar}",
            "context" => (object)[
                "scalar" => "abcde"
            ],
            "expected" => "true",
            "message" => "should test for a scalar string in a # section"
        ];
        $this->runSpecTest($test);
    }
    
    public function testScalarNonEmptyStringInASection2() {
        $test = (object)[
            "name" => "scalar non empty string in a # section",
            "source" => "{#scalar}{.}{:else}false{/scalar}",
            "context" => (object)[
                "scalar" => "abcde"
            ],
            "expected" => "abcde",
            "message" => "should test for a scalar string in a # section"
        ];
        $this->runSpecTest($test);
    }
    
    public function testMissingScalarValue() {
        $test = (object)[
            "name" => "missing scalar value",
            "source" => "{#scalar}true{:else}false{/scalar}",
            "context" => (object)[
                "foo" => 0
            ],
            "expected" => "false",
            "message" => "should test a missing/undefined scalar value"
        ];
        $this->runSpecTest($test);
    }
    
    public function testScalarTrueValueInTheSection() {
        $test = (object)[
            "name" => "scalar true value in the # section",
            "source" => "{#scalar}true{:else}false{/scalar}",
            "context" => (object)["scalar" => true],
            "expected" => "true",
            "message" => "shoud test for scalar true value in the # section"
        ];
        $this->runSpecTest($test);
    }
    
    public function testScalarFalseValueInTheSection() {
        $test = (object)[
            "name" => "scalar false value in the # section",
            "source" => "{#scalar}true{:else}false{/scalar}",
            "context" => (object)[
                "scalar" => false
            ],
            "expected" => "false",
            "message" => "shoud test for scalar false value in the # section"
        ];
        $this->runSpecTest($test);
    }
    
    public function testScalarValuesTrueAndFalseAreSupportedInNorElseBlocks() {
        $test = (object)[
            "name" => "scalar values true and false are supported in # nor else blocks ",
            "source" => "{#foo}foo,{~s}{:else}not foo,{~s}{/foo}{#bar}bar!{:else}not bar!{/bar}",
            "context" => (object)[
                "foo" => true,
                "bar" => false
            ],
            "expected" => "foo, not bar!",
            "message" => "should test scalar values true and false are supported in # nor else blocks"
        ];
        $this->runSpecTest($test);
    }
    
    public function testEmptyArrayIsTreatedAsEmptyInExists() {
        $test = (object)[
            "name" => "empty array is treated as empty in exists",
            "source" => "{?array}true{:else}false{/array}",
            "context" => (object)[
                "array" => [
                    
                ]
            ],
            "expected" => "false",
            "message" => "empty array is treated as empty in exists"
        ];
        $this->runSpecTest($test);
    }
    
    public function testEmptyIsTreatedAsNonEmptyInExists() {
        $test = (object)[
            "name" => "empty {} is treated as non empty in exists",
            "source" => "{?object}true{:else}false{/object}",
            "context" => (object)[
                "object" => (object)[]
            ],
            "expected" => "true",
            "message" => "empty {} is treated as non empty in exists"
        ];
        $this->runSpecTest($test);
    }
    
    public function testEmptyArrayIsTreatedAsEmptyInASection() {
        $test = (object)[
            "name" => "empty array is treated as empty in a section",
            "source" => "{#array}true{:else}false{/array}",
            "context" => (object)[
                "array" => []
            ],
            "expected" => "false",
            "message" => "empty array is treated as empty in a section"
        ];
        $this->runSpecTest($test);
    }
    
    public function testEmptyIsTreatedAsNonEmptyInASection() {
        $test = (object)[
            "name" => "empty {} is treated as non empty in a section",
            "source" => "{#object}true{:else}false{/object}",
            "context" => (object)[
                "object" => (object)[]
            ],
            "expected" => "true",
            "message" => "empty {} is treated as non empty"
        ];
        $this->runSpecTest($test);
    }
    
    public function testNonemptyArrayInAReference() {
        $test = (object)[
            "name" => "non-empty array in a reference",
            "source" => "{array}",
            "context" => (object)[
                "array" => [
                    "1",
                    "2"
                ]
            ],
            "expected" => "1,2",
            "message" => "non-empty array in a reference"
        ];
        $this->runSpecTest($test);
    }
    
    public function testNullStringInTheContextTreatedAsNonEmpty() {
        $test = (object)[
            "name" => "null string in the context treated as non empty",
            "source" => "{NULL}",
            "context" => (object)[
                "NULL" => "null"
            ],
            "expected" => "null",
            "message" => "should test null string in the context treated as non empty"
        ];
        $this->runSpecTest($test);
    }
    
    public function testString0ValueInContextIsTreatedAsNonEmpty() {
        $test = (object)[
            "name" => "String 0 value in context is treated as non empty",
            "source" => "{zero}",
            "context" => (object)[
                "zero" => "0"
            ],
            "expected" => "0",
            "message" => "should test for string zero in the context, prints zero"
        ];
        $this->runSpecTest($test);
    }
    
    public function testEmptyArray() {
        $test = (object)[
            "name" => "empty_array",
            "source" => "{#names}{title} {name}{~n}{/names}",
            "context" => (object)[
                "title" => "Sir",
                "names" => []
            ],
            "expected" => "",
            "message" => "should test an empty array"
        ];
        $this->runSpecTest($test);
    }
    
    public function testArray() {
        $test = (object)[
            "name" => "array",
            "source" => "{#names}{title} {name}{~n}{/names}",
            "context" => (object)[
                "title" => "Sir",
                "names" => [
                    (object)[
                        "name" => "Moe"
                    ],
                    (object)[
                        "name" => "Larry"
                    ],
                    (object)["name" => "Curly"]
                ]
            ],
            "expected" => "Sir Moe\nSir Larry\nSir Curly\n",
            "message" => "should test an array"
        ];
        $this->runSpecTest($test);
    }
    
    public function testAccessingArrayElementByIndexWhenElementValueIsAPrimitive() {
        $test = (object)[
            "name" => "Accessing array element by index when element value is a primitive",
            "source" => "{do.re[0]}",
            "context" => (object)[
                "do" => (object)[
                    "re" => [
                        "hello!",
                        "bye!"
                    ]
                ]
            ],
            "expected" => "hello!",
            "message" => "should return a specific array element by index when element value is a primitive"
        ];
        $this->runSpecTest($test);
    }
    
    public function testAccessingArrayByIndexWhenElementValueIsAObject() {
        $test = (object)[
            "name" => "Accessing array by index when element value is a object",
            "source" => "{do.re[0].mi}",
            "context" => (object)[
                "do" => (object)[
                    "re" => [
                        (object)["mi" => "hello!"],
                        "bye!"
                    ]
                ]
            ],
            "expected" => "hello!",
            "message" => "should return a specific array element by index when element value is a object"
        ];
        $this->runSpecTest($test);
    }
    
    public function testAccessingArrayByIndexWhenElementIsANestedObject() {
        $test = (object)[
            "name" => "Accessing array by index when element is a nested object",
            "source" => "{do.re[0].mi[1].fa}",
            "context" => (object)[
                "do" => (object)[
                    "re" => [
                        (object)[
                            "mi" => [
                                "one",
                                (object)["fa" => "hello!"]
                            ]
                        ],
                        "bye!"
                    ]
                ]
            ],
            "expected" => "hello!",
            "message" => "should return a specific array element by index when element is a nested object"
        ];
        $this->runSpecTest($test);
    }
    
    public function testAccessingArrayByIndexWhenElementIsListOfPrimitives() {
        $test = (object)[
            "name" => "Accessing array by index when element is list of primitives",
            "source" => "{do[0]}",
            "context" => (object)[
                "do" => [
                    "lala",
                    "lele"
                ]
            ],
            "expected" => "lala",
            "message" => "should return a specific array element by index when element is list of primitives"
        ];
        $this->runSpecTest($test);
    }
    
    public function testAccessingArrayInsideALoopUsingTheCurrentContext() {
        $test = (object)[
            "name" => "Accessing array inside a loop using the current context",
            "source" => "{#list3}{.[0].biz}{/list3}",
            "context" => (object)[
                "list3" => [
                    [
                        (object)[
                            "biz" => "123"
                        ]
                    ],
                    [
                        (object)["biz" => "345"]
                    ]
                ]
            ],
            "expected" => "123345",
            "message" => "should return a specific array element using the current context"
        ];
        $this->runSpecTest($test);
    }
    
    public function testArrayReferenceIdxInIterationOnObjects() {
        $test = (object)[
            "name" => "array: reference \$idx in iteration on objects",
            "source" => "{#names}({\$idx}).{title} {name}{~n}{/names}",
            "context" => (object)[
                "title" => "Sir",
                "names" => [
                    (object)[
                        "name" => "Moe"
                    ],
                    (object)[
                        "name" => "Larry"
                    ],
                    (object)[
                        "name" => "Curly"
                    ]
                ]
            ],
            "expected" => "(0).Sir Moe\n(1).Sir Larry\n(2).Sir Curly\n",
            "message" => "array: reference \$idx in iteration on objects"
        ];
        $this->runSpecTest($test);
    }
    
    public function testArrayReferenceLenInIterationOnObjects() {
        $test = (object)[
            "name" => "array: reference \$len in iteration on objects",
            "source" => "{#names}Size=({\$len}).{title} {name}{~n}{/names}",
            "context" => (object)[
                "title" => "Sir",
                "names" => [
                    (object)[
                        "name" => "Moe"
                    ],
                    (object)[
                        "name" => "Larry"
                    ],
                    (object)["name" => "Curly"]
                ]
            ],
            "expected" => "Size=(3).Sir Moe\nSize=(3).Sir Larry\nSize=(3).Sir Curly\n",
            "message" => "test array: reference \$len in iteration on objects"
        ];
        $this->runSpecTest($test);
    }
    
    public function testArrayReferenceIdxInIterationOnSimpleType() {
        $test = (object)[
            "name" => "array reference \$idx in iteration on simple type",
            "source" => "{#names}({\$idx}).{title} {.}{~n}{/names}",
            "context" => (object)[
                "title" => "Sir",
                "names" => [
                    "Moe",
                    "Larry",
                    "Curly"
                ]
            ],
            "expected" => "(0).Sir Moe\n(1).Sir Larry\n(2).Sir Curly\n",
            "message" => "test array reference \$idx in iteration on simple types"
        ];
        $this->runSpecTest($test);
    }
    
    public function testArrayReferenceLenInIterationOnSimpleType() {
        $test = (object)[
            "name" => "array reference \$len in iteration on simple type",
            "source" => "{#names}Size=({\$len}).{title} {.}{~n}{/names}",
            "context" => (object)[
                "title" => "Sir",
                "names" => [
                    "Moe",
                    "Larry",
                    "Curly"
                ]
            ],
            "expected" => "Size=(3).Sir Moe\nSize=(3).Sir Larry\nSize=(3).Sir Curly\n",
            "message" => "test array reference \$len in iteration on simple types"
        ];
        $this->runSpecTest($test);
    }
    
    public function testArrayReferenceIdxlenOnEmptyArrayCase() {
        $test = (object)[
            "name" => "array reference \$idx/\$len on empty array case",
            "source" => "{#names}Idx={\$idx} Size=({\$len}).{title} {.}{~n}{/names}",
            "context" => (object)[
                "title" => "Sir",
                "names" => [
                    
                ]
            ],
            "expected" => "",
            "message" => "test array reference \$idx/\$len on empty array case"
        ];
        $this->runSpecTest($test);
    }
    
    public function testArrayReferenceIdxlenOnSingleElementCaseScalarCase() {
        $test = (object)[
            "name" => "array reference \$idx/\$len on single element case (scalar case)",
            "source" => "{#name}Idx={\$idx} Size={\$len} {.}{/name}",
            "context" => (object)[
                "name" => "Just one name"
            ],
            "expected" => "Idx= Size= Just one name",
            "message" => "test array reference \$idx/\$len on single element case"
        ];
        $this->runSpecTest($test);
    }
    
    public function testArrayReferenceIdxlenSectionCase() {
        $test = (object)[
            "name" => "array reference \$idx/\$len {#.} section case",
            "source" => "{#names}{#.}{\$idx}{.} {/.}{/names}",
            "context" => (object)[
                "names" => [
                    "Moe",
                    "Larry",
                    "Curly"
                ]
            ],
            "expected" => "0Moe 1Larry 2Curly ",
            "message" => "test array reference \$idx/\$len {#.} section case"
        ];
        $this->runSpecTest($test);
    }
    
    public function testArrayReferenceIdxlenNotChangedInNestedObject() {
        $test = (object)[
            "name" => "array reference \$idx/\$len not changed in nested object",
            "source" => "{#results}{#info}{\$idx}{name}-{\$len} {/info}{/results}",
            "context" => (object)[
                "results" => [
                    (object)[
                        "info" => (object)[
                            "name" => "Steven"
                        ]
                    ],
                    (object)[
                        "info" => (object)[
                            "name" => "Richard"
                        ]
                    ]
                ]
            ],
            "expected" => "0Steven-2 1Richard-2 ",
            "message" => "test array reference \$idx/\$len not changed in nested object"
        ];
        $this->runSpecTest($test);
    }
    
    public function testArrayReferenceIdxlenNestedLoops() {
        $test = (object)[
            "name" => "array reference \$idx/\$len nested loops",
            "source" => "{#A}A loop:{\$idx}-{\$len},{#B}B loop:{\$idx}-{\$len}C[0]={.C[0]} {/B}A loop trailing: {\$idx}-{\$len}{/A}",
            "context" => (object)[
                "A" => [
                    (object)[
                        "B" => [
                            (object)[
                                "C" => [
                                    "Ca1",
                                    "C2"
                                ]
                            ],
                            (object)[
                                "C" => [
                                    "Ca2",
                                    "Ca22"
                                ]
                            ]
                        ]
                    ],
                    (object)[
                        "B" => [
                            (object)[
                                "C" => [
                                    "Cb1",
                                    "C2"
                                ]
                            ],
                            (object)[
                                "C" => [
                                    "Cb2",
                                    "Ca2"
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "expected" => "A loop:0-2,B loop:0-2C[0]=Ca1 B loop:1-2C[0]=Ca2 A loop trailing: 0-2A loop:1-2,B loop:0-2C[0]=Cb1 B loop:1-2C[0]=Cb2 A loop trailing: 1-2",
            "message" => "test array reference \$idx/\$len nested loops"
        ];
        $this->runSpecTest($test);
    }
    
    public function testUsingIdxInArrayReferenceAccessing() {
        $test = (object)[
            "name" => "using idx in array reference Accessing",
            "source" => "{#list4} {name} {number[\$idx]} {\$idx}{/list4}",
            "context" => (object)[
                "list4" => [
                    (object)[
                        "name" => "Dog",
                        "number" => [
                            1,
                            2,
                            3
                        ]
                    ],
                    (object)[
                        "name" => "Cat",
                        "number" => [
                            4,
                            5,
                            6
                        ]
                    ]
                ]
            ],
            "expected" => " Dog 1 0 Cat 5 1",
            "message" => "should test the array reference access with idx"
        ];
        $this->runSpecTest($test);
    }
    
    public function testUsingLenInArrayReferenceAccessing() {
        $test = (object)[
            "name" => "using len in array reference Accessing",
            "source" => "{#list4} {name} {number[\$len]}{/list4}",
            "context" => (object)[
                "list4" => [
                    (object)[
                        "name" => "Dog",
                        "number" => [
                            1,
                            2,
                            3
                        ]
                    ],
                    (object)[
                        "name" => "Cat",
                        "number" => [
                            4,
                            5,
                            6
                        ]
                    ]
                ]
            ],
            "expected" => " Dog 3 Cat 6",
            "message" => "should test the array reference access with len"
        ];
        $this->runSpecTest($test);
    }
    
    public function testUsingIdxInArrayReferenceAccessing2() {
        $test = (object)[
            "name" => "using idx in array reference Accessing",
            "source" => "{#list3}{.[\$idx].biz}{/list3}",
            "context" => (object)[
                "list3" => [
                    [
                        (object)[
                            "biz" => "123"
                        ]
                    ],
                    [
                        (object)[
                            "biz" => "345"
                        ],
                        (object)[
                            "biz" => "456"
                        ]
                    ]
                ]
            ],
            "expected" => "123456",
            "message" => "should test the array reference access with idx and current context"
        ];
        $this->runSpecTest($test);
    }
    
    public function testUsingLenInArrayReferenceAccessing2() {
        $test = (object)[
            "name" => "using len in array reference Accessing",
            "source" => "{#list3}{.[\$len].idx}{/list3}",
            "context" => (object)[
                "list3" => [
                    [
                        (object)[
                            "idx" => "0"
                        ],
                        (object)["idx" => "1"],
                        (object)["idx" => "2"]
                    ],
                    [
                        (object)[
                            "idx" => "0"
                        ],
                        (object)[
                            "idx" => "1"
                        ],
                        (object)["idx" => "2"]
                    ]
                ]
            ],
            "expected" => "22",
            "message" => "should test the array reference access with len and current context"
        ];
        $this->runSpecTest($test);
    }
    
    public function testObject() {
        $test = (object)[
            "name" => "object",
            "source" => "{#person}{root}: {name}, {age}{/person}",
            "context" => (object)[
                "root" => "Subject",
                "person" => (object)[
                    "name" => "Larry",
                    "age" => 45
                ]
            ],
            "expected" => "Subject: Larry, 45",
            "message" => "should test an object"
        ];
        $this->runSpecTest($test);
    }
    
    public function testPath() {
        $test = (object)[
            "name" => "path",
            "source" => "{foo.bar}",
            "context" => (object)["foo" => (object)["bar" => "Hello!"]],
            "expected" => "Hello!",
            "message" => "should test an object path"
        ];
        $this->runSpecTest($test);
    }
    
    public function testConditional() {
        $test = (object)[
            "name" => "conditional",
            "source" => "{?tags}<ul>{~n}{#tags}{~s} <li>{.}</li>{~n}{/tags}</ul>{:else}No Tags!{/tags}{~n}{^likes}No Likes!{:else}<ul>{~n}{#likes}{~s} <li>{.}</li>{~n}{/likes}</ul>{/likes}",
            "context" => (object)[
                "tags" => [
                    
                ],
                "likes" => [
                    "moe",
                    "larry",
                    "curly",
                    "shemp"
                ]
            ],
            "expected" => "No Tags!\n<ul>\n  <li>moe</li>\n  <li>larry</li>\n  <li>curly</li>\n  <li>shemp</li>\n</ul>",
            "message" => "should test conditional tags"
        ];
        $this->runSpecTest($test);
    }
    
    public function testEmptyElseBlock() {
        $test = (object)[
            "name" => "empty_else_block",
            "source" => "{#foo}full foo{:else}empty foo{/foo}",
            "context" => (object)[
                "foo" => [
                    
                ]
            ],
            "expected" => "empty foo",
            "message" => "should test else block when array empty"
        ];
        $this->runSpecTest($test);
    }
    
    public function testFilter() {
        $test = (object)[
            "name" => "filter",
            "source" => "{#filter}foo {bar}{/filter}",
            "context" => (object)[
                "filter" => function ($chunk, $context, $bodies) {
                    return $chunk->tap(function ($data) {
                        return strtoupper($data);
                    })->render($bodies->block, $context)->untap();
                },
                "bar" => "bar"
            ],
            "expected" => "FOO BAR",
            "message" => "should test the filter tag"
        ];
        $this->runSpecTest($test);
    }
    
    public function testInvalidFilter() {
        $test = (object)[
            "name" => "Invalid filter",
            "source" => "{obj|nullcheck|invalid}",
            "context" => (object)["obj" => "test"],
            "expected" => "test",
            "message" => "should fail gracefully for invalid filter"
        ];
        $this->runSpecTest($test);
    }
    
    public function testJsonstringifyFilter() {
        $test = (object)[
            "name" => "JSON.stringify filter",
            "source" => "{obj|js|s}",
            "context" => (object)[
                "obj" => (object)[
                    "id" => 1,
                    "name" => "bob",
                    "occupation" => "construction"
                ]
            ],
            "expected" => "{\"id\":1,\"name\":\"bob\",\"occupation\":\"construction\"}",
            "message" => "should stringify a JSON literal when using the js filter"
        ];
        $this->runSpecTest($test);
    }
    
    /**
     * @depends testPartial
     * @depends testHelloWorld
     */
    public function testPartials() {
        $test = (object)[
            "name" => "partials",
            "source" => "{>partial/} {>\"hello_world\"/} {>\"{ref}\"/}",
            "context" => (object)[
                "name" => "Jim",
                "count" => 42,
                "ref" => "hello_world"
            ],
            "expected" => "Hello Jim! You have 42 new messages. Hello World! Hello World!",
            "message" => "should test partials"
        ];
        $this->runSpecTest($test);
    }
    
    /**
     * @depends testPartial
     */
    public function testPartialWithContext() {
        $test = (object)[
            "name" => "partial with context",
            "source" => "{>partial:.profile/}",
            "context" => (object)[
                "profile" => (object)[
                    "name" => "Mick",
                    "count" => 30
                ]
            ],
            "expected" => "Hello Mick! You have 30 new messages.",
            "message" => "should test partial with context"
        ];
        $this->runSpecTest($test);
    }
    
    /**
     * @depends testPartialWithBlocksAndNoDefaults
     */
    public function testPartialWithBlocksWithNoDefaultValuesForBlocks() {
        $test = (object)[
            "name" => "partial with blocks, with no default values for blocks",
            "source" => "{>partial_with_blocks_and_no_defaults/}",
            "context" => (object)[
                "name" => "Mick",
                "count" => 30
            ],
            "expected" => "Hello Mick! You have 30 new messages.",
            "message" => "partial with blocks, with no default values for blocks"
        ];
        $this->runSpecTest($test);
    }
    
    /**
     * @depends testPartialWithBlocksAndNoDefaults
     */
    public function testPartialWithBlocksWithNoDefaultValuesForBlocksButOverrideDefaultValuesWithInlinePartials() {
        $test = (object)[
            "name" => "partial with blocks, with no default values for blocks, but override default values with inline partials",
            "source" => "{>partial_with_blocks_and_no_defaults/}{<header}override header {/header}",
            "context" => (object)[
                "name" => "Mick",
                "count" => 30
            ],
            "expected" => "override header Hello Mick! You have 30 new messages.",
            "message" => "partial with blocks, with no default values for blocks, but override default values with inline partials"
        ];
        $this->runSpecTest($test);
    }
    
    /**
     * @depends testPartialWithBlocks
     */
    public function testPartialWithBlocksOverrideDefaultValuesWithInlinePartials() {
        $test = (object)[
            "name" => "partial with blocks, override default values with inline partials",
            "source" => "{>partial_with_blocks/}{<header}my header {/header}",
            "context" => (object)[
                "name" => "Mick",
                "count" => 30
            ],
            "expected" => "my header Hello Mick! You have 30 new messages.",
            "message" => "partial with blocks, override default values with inline partials"
        ];
        $this->runSpecTest($test);
    }
    
    public function testPartialWithInlineParams() {
        $test = (object)[
            "name" => "partial with inline params",
            "source" => "{>partial name=n count=\"{c}\"/}",
            "context" => (object)[
                "n" => "Mick",
                "c" => 30
            ],
            "expected" => "Hello Mick! You have 30 new messages.",
            "message" => "should test partial with inline params"
        ];
        $this->runSpecTest($test);
    }
    
    public function testPartialWithInlineParamsTreeWalkUp() {
        $test = (object)[
            "name" => "partial with inline params tree walk up",
            "source" => "{#a}{#b}{#c}{#d}{>partial name=n count=\"{x}\"/}{/d}{/c}{/b}{/a}",
            "context" => (object)[
                "n" => "Mick",
                "x" => 30,
                "a" => (object)[
                    "b" => (object)[
                        "c" => (object)[
                            "d" => (object)[
                                "e" => "1"
                            ]
                        ]
                    ]
                ]
            ],
            "expected" => "Hello Mick! You have 30 new messages.",
            "message" => "should test partial with inline params tree walk up"
        ];
        $this->runSpecTest($test);
    }
    
    /**
     * @depends testPartial
     */
    public function testPartialWithInlineParamsAndContext() {
        $test = (object)[
            "name" => "partial with inline params and context",
            "source" => "{>partial:profile name=\"{n}\" count=\"{c}\"/}",
            "context" => (object)[
                "profile" => (object)[
                    "n" => "Mick",
                    "c" => 30
                ]
            ],
            "expected" => "Hello Mick! You have 30 new messages.",
            "message" => "should test partial with inline params and context"
        ];
        $this->runSpecTest($test);
    }
    
    /**
     * @depends testPartial
     */
    public function testPartialWithInlineParamsAndContextTreeWalkUp() {
        $test = (object)[
            "name" => "partial with inline params and context tree walk up",
            "source" => "{#profile}{#a}{#b}{#c}{#d}{>partial:profile name=n count=\"{x}\"/}{/d}{/c}{/b}{/a}{/profile}",
            "context" => (object)[
                "profile" => (object)[
                    "n" => "Mick",
                    "x" => 30,
                    "a" => (object)[
                        "b" => (object)[
                            "c" => (object)[
                                "d" => (object)[
                                    "e" => "1"
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "expected" => "Hello Mick! You have 30 new messages.",
            "message" => "should test partial with inline params and context tree walk up"
        ];
        $this->runSpecTest($test);
    }
    
    /**
     * @depends testPartial
     */
    public function testPartialWithLiteralInlineParamAndContext() {
        $test = (object)[
            "name" => "partial with literal inline param and context",
            "source" => "{>partial:profile name=\"Joe\" count=\"99\"/}",
            "context" => (object)[
                "profile" => (object)[
                    "n" => "Mick",
                    "count" => 30
                ]
            ],
            "expected" => "Hello Joe! You have 30 new messages.",
            "message" => "should test partial with literal inline param and context. Fallback values for name or count are undefined"
        ];
        $this->runSpecTest($test);
    }
    
    public function testPartialWithBlocksAndInlineParams() {
        $test = (object)[
            "name" => "partial with blocks and inline params",
            "source" => "{>partial_with_blocks name=n count=\"{c}\"/}",
            "context" => (object)[
                "n" => "Mick",
                "c" => 30
            ],
            "expected" => "default header Hello Mick! You have 30 new messages.",
            "message" => "should test partial with blocks and inline params"
        ];
        $this->runSpecTest($test);
    }
    
    public function testPartialWithBlocksOverrideDefaultValuesForBlocksAndInlineParams() {
        $test = (object)[
            "name" => "partial with blocks, override default values for blocks and inline params",
            "source" => "{>partial_with_blocks name=n count=\"{c}\"/}{<header}my header {/header}",
            "context" => (object)[
                "n" => "Mick",
                "c" => 30
            ],
            "expected" => "my header Hello Mick! You have 30 new messages.",
            "message" => "should test partial with blocks, override default values for blocks and inline params"
        ];
        $this->runSpecTest($test);
    }
    
    public function testPartialWithBlocksAndNoDefaultsOverrideDefaultValuesForBlocksAndInlineParams() {
        $test = (object)[
            "name" => "partial with blocks and no defaults, override default values for blocks and inline params",
            "source" => "{>partial_with_blocks_and_no_defaults name=n count=\"{c}\"/}{<header}my header {/header}",
            "context" => (object)[
                "n" => "Mick",
                "c" => 30
            ],
            "expected" => "my header Hello Mick! You have 30 new messages.",
            "message" => "should test partial blocks and no defaults, override default values for blocks and inline params"
        ];
        $this->runSpecTest($test);
    }
    
    public function testPartialWithNoBlocksIgnoreTheOverrideInlinePartials() {
        $test = (object)[
            "name" => "partial with no blocks, ignore the override inline partials",
            "source" => "{>partial name=n count=\"{c}\"/}{<header}my header {/header}",
            "context" => (object)[
                "n" => "Mick",
                "c" => 30
            ],
            "expected" => "Hello Mick! You have 30 new messages.",
            "message" => "should test partial with no blocks, ignore the override inline partials"
        ];
        $this->runSpecTest($test);
    }
    
    public function testIgnoreExtraWhitespacesBetweenOpeningBracePlusAnyOfAndTheTagIdentifier() {
        $test = (object)[
            "name" => "ignore extra whitespaces between opening brace plus any of (#,?,@,^,+,%) and the tag identifier",
            "source" => "{# helper foo=\"bar\" boo=\"boo\" } {/helper}",
            "context" => (object)[
                "helper" => function ($chunk, $context, $bodies, $params) {
                    return $chunk->write($params->boo . " " . $params->foo);
                }
            ],
            "expected" => "boo bar",
            "message" => "should ignore extra whitespaces between opening brace plus any of (#,?,@,^,+,%) and the tag identifier"
        ];
        $this->runSpecTest($test);
    }
    
    public function testErrorWhitespacesBetweenTheOpeningBraceAndAnyOfIsNotAllowed() {
        $test = (object)[
            "name" => "error: whitespaces between the opening brace and any of (#,?,@,^,+,%) is not allowed",
            "source" => "{ # helper foo=\"bar\" boo=\"boo\" } {/helper}",
            "context" => (object)[
                "helper" => function ($chunk, $context, $bodies, $params) {
                    return $chunk->write($params->boo . " " . $params->foo);
                }
            ],
            "error" => "Expected buffer, comment, partial, reference, section or special but \"{\" found. At line : 1, column : 1",
            "message" => "should show an error for whitespces between the opening brace and any of (#,?,@,^,+,%)"
        ];
        $this->runSpecTest($test);
    }
    
    public function testWhitespacesBetweenTheClosingBracePlusSlashAndTheTagIdentifierIsSupported() {
        $test = (object)[
            "name" => "whitespaces between the closing brace plus slash and the tag identifier is supported",
            "source" => "{# helper foo=\"bar\" boo=\"boo\"} {/ helper }",
            "context" => (object)[
                "helper" => function ($chunk, $context, $bodies, $params) {
                    return $chunk->write($params->boo . " " . $params->foo);
                }
            ],
            "expected" => "boo bar",
            "message" => "should ignore extra whitespaces between the closing brace plus slash and the tag identifier"
        ];
        $this->runSpecTest($test);
    }
    
    public function testErrorWhitespacesBetweenTheOpenningCurlyBraceAndForwardSlashInTheClosingTagsNotSupported() {
        $test = (object)[
            "name" => "error: whitespaces between the openning curly brace and forward slash in the closing tags not supported",
            "source" => "{# helper foo=\"bar\" boo=\"boo\"} { / helper }",
            "context" => (object)[
                "helper" => function ($chunk, $context, $bodies, $params) {
                    return $chunk->write($params->boo . " " . $params->foo);
                }
            ],
            "error" => "Expected buffer, comment, partial, reference, section or special but \"{\" found. At line : 1, column : 1",
            "message" => "should show an error because whitespaces between the '{' and the forward slash are not allowed in the closing tags"
        ];
        $this->runSpecTest($test);
    }
    
    public function testWhitespacesBeforeTheSelfClosingTagsIsAllowed() {
        $test = (object)[
            "name" => "whitespaces before the self closing tags is allowed",
            "source" => "{#helper foo=\"bar\" boo=\"boo\" /}",
            "context" => (object)[
                "helper" => function ($chunk, $context, $bodies, $params) {
                    return $chunk->write($params->boo . " " . $params->foo);
                }
            ],
            "expected" => "boo bar",
            "message" => "should ignore extra whitespaces before the self closing tags"
        ];
        $this->runSpecTest($test);
    }
    
    public function testErrorWhitespacesBetweenTheForwardSlashAndTheClosingBraceInSelfClosingTags() {
        $test = (object)[
            "name" => "error: whitespaces between the forward slash and the closing brace in self closing tags",
            "source" => "{#helper foo=\"bar\" boo=\"boo\" / }",
            "context" => (object)[
                "helper" => function ($chunk, $context, $bodies, $params) {
                    return $chunk->write($params->boo . " " . $params->foo);
                }
            ],
            "error" => "Expected buffer, comment, partial, reference, section or special but \"{\" found. At line : 1, column : 1",
            "message" => "should show an error for whitespaces  etween the forward slash and the closing brace in self closing tags"
        ];
        $this->runSpecTest($test);
    }
    
    public function testExtraWhitespacesBetweenInlineParamsSupported() {
        $test = (object)[
            "name" => "extra whitespaces between inline params supported",
            "source" => "{#helper foo=\"bar\"   boo=\"boo\"/}",
            "context" => (object)[
                "helper" => function ($chunk, $context, $bodies, $params) {
                    return $chunk->write($params->boo . " " . $params->foo);
                }
            ],
            "expected" => "boo bar",
            "message" => "should ignore extra whitespaces between inline params"
        ];
        $this->runSpecTest($test);
    }
    
    /**
     * @depends testHelloWorld
     */
    public function testErrorWhitespacesBetweenThePlusAndPartialIdentifierIsNotSupported() {
        $test = (object)[
            "name" => "error : whitespaces between the '{' plus '>' and partial identifier is not supported",
            "source" => "{ > partial/} {> \"hello_world\"/} {> \"{ref}\"/}",
            "context" => (object)[
                "name" => "Jim",
                "count" => 42,
                "ref" => "hello_world"
            ],
            "error" => "Expected buffer, comment, partial, reference, section or special but \"{\" found. At line : 1, column : 1",
            "message" => "should show an error for whitespaces between the '{' plus '>' and partial identifier"
        ];
        $this->runSpecTest($test);
    }
    
    /**
     * @depends testPartial
     * @depends testHelloWorld
     */
    public function testWhitespacesBeforeTheForwardSlashAndTheClosingBraceInPartialsSupported() {
        $test = (object)[
            "name" => "whitespaces before the forward slash and the closing brace in partials supported",
            "source" => "{>partial /} {>\"hello_world\" /} {>\"{ref}\" /}",
            "context" => (object)[
                "name" => "Jim",
                "count" => 42,
                "ref" => "hello_world"
            ],
            "expected" => "Hello Jim! You have 42 new messages. Hello World! Hello World!",
            "message" => "should ignore extra whitespacesbefore the forward slash and the closing brace in partials"
        ];
        $this->runSpecTest($test);
    }
    
    public function testIgnoreWhitespacesAlsoMeansIgnoringEol() {
        $test = (object)[
            "name" => "ignore whitespaces also means ignoring eol",
            "source" => "{#authors \nname=\"theAuthors\"\nlastname=\"authorlastname\" \nmaxtext=300}\n{>\"otherTemplate\"/}\n{/authors}",
            "context" => (object)[],
            "expected" => "",
            "message" => "should ignore eol"
        ];
        $this->runSpecTest($test);
    }
    
    public function testIgnoreCarriageReturnOrTabInInlineParamValues() {
        $test = (object)[
            "name" => "ignore carriage return or tab in inline param values",
            "source" => "{#helper name=\"Dialog\" config=\"{\n'name' : 'index' }\n \"} {/helper}",
            "context" => (object)[],
            "expected" => "",
            "message" => "should ignore carriage return or tab in inline param values"
        ];
        $this->runSpecTest($test);
    }
    
    public function testParams() {
        $test = (object)[
            "name" => "params",
            "source" => "{#helper foo=\"bar\"/}",
            "context" => (object)[
                "helper" => function ($chunk, $context, $bodies, $params) {
                    return $chunk->write($params->foo);
                }
            ],
            "expected" => "bar",
            "message" => "should test inner params"
        ];
        $this->runSpecTest($test);
    }
    
    public function testInlineParamsAsInteger() {
        $test = (object)[
            "name" => "inline params as integer",
            "source" => "{#helper foo=10 /}",
            "context" => (object)[
                "helper" => function ($chunk, $context, $bodies, $params) {
                    return $chunk->write($params->foo);
                }
            ],
            "expected" => "10",
            "message" => "Block handlers syntax should support integer number parameters"
        ];
        $this->runSpecTest($test);
    }
    
    public function testInlineParamsAsFloat() {
        $test = (object)[
            "name" => "inline params as float",
            "source" => "{#helper foo=3.14159 /}",
            "context" => (object)[
                "helper" => function ($chunk, $context, $bodies, $params) {
                    return $chunk->write($params->foo);
                }
            ],
            "expected" => "3.14159",
            "message" => "Block handlers syntax should support decimal number parameters"
        ];
        $this->runSpecTest($test);
    }
    
    public function testBlocksWithDynamicKeys() {
        $test = (object)[
            "name" => "blocks with dynamic keys",
            "source" => "{<title_A}\nAAA\n{/title_A}\n{<title_B}\nBBB\n{/title_B}\n{+\"title_{val}\"/}",
            "context" => (object)[
                "val" => "A"
            ],
            "expected" => "AAA\n",
            "message" => "should test blocks with dynamic keys"
        ];
        $this->runSpecTest($test);
    }
    
    public function testBlocksWithMoreThanOneDynamicKeys() {
        $test = (object)[
            "name" => "blocks with more than one dynamic keys",
            "source" => "{<title_A}\nAAA\n{/title_A}\n{<title_B}\nBBB\n{/title_B}\n{+\"{val1}_{val2}\"/}",
            "context" => (object)[
                "val1" => "title",
                "val2" => "A"
            ],
            "expected" => "AAA\n",
            "message" => "should test blocks with more than one dynamic keys"
        ];
        $this->runSpecTest($test);
    }
    
    public function testBlocksWithDynamicKeyValuesAsObjects() {
        $test = (object)[
            "name" => "blocks with dynamic key values as objects",
            "source" => "{<title_A}\nAAA\n{/title_A}\n{<title_B}\nBBB\n{/title_B}\n{+\"{val1}_{obj.name}\"/}",
            "context" => (object)[
                "val1" => "title",
                "val2" => "A",
                "obj" => (object)["name" => "B"]
            ],
            "expected" => "BBB\n",
            "message" => "should test blocks with dynamic key values as objects"
        ];
        $this->runSpecTest($test);
    }
    
    public function testBlocksWithDynamicKeyValuesAsArrays() {
        $test = (object)[
            "name" => "blocks with dynamic key values as arrays",
            "source" => "{<title_A}\nAAA\n{/title_A}\n{<title_B}\nBBB\n{/title_B}\n{+\"{val1}_{obj.name[0]}\"/}",
            "context" => (object)[
                "val1" => "title",
                "val2" => "A",
                "obj" => (object)[
                    "name" => [
                        "A",
                        "B"
                    ]
                ]
            ],
            "expected" => "AAA\n",
            "message" => "should test blocks with dynamic key values as arrays"
        ];
        $this->runSpecTest($test);
    }
    
    public function testTestThatTheScopeOfTheFunctionIsCorrectAndThatANonchunkReturnValueIsUsedForTruthinessChecks() {
        $test = (object)[
            "name" => "test that the scope of the function is correct and that a non-chunk return value is used for truthiness checks",
            "source" => "Hello {#foo}{#bar}{.}{/bar}{/foo} World!",
            "context" => (object)[
                "foo" => (object)[
                    "foobar" => "Foo Bar",
                    "bar" => function () {
                        return $this->foobar;
                    }
                ]
            ],
            "expected" => "Hello Foo Bar World!",
            "message" => "should test scope of context function"
        ];
        $this->runSpecTest($test);
    }
    
    public function testTestThatFunctionThatDoNotReturnChunkAndReturnFalsyAreTreatedAsFalsy() {
        $test = (object)[
            "name" => "test that function that do not return chunk and return falsy are treated as falsy",
            "source" => "{#bar}{.}{:else}false{/bar}",
            "context" => (object)[
                "bar" => function () {
                    return false;
                }
            ],
            "expected" => "false",
            "message" => "should functions that return false are falsy"
        ];
        $this->runSpecTest($test);
    }
    
    public function testTestThatFunctionThatDoNotReturnChunkAndReturn0AreTreatedAsTruthyInTheDustSense() {
        $test = (object)[
            "name" => "test that function that do not return chunk and return 0 are treated as truthy (in the Dust sense)",
            "source" => "{#bar}{.}{:else}false{/bar}",
            "context" => (object)[
                "bar" => function () {
                    return 0;
                }
            ],
            "expected" => "0",
            "message" => "should functions that return 0 are truthy"
        ];
        $this->runSpecTest($test);
    }
    
    public function testTestThatTheScopeOfTheFunctionIsCorrect() {
        $test = (object)[
            "name" => "test that the scope of the function is correct",
            "source" => "Hello {#foo}{bar}{/foo} World!",
            "context" => (object)[
                "foo" => (object)[
                    "foobar" => "Foo Bar",
                    "bar" => function () {
                        return $this->foobar;
                    }
                ]
            ],
            "expected" => "Hello Foo Bar World!",
            "message" => "should test scope of context function"
        ];
        $this->runSpecTest($test);
    }
    
    public function testSupportDashInKeyreference() {
        $test = (object)[
            "name" => "support dash in key/reference",
            "source" => "Hello {first-name}, {last-name}! You have {count} new messages.",
            "context" => (object)[
                "first-name" => "Mick",
                "last-name" => "Jagger",
                "count" => 30
            ],
            "expected" => "Hello Mick, Jagger! You have 30 new messages.",
            "message" => "should test using dash in key/reference"
        ];
        $this->runSpecTest($test);
    }
    
    public function testSupportDashInPartialsKey() {
        $test = (object)[
            "name" => "support dash in partial's key",
            "source" => "{<title-a}foo-bar{/title-a}{+\"{foo-title}-{bar-letter}\"/}",
            "context" => (object)[
                "foo-title" => "title",
                "bar-letter" => "a"
            ],
            "expected" => "foo-bar",
            "message" => "should test dash in partial's keys"
        ];
        $this->runSpecTest($test);
    }
    
    public function testSupportDashInPartialsParams() {
        $test = (object)[
            "name" => "support dash in partial's params",
            "source" => "{>partial name=first-name count=\"{c}\"/}",
            "context" => (object)[
                "first-name" => "Mick",
                "c" => 30
            ],
            "expected" => "Hello Mick! You have 30 new messages.",
            "message" => "should test dash in partial's params"
        ];
        $this->runSpecTest($test);
    }
    
    public function testSupportDashInSections() {
        $test = (object)[
            "name" => "support dash in # sections",
            "source" => "{#first-names}{name}{/first-names}",
            "context" => (object)[
                "first-names" => [
                    (object)[
                        "name" => "Moe"
                    ],
                    (object)["name" => "Larry"],
                    (object)[
                        "name" => "Curly"
                    ]
                ]
            ],
            "expected" => "MoeLarryCurly",
            "message" => "should test dash in # sections"
        ];
        $this->runSpecTest($test);
    }
    
    public function testSupportDashInARefereceForExistsSection() {
        $test = (object)[
            "name" => "support dash in a referece for exists section",
            "source" => "{?tags-a}tag found!{:else}No Tags!{/tags-a}",
            "context" => (object)["tags-a" => "tag"],
            "expected" => "tag found!",
            "message" => "should test for dash in a referece for exists section"
        ];
        $this->runSpecTest($test);
    }
    
    public function testBaseTemplateWithDashInTheReference() {
        $test = (object)[
            "name" => "base_template with dash in the reference",
            "source" => "Start{~n}{+title-t}Template Title{/title-t}{~n}{+main-t}Template Content{/main-t}{~n}End",
            "context" => (object)[],
            "expected" => "Start\nTemplate Title\nTemplate Content\nEnd",
            "message" => "should test base template with dash in the reference"
        ];
        $this->runSpecTest($test);
    }
    
    public function testChildTemplateWithDashInTheReference() {
        $test = (object)[
            "name" => "child_template with dash in the reference",
            "source" => "{^xhr-n}tag not found!{:else}tag found!{/xhr-n}",
            "context" => (object)["xhr" => false],
            "expected" => "tag not found!",
            "message" => "should test child template with dash"
        ];
        $this->runSpecTest($test);
    }
    
    public function testBuffer() {
        $test = (object)[
            "name" => "buffer ",
            "source" => "{&partial/}",
            "context" => (object)[],
            "expected" => "{&partial/}",
            "message" => "This content {&partial/} should be parsed as buffer"
        ];
        $this->runSpecTest($test);
    }
    
    public function testBufferDoesNotIgnoreWs() {
        $test = (object)[
            "name" => "buffer does not ignore ws",
            "source" => "Hi {name}:   you won a blue car",
            "context" => (object)[
                "name" => "Jairo"
            ],
            "expected" => "Hi Jairo:   you won a blue car",
            "message" => "Buffer should not ignore ws"
        ];
        $this->runSpecTest($test);
    }
    
    public function testBufferDoesNotIgnoreLineFeed() {
        $test = (object)[
            "name" => "buffer: does not ignore line feed",
            "source" => "Hi {name}:\n   you won a blue car",
            "context" => (object)["name" => "Jairo"],
            "expected" => "Hi Jairo:\n   you won a blue car",
            "message" => "Buffer should not ignore line feed"
        ];
        $this->runSpecTest($test);
    }
    
    public function testBufferDoesNotIgnoreLineFeedAndCarriage() {
        $test = (object)[
            "name" => "buffer: does not ignore line feed and carriage",
            "source" => "Hi {name}:\r\n   you won a blue car",
            "context" => (object)[
                "name" => "Jairo"
            ],
            "expected" => "Hi Jairo:\r\n   you won a blue car",
            "message" => "Buffer should not ignore line feed and carriage"
        ];
        $this->runSpecTest($test);
    }
    
    public function testBufferDoesNotIgnoreCarriageReturn() {
        $test = (object)[
            "name" => "buffer: does not ignore carriage return",
            "source" => "Hi {name}:\r   you won a blue car",
            "context" => (object)[
                "name" => "Jairo"
            ],
            "expected" => "Hi Jairo:\r   you won a blue car",
            "message" => "Buffer should not ignore carriage return"
        ];
        $this->runSpecTest($test);
    }
    
    
}