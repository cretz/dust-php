///<reference path="common.ts" />

module Dust {

    export class DustTestBase extends PHPUnit.Framework.TestCase {

        dust: Dust;

        setUp() {
            this.dust = new Dust();
        }

        assertTemplate(expected: string, template: string, context: any) {
            var compiled = this.dust.compile(template);
            this.assertEquals(expected, this.dust.renderTemplate(compiled, context));
        }
    }

    class StoogeName {
        constructor(public name: string) { }
    }

    class StoogesContext {
        title = 'Famous People';

        names() {
            return [new StoogeName('Larry'), new StoogeName('Curly'), new StoogeName('Moe')];
        }
    }

    class StripTagsFilter implements Filter.Filter {
        apply(item: any) {
            if (!is_string(item)) return item;
            return strip_tags(<string>item);
        }
    }

    class StandardTest extends DustTestBase {

        testContextTypes() {
            var template = '{title}' +
                '<ul>' +
                '{#names}' +
                '  <li>{name}</li>{~n}' +
                '{/names}' +
                '</ul>';
            var expected = 'Famous People' +
                '<ul>' +
                '  <li>Larry</li>\n' +
                '  <li>Curly</li>\n' +
                '  <li>Moe</li>\n' +
                '</ul>';
            //ok, test w/ associative array
            this.assertTemplate(expected, template, Pct.newAssocArray({
                title: 'Famous People',
                names: [
                    Pct.newAssocArray({ name: 'Larry' }),
                    Pct.newAssocArray({ name: 'Curly' }),
                    Pct.newAssocArray({ name: 'Moe' })
                ]
            }));
            //dynamic object
            this.assertTemplate(expected, template, {
                title: 'Famous People',
                names: [
                    { name: 'Larry' },
                    { name: 'Curly' },
                    { name: 'Moe' }
                ]
            });
            //json (basically same as above)
            this.assertTemplate(expected, template, json_decode('{' +
                '"title": "Famous People",' +
                '"names": [' +
                    '{ "name": "Larry" },' +
                    '{ "name": "Curly" },' +
                    '{ "name": "Moe" }' +
                ']' +
            '}'));
            //class
            this.assertTemplate(expected, template, new StoogesContext());
        }

        testArrayAccess() {
            this.assertTemplate('123', '{#items}{.}{/items}', { items: new ArrayObject([1, 2, 3]) });
        }

        testStringIndex() {
            this.assertTemplate('a => b,2 => c,foo => blah', '{#items}{$idx} => {.}{@sep},{/sep}{/items}',
                Pct.newAssocArray({ items: Pct.newAssocArray({ a: 'b', 2: 'c', foo: 'blah' }) }));
        }

        testAutoloaderOverride() {
            //override
            var $autoloaderInvoked = false;
            this.dust.autoloaderOverride = (name: string) => {
                $autoloaderInvoked = true;
                this.assertEquals('testCustom', name);
                return this.dust.compile('{#children}Child: {.}, {/children}');
            };
            //now run partial w/ expectation
            this.assertTemplate('Before, Child: foo, Child: bar, After',
                '{#item}Before, {>testCustom/}After{/item}', {
                    item: {
                        children: ['foo', 'bar']
                    }
                });
            this.assertTrue($autoloaderInvoked);
        }

        testCustomFilter() {
            this.dust.filters['stripTags'] = new StripTagsFilter();
            this.assertTemplate('Value: foo, bar', 'Value: {contents|stripTags}',
                { contents: '<div>foo, <br /><strong>bar</strong></div>' });
        }

        testCustomHelper() {
            //from manual
            this.dust.helpers['substr'] = (chunk: Evaluate.Chunk, ctx: Evaluate.Context, bodies: Evaluate.Bodies, params: Evaluate.Parameters) => {
                //make sure str is present
                if (!isset(params['str'])) return chunk.setError('Parameter required: str');
                //parse parameters
                var str = params['str'];
                var begin = isset(params['begin']) ? params['begin'] : 0;
                var end = isset(params['end']) ? params['end'] : null;
                var len = isset(params['len']) ? params['len'] : null;
                //if len is set, use it instead of end
                if (len !== null) return chunk.write(substr(str, begin, len));
                if (end !== null) return chunk.write(substr(str, begin, end - begin));
                return chunk.write(substr(str, begin));
            };
            //test some things (kinda taken from PHP manual)
            this.assertTemplate('bcdef,bcd,abcd,abcdef,bc',
                '{@substr str="abcdef" begin=1 /},' +
                '{@substr str="abcdef" begin=1 len=3 /},' +
                '{@substr str="abcdef" len=4 /},' +
                '{@substr str="abcdef" len=8 /},' +
                '{@substr str="abcdef" begin=1 end=3 /}', { });
        }
    }

    class FilesystemTest extends DustTestBase {
        static dir: string;

        static setUpBeforeClass() {
            FilesystemTest.dir = sys_get_temp_dir();
            if (FilesystemTest.dir.charAt(FilesystemTest.dir.length - 1) != '/') FilesystemTest.dir += '/';
            FilesystemTest.dir += 'dustFsTest';
            //need to tear down if it's there
            if (is_dir(FilesystemTest.dir)) FilesystemTest.tearDownAfterClass();
            //create
            if (!mkdir(FilesystemTest.dir)) throw new DustException('Unable to create dir: ' + FilesystemTest.dir);
            //add base template
            var fileRes = file_put_contents(FilesystemTest.dir + '/baseTemplate.dust',
                'before{~n}{+one}oneDefault{/one}...{+two/}...{+three}threeDefault{/three}{~n}after');
            if (Pct.isFalse(fileRes)) throw new DustException('Unable to create template');
            //add child template
            var fileRes = file_put_contents(FilesystemTest.dir + '/childTemplate.dust',
                '{>baseTemplate/}{<two}newTwo{/two}{<three}newThree{/three}');
            if (Pct.isFalse(fileRes)) throw new DustException('Unable to create template');
        }

        static deleteTree(dir: string) {
            scandir(dir).forEach((file: string) => {
                if (file != '.' && file != '..') {
                    if (is_dir(dir + '/' + file)) FilesystemTest.deleteTree(dir + '/' + file);
                    else unlink(dir + '/' + file);
                }
            });
            return rmdir(dir);
        }

        static tearDownAfterClass() {
            if (!FilesystemTest.deleteTree(FilesystemTest.dir)) throw new DustException('Unable to delete dir: ' + FilesystemTest.dir);
        }

        testSimpleBlock() {
            //just run the child, it should auto-find the base
            var compiled = this.dust.compileFile(FilesystemTest.dir + '/childTemplate');
            var expected = 'before\noneDefault...newTwo...newThree\nafter';
            this.assertEquals(expected, this.dust.renderTemplate(compiled, { }));
        }

        testIncludedDirectories() {
            //add the dir
            this.dust.includedDirectories.push(FilesystemTest.dir);
            //now have something call the child
            this.assertTemplate('Begin - before\noneDefault...newTwo...newThree\nafter - End',
                'Begin - {>childTemplate/} - End', { });
        }
    }
}
