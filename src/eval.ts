///<reference path="dust.ts" />

module Dust.Eval {

    import Ast = Dust.Ast;

    class EvaluatorOptions {

    }

    class EvaluatorContext {
        out = '';
        stack: any[] = [];
        state: any;
    }

    class Evaluator {

        constructor(public options: EvaluatorOptions) { }
        /*
        evaluate(source: Ast.Body, state: any) {
            //create context
            var ctx = new EvaluatorContext();
            ctx.state = state;
            ctx.stack.push(state);
            //go
            this.evaluateBody(source, ctx);
            //return string
            return ctx.out;
        }

        evaluateBody(body: Ast.Body, ctx: EvaluatorContext) {
            body.parts.forEach((part: Ast.Part) => {
                if (part instanceof Ast.Comment) { }
                else if (part instanceof Ast.Section) this.evaluateSection(<Ast.Section>part, ctx);
                else if (part instanceof Ast.Partial) this.evaluatePartial(<Ast.Partial>part, ctx);
                else if (part instanceof Ast.Special) this.evaluateSpecial(<Ast.Special>part, ctx);
                else if (part instanceof Ast.Reference) this.evaluateReference(<Ast.Reference>part, ctx);
                else if (part instanceof Ast.Buffer) this.evaluateBuffer(<Ast.Buffer>part, ctx);
            });
        }

        evaluateSection(section: Ast.Section, ctx: EvaluatorContext) {
            //find the context

        }
    */
    }
}