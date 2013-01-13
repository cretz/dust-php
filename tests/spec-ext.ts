///<reference path='../node_modules/pratphall/src/pratphall.ts' />

module Dust.Extension {

    //import the existing spec

    interface SpecSuite {
        name: string;
        tests: SpecTest[];
    }

    interface SpecTest {
        name: string;
        source: string;
        context: any;
        expected?: string;
        error?: string;
        message: string;
    }

    import TS = TypeScript;
    var suites = <SpecSuite[]>require('./node_modules/dustjs-linkedin/test/jasmine-test/coreTests.js');

    //register emitter extension to write our tests for us

    Pratphall.PhpEmitter.registerExtension({
        name: "Dust spec tests",
        description: 'Emit dust spec tests as individual PHP unit tests',
        matcher: {
            nodeType: [TS.NodeType.FuncDecl],
            priority: 2,
            propertyMatches: {
                name: (value: TS.Identifier) { return value.actualText == '__emitCoreSpecTests'; }
            }
        },
        emit: (ast: TS.FuncDecl, emitter: Pratphall.PhpEmitter) {
            //ok, let's loop over the suites
            suites.forEach((suite: SpecSuite) => {
                emitter.newline().write('// TEST SUITE: ' + suite.name).newline();
                //TODO

            })
            return true;
        }
    });
}