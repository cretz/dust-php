///<reference path="common.ts" />

module Dust.Ast {

    export class Ast {
        constructor(public offset: number) { }
    }

    export class Body extends Ast {
        parts: Part[];

        toString() {
            var str = '';
            if (!empty(this.parts)) {
                this.parts.forEach((value: Part) { str += value; });
            }
            return str;
        }
    }

    export class Part extends Ast {
    }

    export class InlinePart extends Part {
    }

    export class Section extends Part {
        static acceptableTypes = ['#', '?', '^', '<', '+', '@', '%'];

        type: string;
        identifier: Identifier;
        context: Context;
        parameters: Parameter[];
        body: Body;
        bodies: BodyList[];

        toString() {
            var str = '{' + this.type + this.identifier;
            if (this.context != null) str += this.context;
            if (!empty(this.parameters)) {
                this.parameters.forEach((value: Parameter) => {
                    str += ' ' + value;
                });
            }
            str += '}';
            if (this.body != null) str += this.body;
            if (!empty(this.bodies)) {
                this.bodies.forEach((value: BodyList) => { str += value; });
            }
            str += '{/' + this.identifier;
            return str;
        }
    }

    export class Context extends Ast {
        identifier: Identifier;

        toString() {
            return ':' + this.identifier;
        }
    }

    export class Parameter extends Ast {
        key: string;
    }

    export class NumericParameter extends Parameter {
        value: string;

        toString() {
            return this.key + '=' + this.value;
        }
    }

    export class IdentifierParameter extends Parameter {
        value: Identifier;

        toString() {
            return this.key + '=' + this.value;
        }
    }

    export class InlineParameter extends Parameter {
        value: Inline;

        toString() {
            return this.key + '=' + this.value;
        }
    }

    export class BodyList extends Ast {
        key: string;
        body: Body;

        toString() {
            var str = '{:' + this.key + '}';
            if (this.body != null) str += this.body;
            return str;
        }
    }

    export class Reference extends InlinePart {
        identifier: Identifier;
        filters: Filter[];

        toString() {
            var str = '{' + this.identifier;
            if (!empty(this.filters)) {
                this.filters.forEach((value: Filter) => { str += value; });
            }
            return str + '}';
        }
    }

    export class Partial extends Part {
        type: string;
        key: string;
        inline: Inline;
        context: Context;
        parameters: Parameter[];

        toString() {
            var str = '{' + this.type;
            if (this.key != null) str += this.key;
            else str += this.inline;
            if (this.context != null) str += this.context;
            if (!empty(this.parameters)) {
                this.parameters.forEach((value: Parameter) => {
                    str += ' ' + value;
                });
            }
            str += '/}';
            return str;
        }
    }

    export class Filter extends Ast {
        key: string;

        toString() {
            return '|' + this.key;
        }
    }

    export class Special extends InlinePart {
        key: string;

        toString() {
            return '{~' + this.key + '}';
        }
    }

    export class Identifier extends Ast {
        preDot = false;
        key: string;
        number: string;
        arrayAccess: Identifier;
        next: Identifier;

        toString() {
            var str = '';
            if (this.preDot) str += '.';
            if (this.key != null) str += this.key;
            else if (this.number != null) str += this.number;
            if (this.arrayAccess != null) str += '[' + this.arrayAccess + ']';
            if (this.next != null) str += this.next;
            return str;
        }
    }

    export class Inline extends Ast {
        parts: InlinePart[];

        toString() {
            var str = '"';
            if (!empty(this.parts)) {
                this.parts.forEach((value: InlinePart) => { str += value; });
            }
            return str + '"';
        }
    }

    export class InlineLiteral extends InlinePart {
        value: string;

        toString() {
            return this.value;
        }
    }

    export class Buffer extends Part {
        contents: string;

        toString() {
            return this.contents;
        }
    }

    export class Comment extends Part {
        contents: string;

        toString() {
            return '{!' + this.contents + '!}';
        }
    }
}