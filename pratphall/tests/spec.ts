///<reference path="common.ts" />

module Dust {

    interface SpecTestObject extends Pct.CompileTimeOnly {
        name: string;
        source: string;
        context: any;
        expected?: string;
        error?: string;
        message: string;
    }

    class SpecTest extends PHPUnit.Framework.TestCase {

        private static dust: Dust;

        public static setUpBeforeClass() {
            SpecTest.dust = new Dust();
        }

        public static tearDownAfterClass() {
            SpecTest.dust = null;
        }

        private runSpecTest(test: SpecTestObject) {
            //error means expected exception
            if (isset(test.error)) this.setExpectedException(Pct.typeName(DustException));
            //create parser
            var compiled = SpecTest.dust.compile(test.source, test.name);
            var evald = SpecTest.dust.renderTemplate(compiled, test.context);
            this.assertEquals(trim(test.expected), trim(evald));
        }

        __emitCoreSpecTests() { }
    }
}