
var fs = require('fs-extra');

function copyAll(pairs, onComplete) {
    var index = -1;
    function next() {
        if (++index >= pairs.length) onComplete();
        else {
            console.log('Copying ' + pairs[index].src + ' to ' + pairs[index].dest);
            fs.copy(pairs[index].src, pairs[index].dest, function (err) {
                if (err) throw err;
                else next();
            });
        }
    }
    next();
}

desc('Build');
task('build', {async: true}, function () {
    //compile
    console.log('Compiling with Pratphall');
    var cmds = [
        //regular src
        'node ./node_modules/pratphall/bin/ppc.js --no-php-lib -o ./src ./pratphall/src/common.ts',
        //test src
        'node ./node_modules/pratphall/bin/ppc.js --no-php-lib --exclude-outside --ext pratphall/tests/spec-ext.ts -o ./tests ./pratphall/tests/common.ts'
    ];
    jake.exec(cmds, function () {
        //copy other files
        console.log('Copying support files');
        copyAll([
            {src: 'pratphall/tests/phpunit.xml', dest: 'tests/phpunit.xml'}
        ], complete);
    }, {printStdout: true, printStderr: true});
});

desc('Run Tests');
task('test', ['build'], {async: true}, function () {
    console.log('Running tests');
    var cmds = [
        'vendor/bin/phpunit tests'
    ];
    jake.exec(cmds, complete, {printStdout: true, printStderr: true});
});