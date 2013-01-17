///<reference path="dust.ts" />

module Dust.Ast {

    export class Ast {
        constructor(public offset: number) { }
    }

    export class Body extends Ast {
        parts: Part[];
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
    }

    export class Context extends Ast {
        identifier: Identifier;
    }

    export class Parameter extends Ast {
        key: string;
    }

    export class NumericParameter extends Parameter {
        value: string;
    }

    export class IdentifierParameter extends Parameter {
        value: Identifier;
    }

    export class InlineParameter extends Parameter {
        value: Inline;
    }

    export class BodyList extends Ast {
        key: string;
        body: Body;
    }

    export class Reference extends InlinePart {
        identifier: Identifier;
        filters: Filter[];
    }

    export class Partial extends Part {
        type: string;
        key: string;
        inline: Inline;
        context: Context;
        parameters: Parameter[];
    }

    export class Filter extends Ast {
        key: string;
    }

    export class Special extends InlinePart {
        key: string;
    }

    export class Identifier extends Ast {
        preDot = false;
        key: string;
        number: string;
        arrayAccess: Identifier;
        next: Identifier;
    }

    export class Inline extends Ast {
        parts: InlinePart[];
    }

    export class InlineLiteral extends InlinePart {
        value: string;
    }

    export class Buffer extends Part {
        contents: string;
    }

    export class Comment extends Part {
        contents: string;
    }
}