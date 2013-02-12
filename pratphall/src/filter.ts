///<reference path="common.ts" />

module Dust.Filter {

    export interface Filter {
        apply(item: any): any;
    }

    export class SuppressEscape implements Filter {
        apply(item: any) { return item; }
    }

    export class HtmlEscape implements Filter {
        static replacers = Pct.newAssocArray({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        });

        apply(item: any) {
            if (!is_string(item)) return item;
            return str_replace(array_keys(HtmlEscape.replacers),
                array_values(HtmlEscape.replacers), <string>item);
        }
    }

    export class JavaScriptEscape implements Filter {
        static replacers = Pct.newAssocArray({
            '\\': '\\\\',
            '\r': '\\r',
            '\n': '\\n',
            '\f': '\\f',
            "'": "\\'",
            '"': "\\\"",
            '\t': '\\t'
        });

        apply(item: any) {
            if (!is_string(item)) return item;
            return str_replace(array_keys(JavaScriptEscape.replacers),
                array_values(JavaScriptEscape.replacers), <string>item);
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

        apply(item: any) {
            if (!is_string(item)) return item;
            return strtr(rawurlencode(<string>item), EncodeUri.replacers);
        }
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

        apply(item: any) {
            if (!is_string(item)) return item;
            return strtr(rawurlencode(<string>item), EncodeUriComponent.replacers);
        }
    }

    export class JsonEncode implements Filter {
        apply(item: any) { return json_encode(item); }
    }

    export class JsonDecode implements Filter {
        apply(item: any) { return json_decode(item); }
    }
}