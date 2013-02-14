<?php
namespace Dust;

class FilesystemTest extends DustTestBase {
    public static $dir;
    
    public static function setUpBeforeClass() {
        FilesystemTest::$dir = sys_get_temp_dir();
        if (FilesystemTest::$dir[strlen(FilesystemTest::$dir) - 1] != '/') FilesystemTest::$dir .= '/';
        FilesystemTest::$dir .= 'dustFsTest';
        //need to tear down if it's there
        if (is_dir(FilesystemTest::$dir)) FilesystemTest::tearDownAfterClass();
        //create
        if (!mkdir(FilesystemTest::$dir)) throw new DustException('Unable to create dir: ' . FilesystemTest::$dir);
        //add base template
        $fileRes = file_put_contents(FilesystemTest::$dir . '/baseTemplate.dust', 'before{~n}{+one}oneDefault{/one}...{+two/}...{+three}threeDefault{/three}{~n}after');
        if ($fileRes === false) throw new DustException('Unable to create template');
        //add child template
        $fileRes = file_put_contents(FilesystemTest::$dir . '/childTemplate.dust', '{>baseTemplate/}{<two}newTwo{/two}{<three}newThree{/three}');
        if ($fileRes === false) throw new DustException('Unable to create template');
    }
    
    public static function deleteTree($dir) {
        foreach (scandir($dir) as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($dir . '/' . $file)) FilesystemTest::deleteTree($dir . '/' . $file);
                else unlink($dir . '/' . $file);
            }
        }
        return rmdir($dir);
    }
    
    public static function tearDownAfterClass() {
        if (!FilesystemTest::deleteTree(FilesystemTest::$dir)) throw new DustException('Unable to delete dir: ' . FilesystemTest::$dir);
    }
    
    public function testSimpleBlock() {
        //just run the child, it should auto-find the base
        $compiled = $this->dust->compileFile(FilesystemTest::$dir . '/childTemplate');
        $expected = "before\noneDefault...newTwo...newThree\nafter";
        $this->assertEquals($expected, $this->dust->renderTemplate($compiled, (object)[]));
    }
    
    public function testIncludedDirectories() {
        //add the dir
        $this->dust->includedDirectories[] = FilesystemTest::$dir;
        //now have something call the child
        $this->assertTemplate("Begin - before\noneDefault...newTwo...newThree\nafter - End", 'Begin - {>childTemplate/} - End', (object)[]);
    }
    
}