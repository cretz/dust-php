///<reference path='../node_modules/pratphall/src/pratphall.ts' />

//this extension PHP-ifies the spec tests
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

    declare function require(path: string): any;

    import TS = TypeScript;
    var io = Pratphall.loadIo();
    var tests = <SpecTest[]>require(io.joinPaths(
        io.cwd(), './node_modules/dustjs-linkedin/test/jasmine-test/spec/coreTests.js'));

    //we have to ignore certain JS-only tests
    var ignored = [
        //uses set timeout
        'intro',
        //async
        'async_key',
        //pragmas not supported atm (hard to find concrete definition)
        'escape_pragma',
        //can't do json parse filter because PHP doesn't stringify objects the same way
        'JSON.parse filter'
    ];

    //register emitter extension to write our tests for us
    Pratphall.PhpEmitter.registerExtension({
        name: "Dust spec tests",
        description: 'Emit dust spec tests as individual PHP unit tests',
        matcher: {
            nodeType: [TS.NodeType.FuncDecl],
            priority: 2,
            propertyMatches: {
                name: (value: TS.Identifier) => { return value != null && value.actualText == '__emitCoreSpecTests'; }
            }
        },
        emit: (ast: TS.FuncDecl, emitter: Pratphall.PhpEmitter) => {
            var funcNames: string[] = [];
            var scripts: string[] = [];
            //dependencies keyed by the test func, values are arrays of strings that are dependencies
            var dependencies = {};
            //fix the naming to get the proper name
            var getProperFuncName = (name: string, forceUnique = true) => {
                var funcName = 'test' + name.replace(/[^A-Za-z0-9_ ]/g, '').toLowerCase().split(/ |_/g).
                    reduce((prev: string, curr: string) => {
                        return prev + curr.charAt(0).toUpperCase() + curr.substr(1);
                    }, '');
                if (!forceUnique) return funcName;
                //need to make sure we don't have ambiguous name
                var properFuncName = funcName;
                var counter = 1;
                while (funcNames.indexOf(properFuncName) != -1) {
                    properFuncName = funcName + (++counter);
                }
                return properFuncName;
            };
            //ok, let's loop over the tests and make parseable scripts and function names
            tests.forEach((test: SpecTest) => {
                if (ignored.indexOf(test.name) != -1) return;
                //make a function name from the name
                var funcName = getProperFuncName(test.name);
                //if (funcName != 'testTestThatTheScopeOfTheFunctionIsCorrectAndThatANonchunkReturnValueIsUsedForTruthinessChecks') return;
                //add func and script
                funcNames.push(funcName);
                //build up dependencies from partials
                var partials = test.source.match(/{>[A-Za-z0-9_ "{"]*/g);
                if (partials != null) {
                    partials.forEach((value: string) => {
                        if (!(funcName in dependencies)) dependencies[funcName] = [];
                        var dep = getProperFuncName(value.substr(2).trim(), false);
                        if (funcNames.indexOf(dep) != -1) dependencies[funcName].push(dep);
                    });
                }
                //replace some stuff for typing purposes
                var source = Pratphall.toJavaScriptSource(test);
                source = source.replace('data.toUpperCase()', '(<string>data).toUpperCase()');
                source = source.replace('var items', 'var items: any[]');
                //go ahead and parse
                scripts.push('var test = ' + source);
            });
            //compile the scripts
            var asts = Pratphall.parseMultipleTypeScripts(scripts, false, true);
            asts.forEach((value: TypeScript.Script, index: number) => {
                //write dependencies
                var deps = dependencies[funcNames[index]];
                if (deps != null && deps.length > 0) {
                    emitter.newline().write('/**');
                    deps.forEach((dep: string) => {
                        emitter.newline().write(' * @depends ' + dep);
                    });
                    emitter.newline().write(' */');
                }
                //write function
                emitter.newline().write('public function ' + funcNames[index] + '() {').increaseIndent().newline();
                emitter.emit(value.bod.members[0]).write(';');
                emitter.newline().write('$this->runSpecTest($test);');
                emitter.decreaseIndent().newline().write('}').newline();
            });
            return true;
        }
    });
}