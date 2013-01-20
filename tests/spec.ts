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

        private runSpecTest(test: SpecTestObject) {
            //error means expected exception
            if (isset(test.error)) this.setExpectedException(Pct.typeName(DustException));
            //parse
            var parser = new Parse.Parser();
            var parsed = parser.parse(test.source);
            //now eval
            var evaluator = new Evaluate.Evaluator();
            var evald = evaluator.evaluate(parsed, test.context);
            this.assertEquals(test.expected, evald);
        }

        __emitCoreSpecTests() { }
    }
}