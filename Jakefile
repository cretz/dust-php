
var fs = require('fs-extra');

desc('Build');
task('build', {async: true}, function () {
    //recreate build dir
    console.log('Recreating build directory');
    fs.removeSync('build');
    fs.mkdirsSync('build/src');

    //compile
    console.log('Compiling with pratphall');
    var cmds = [
        'node ./node_modules/pratphall/bin/ppc.js --no-php-lib --all-caps-const -o ./build/src ./src/dust.ts'
    ];
    jake.exec(cmds, complete, {printStdout: true, printStderr: true});
});