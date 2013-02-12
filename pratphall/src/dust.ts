///<reference path="common.ts" />

module Dust {
    export class DustException extends Exception { }

    export class Dust implements Serializable {

        /** @type Ast.Body[] */
        templates = Pct.newAssocArray();

        /** @type Dust.Filter[] */
        filters = Pct.newAssocArray({
            s: new Filter.SuppressEscape(),
            h: new Filter.HtmlEscape(),
            j: new Filter.JavaScriptEscape(),
            u: new Filter.EncodeUri(),
            uc: new Filter.EncodeUriComponent(),
            js: new Filter.JsonEncode(),
            jp: new Filter.JsonDecode()
        });

        /** @type Evaluate.EvaluationCallback[] */
        helpers = Pct.newAssocArray({
            select: new Helper.Select().__invoke,
            math: new Helper.Math().__invoke,
            eq: new Helper.Eq().__invoke,
            lt: new Helper.Lt().__invoke,
            lte: new Helper.Lte().__invoke,
            gt: new Helper.Gt().__invoke,
            gte: new Helper.Gte().__invoke,
            default: new Helper.DefaultHelper().__invoke,
            sep: new Helper.Sep().__invoke,
            size: new Helper.Size().__invoke
        });

        automaticFilters: Filter[];

        constructor(public parser = new Parse.Parser(), public evaluator = new Evaluate.Evaluator(this)) {
            this.automaticFilters = [this.filters['h']];
        }

        /**
         * Compile source into serializable AST
         */
        compile(source: string, name?: string) {
            var parsed = this.parser.parse(source);
            if (name != null) this.register(name, parsed);
            return parsed;
        }

        /**
         * Compile source into callable function that accepts context
         */
        compileFn(source: string, name?: string) {
            var parsed = this.compile(source, name);
            return (context: any) => { return this.renderTemplate(parsed, context); };
        }

        /**
         * Registers a parsed template as a certain name
         */
        register(name: string, template: Ast.Body) {
            this.templates[name] = template;
        }

        /**
         * Load a template from a name or return false if not found
         */
        loadTemplate(name: string) {
            if (!isset(this.templates[name])) return false;
            return this.templates[name];
        }

        templateExists(name: string) {
            return isset(this.templates[name]);
        }

        render(name: string, context: any) {
            return this.renderTemplate(this.loadTemplate(name), context);
        }

        renderTemplate(template: Ast.Body, context: any) {
            return this.evaluator.evaluate(template, context);
        }

        serialize() { return serialize(this.templates); }
        unserialize(data: string) { this.templates = unserialize(data); }
    }
}