///<reference path="common.ts" />

module Dust.Filter {

    export interface Filter {
        apply(str: string): string;
    }

    export class SuppressEscape implements Filter {
        apply(str: string) { return str; }
    }

    export class HtmlEscape implements Filter {
        apply(str: string) { return htmlspecialchars(str); }
    }

    export class JavaScriptEscape implements Filter {
        apply(str: string) {
            str = json_encode(str);
            return str.substr(1, str.length - 2);
        }
    }

    export class EncodeUri implements Filter {
        //ref: http://stackoverflow.com/questions/4929584/encodeuri-in-php
        static replacers = Pct.newAssocArray({
            //unescaped
            '%2D': '-',
            '%5F': '_',
            '%2E': '.',
            '%21': '!',
            '%7E': '~',
            '%2A': '*',
            '%27': "'",
            '%28': '(',
            '%29': ')',
            //reserved
            '%3B': ';',
            '%2C': ',',
            '%2F': '/',
            '%3F':'?',
            '%3A': ':',
            '%40': '@',
            '%26': '&',
            '%3D': '=',
            '%2B': '+',
            '%24': '$',
            //score
            '%23': '#'
        });

        apply(str: string) { return strtr(rawurlencode(str), EncodeUri.replacers); }
    }

    export class EncodeUriComponent implements Filter {
        //ref: http://stackoverflow.com/questions/1734250/what-is-the-equivalent-of-javascripts-encodeuricomponent-in-php
        static replacers = Pct.newAssocArray({
            '%21': '!',
            '%2A': '*',
            '%27': "'",
            '%28': '(',
            '%29': ')'
        });

        apply(str: string) { return strtr(rawurlencode(str), EncodeUriComponent.replacers); }
    }

    export class JsonEncode implements Filter {
        apply(str: string) { return json_encode(str); }
    }

    export class JsonDecode implements Filter {
        apply(str: string) { return strval(json_decode(str)); }
    }
}