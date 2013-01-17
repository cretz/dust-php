///<reference path='../node_modules/pratphall/src/pratphall.ts' />

module Dust.Extension {

    //import the existing spec

    interface SpecTest {
        name: string;
        source: string;
        context: any;
        expected?: string;
        error?: string;
        message: string;
    }

    import TS = TypeScript;
    var io = Pratphall.loadIo();
    var tests = <SpecTest[]>require(io.joinPaths(
        io.cwd(), './node_modules/dustjs-linkedin/test/jasmine-test/spec/coreTests.js'));

    //we have to ignore certain JS-only tests
    var ignored = [
        //uses set timeout
        'intro',
        //async
        'async_key'
    ];
    //register emitter extension to write our tests for us
    Pratphall.PhpEmitter.registerExtension({
        name: "Dust spec tests",
        description: 'Emit dust spec tests as individual PHP unit tests',
        matcher: {
            nodeType: [TS.NodeType.FuncDecl],
            priority: 2,
            propertyMatches: {
                name: (value: TS.Identifier) { return value != null && value.actualText == '__emitCoreSpecTests'; }
            }
        },
        emit: (ast: TS.FuncDecl, emitter: Pratphall.PhpEmitter) {
            //ok, let's loop over the tests and make parseable scripts and function names
            var funcNames: string[] = [];
            var scripts: string[] = [];
            tests.forEach((test: SpecTest) => {
                if (ignored.indexOf(test.name) != -1) return;
                //make a function name from the name
                funcNames.push('test' + test.name.replace(/[^A-Za-z0-9_ ]/g, '').toLowerCase().split(/ |_/g).
                    reduce((prev: string, curr: string) => {
                        return prev + curr.charAt(0).toUpperCase() + curr.substr(1);
                    }, ''));
                scripts.push('var test = ' + Pratphall.toJavaScriptSource(test));
            });
            //compile the scripts
            var asts = Pratphall.parseMultipleTypeScripts(scripts, false, true);
            asts.forEach((value: TypeScript.Script, index: number) => {
                emitter.newline().write('public function ' + funcNames[index] + '() {').increaseIndent().newline();
                emitter.emit(value.bod.members[0]).write(';');
                emitter.decreaseIndent().newline().write('}').newline();
            });
            return true;
        }
    });
}