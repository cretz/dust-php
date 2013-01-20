///<reference path="common.ts" />

interface Autoloader extends Pct.Ambient {
    add(namespace: string, dir: string);
}

var loader = <Autoloader>require('../vendor/autoload.php');
loader.add('Dust', __DIR__);