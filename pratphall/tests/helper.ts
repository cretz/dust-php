///<reference path="common.ts" />

module Dust.Helper {

    class SelectTest extends DustTestBase {
        testSelect() {
            var templ = '{@select key="{value}"}{@eq value=5}five{/eq}{@eq value=6}six{/eq}{@default}def{/default}{/select}';
            this.assertTemplate('five', templ, { value: 5 });
            this.assertTemplate('six', templ, { value: 6 });
            this.assertTemplate('def', templ, { value: 'yourmom' });
        }
    }

    class MathTest extends DustTestBase {
        testMath() {
            //from manual mostly
            this.assertTemplate('20', '{@math key="16" method="add" operand="4"/}', { });
            this.assertTemplate('16', '{@math key="16.5" method="floor"/}', { });
            this.assertTemplate('17', '{@math key="16.5" method="ceil"/}', { });
            this.assertTemplate('8', '{@math key="-8" method="abs"/}', { });
            this.assertTemplate('010101', '{#items}{@math key="{$idx}" method="mod" operand="2"/}{/items}', { items: [1, 2, 3, 4, 5, 6] });
        }
    }

    class ComparisonTestBase extends DustTestBase {
        constructor(private name: string) { super(); }

        assertValidResult(key: any, value: any, expectsValid: bool) {
            this.assertTemplate(expectsValid ? 'yes' : 'no',
                '{@' + this.name + ' key=one value=two}yes{:else}no{/' + this.name + '}',
                { one: key, two: value });
        }
    }

    class EqTest extends ComparisonTestBase {
        constructor() { super('eq'); }

        testEq() {
            this.assertValidResult(12, 12, true);
            this.assertValidResult(12, '12', true);
            this.assertValidResult(12, false, false);
            this.assertValidResult([1, 2], [1, 2], true);
            this.assertValidResult('FOO', 'foo', false);
        }
    }

    class LtTest extends ComparisonTestBase {
        constructor() { super('lt'); }

        testLt() {
            this.assertValidResult(12, 12, false);
            this.assertValidResult(12, '12', false);
            this.assertValidResult(12, 15, true);
            this.assertValidResult(15, 12, false);
            this.assertValidResult([1, 2], [3, 4], true);
            this.assertValidResult('foo', 'bar', false);
            this.assertValidResult('bar', 'foo', true);
        }
    }

    class LteTest extends ComparisonTestBase {
        constructor() { super('lte'); }

        testLte() {
            this.assertValidResult(12, 12, true);
            this.assertValidResult(12, '12', true);
            this.assertValidResult(12, 15, true);
            this.assertValidResult(15, 12, false);
            this.assertValidResult([1, 2], [3, 4], true);
            this.assertValidResult('foo', 'bar', false);
            this.assertValidResult('bar', 'foo', true);
            this.assertValidResult('foo', 'foo', true);
        }
    }

    class GtTest extends ComparisonTestBase {
        constructor() { super('gt'); }

        testGt() {
            this.assertValidResult(12, 12, false);
            this.assertValidResult(12, '12', false);
            this.assertValidResult(12, 15, false);
            this.assertValidResult(15, 12, true);
            this.assertValidResult([1, 2], [3, 4], false);
            this.assertValidResult('foo', 'bar', true);
            this.assertValidResult('bar', 'foo', false);
        }
    }

    class GteTest extends ComparisonTestBase {
        constructor() { super('gte'); }

        testGte() {
            this.assertValidResult(12, 12, true);
            this.assertValidResult(12, '12', true);
            this.assertValidResult(12, 15, false);
            this.assertValidResult(15, 12, true);
            this.assertValidResult([1, 2], [3, 4], false);
            this.assertValidResult('foo', 'bar', true);
            this.assertValidResult('bar', 'foo', false);
            this.assertValidResult('foo', 'foo', true);
        }
    }

    class IfHelperTest extends DustTestBase {
        testIfHelper() {
            this.dust.helpers['if'] = new Helper.IfHelper();
            var templ = '{@if cond="{x} < {y} || {x} < 3"}yes{:else}no{/if}'
            this.assertTemplate('yes', templ, { x: 12, y: 15 });
            this.assertTemplate('yes', templ, { x: 2, y: 1 });
            this.assertTemplate('no', templ, { x: 7, y: 5 });
        }
    }

    class SepTest extends DustTestBase {
        testSep() {
            this.assertTemplate('1, 2, 3, 4', '{#items}{.}{@sep}, {/sep}{/items}',
                { items: [1, 2, 3, 4] });
        }
    }

    class SizeTest extends DustTestBase {
        testSize() {
            var templ = '{@size key=val /}';
            this.assertTemplate('4', templ, { val: [1, 2, 3, 4] });
            this.assertTemplate('6', templ, { val: 'abcdef' });
            this.assertTemplate('2', templ, { val: { foo: 12, bar: 15 } });
            this.assertTemplate('23', templ, { val: 23 });
            this.assertTemplate('3.14', templ, { val: 3.14 });
            this.assertTemplate('0', templ, { });
            this.assertTemplate('0', templ, { val: '' });
            this.assertTemplate('1', templ, { val: true });
            this.assertTemplate('0', templ, { val: false });
        }
    }


    class ContextDumpTest extends DustTestBase {
        testContextDump() {
            var ctx = {
                outer: {
                    meh: 'meh',
                    inner: {
                        key: 'current',
                        output: 'output'
                    }
                }
            }
            var templ = '{#outer outerFoo="bar"}{#inner:meh innerFoo="bar"}{@contextDump key=key to=output /}{/inner}{/outer}';
            //I really don't care what the output is, I just wanna run it
            this.dust.renderTemplate(this.dust.compile(templ), ctx);
            ctx.outer.inner.key = 'full';
            this.dust.renderTemplate(this.dust.compile(templ), ctx);
        }
    }
}