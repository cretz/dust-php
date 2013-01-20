
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
    //recreate build dir
    console.log('Recreating build directory');
    //fs.removeSync('build');
    fs.mkdirsSync('build/src');
    fs.mkdirsSync('build/tests');

    //compile
    console.log('Compiling with Pratphall');
    var cmds = [
        //regular src
        'node ./node_modules/pratphall/bin/ppc.js --no-php-lib --all-caps-const -o ./build/src ./src/common.ts',
        //test src
        'node ./node_modules/pratphall/bin/ppc.js --no-php-lib --all-caps-const --exclude-outside --no-composer --ext tests/spec-ext.ts -o ./build/tests ./tests/common.ts'
    ];
    jake.exec(cmds, function () {
        //copy other files
        console.log('Copying support files');
        copyAll([
            {src: 'LICENSE', dest: 'build/LICENSE'},
            {src: 'README.md', dest: 'build/README.md'},
            {src: 'composer.json', dest: 'build/composer.json'},
            {src: 'tests/phpunit.xml', dest: 'build/tests/phpunit.xml'}
        ], complete);
    }, {printStdout: true, printStderr: true});
});
