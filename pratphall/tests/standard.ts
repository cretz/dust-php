///<reference path="common.ts" />

module Dust {

    class DustTestBase extends PHPUnit.Framework.TestCase {

        protected assertTemplate(expected: string, template: string, context: any) {
            var dust = new Dust();
            var compiled = dust.compile(input);
            this.assertEquals(expected, dust.renderTemplate(compiled, context));
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

    class StandardTest extends DustTestBase {

        testContextTypes() {
            var template = '{title}\n' +
                '<ul>\n' +
                '{#names}\n' +
                '  <li>{name}</li>{~n}\n' +
                '{/names}\n' +
                '</ul>';
            var expected = 'Famous People\n' +
                '<ul>\n' +
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
            this.assertTemplate(expected, template, json_encode('{
                "title": "Famous People",
                "names": [
                    { "name": "Larry" },
                    { "name": "Curly" },
                    { "name": "Moe" }
                ]
            }'));
            //class
            this.assertTemplate(expected, template, new StoogesContext());
        }
    }
}
