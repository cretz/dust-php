///<reference path="common.ts" />

module Dust {
    export class DustException extends Exception { }

    export class Dust implements Serializable {

        templates = Pct.newAssocArray();

        filters = Pct.newAssocArray({
            s: new Filter.SuppressEscape(),
            h: new Filter.HtmlEscape(),
            j: new Filter.JavaScriptEscape(),
            u: new Filter.EncodeUri(),
            uc: new Filter.EncodeUriComponent(),
            js: new Filter.JsonEncode(),
            jp: new Filter.JsonDecode()
        });

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
            size: new Helper.Size().__invoke,
            contextDump: new Helper.ContextDump().__invoke
        });

        automaticFilters: Filter[];
        includedDirectories: string[] = [];
        autoloaderOverride: (templateName: string) => Ast.Body;

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
         * Resolve the absolute .dust file path, or return null
         */
        resolveAbsoluteDustFilePath(path: string, basePath?: string) {
            //add extension if necessary
            if (substr_compare(path, '.dust', -5, 5) !== 0) path += '.dust';
            //if base path provided, try it
            var possible: string;
            if (basePath != null) {
                possible = realpath(basePath + '/' + path);
                if (Pct.isNotFalse(possible)) return possible;
            }
            //try the current path
            possible = realpath(path);
            if (Pct.isNotFalse(possible)) return possible;
            //now try each of the included directories
            for (var i = 0; i < this.includedDirectories.length; i++) {
                possible = realpath(this.includedDirectories[i] + '/' + path);
                if (Pct.isNotFalse(possible)) return possible;
            }
            return null;
        }

        /**
         * Compile given path to AST or null if not found
         */
        compileFile(path: string, basePath?: string) {
            //resolve absolute path
            var absolutePath = this.resolveAbsoluteDustFilePath(path, basePath);
            if (absolutePath == null) return null;
            //just compile w/ the path as the name
            var compiled = this.compile(file_get_contents(absolutePath), absolutePath);
            compiled.filePath = absolutePath;
            return compiled;
        }

        /**
         * Registers a parsed template as a certain name
         */
        register(name: string, template: Ast.Body) {
            this.templates[name] = template;
        }

        /**
         * Load a template from a name or return null if not found
         */
        loadTemplate(name: string, basePath?: string) {
            //if there is an override, use it instead
            if (this.autoloaderOverride != null) return this.autoloaderOverride(name);
            //is it there w/ the normal name?
            if (!isset(this.templates[name])) {
                //what if I used the resolve file version of the name
                name = this.resolveAbsoluteDustFilePath(name, basePath);
                //if name is null, then it's not around
                if (name == null) return null;
                //if name is null and not in the templates array, put it there automatically
                if (!isset(this.templates[name])) this.compileFile(name, basePath);
            }
            return this.templates[name];
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