///<reference path="dust.ts" />

module Dust.Eval {

    import Ast = Dust.Ast;
    import Filter = Dust.Filter;

    class EvaluatorOptions {
        static defaultFilters = Pct.newAssocArray({
            s: new Filter.SuppressEscape(),
            h: new Filter.HtmlEscape,
            j: new Filter.JavaScriptEscape(),
            u: new Filter.EncodeUri(),
            uc: new Filter.EncodeUriComponent(),
            js: new Filter.JsonEncode(),
            jp: new Filter.JsonDecode()
        });

        filters = Pct.newAssocArray();
    }

    class EvaluatorContext {
        out = '';
        stack: EvaluatorState[] = [];
        state: EvaluatorState;
    }

    class EvaluatorState {
        value: any;
        forcedParent: any;
        params: Pct.PhpAssocArray = Pct.newAssocArray();
    }

    class EvaluatorException extends Exception {
        constructor(public ast: Ast.Ast, message: string) {
            super(message);
        }
    }

    class Evaluator {

        constructor(public options: EvaluatorOptions) { }

        error(ast: Ast.Ast, message: string) {
            throw new EvaluatorException(ast, message);
        }
        
        evaluate(source: Ast.Body, state: any) {
            //create context
            var ctx = new EvaluatorContext();
            ctx.state = new EvaluatorState();
            ctx.state.value = state;

            ctx.stack.push(ctx.state);
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
            //build a new state set
            var resolved = this.resolveIdentifierFromStack(section.identifier, ctx);
            //do the needful per type
            switch (section.type) {
                case '#':
                    //build state
                    var state = new EvaluatorState();
                    state.value = resolved;
                    //do we have an explicit context?
                    if (section.context != null) {
                        state.forcedParent = this.resolveIdentifierFromStack(section.context.identifier, ctx);
                    }
                    //how about params?
                    if (!empty(section.parameters)) {
                        state.params = this.evaluateParameters(section.parameters, ctx);
                    }
                    //empty means try else
                    if (empty(resolved)) {
                        if (section.bodies != null && section.bodies.length > 0) {
                            section.bodies.forEach((value: Ast.BodyList) => {
                                if (value.key == 'else') {
                                    this.evaluateBody(value.body, ctx);
                                }
                            });
                        }
                    } else {
                        //push the new state and also set it
                        ctx.state = state;
                        ctx.stack.push(state);
                        //array means loop
                        if (is_array(resolved)) {
                            //set $len
                            state.params['$len'] = (<Array>resolved).length;
                            (<Array>resolved).forEach((value: any, index: any) => {
                                //set $idx
                                state.params['$idx'] = index;
                                state.value = value;
                                //run body
                                this.evaluateBody(section.body, ctx);
                            });
                        } else {
                            //otherwise, just do the body
                            this.evaluateBody(section.body, ctx);
                        }
                    }
                    break;
                case '?':
                    //only if it exists
                    if (this.exists(resolved)) {
                        this.evaluateBody(section.body, ctx);
                    }
                    break;
                case '^':
                    //only if it doesn't exist
                    if (!this.exists(resolved)) {
                        this.evaluateBody(section.body, ctx);
                    }
                    break;
                //TODO
            }
        }

        evaluateReference(ref: Ast.Reference, ctx: EvaluatorContext) {
            //resolve
            var resolved = this.resolveIdentifierFromStack(ref.identifier, ctx);
            //make the string
            var str = this.toDustString(resolved);
            if (!empty(str)) {
                //apply filters in order
                str = this.options.filters.reduce((prev: string, curr: Filter.Filter) => {
                    return curr.apply(prev);
                }, str);
            }
            ctx.out += str;
        }

        evaluateParameters(params: Ast.Parameter[], ctx: EvaluatorContext) {
            var ret = Pct.newAssocArray();
            params.forEach((value: Ast.Parameter) => {
                if (value instanceof Ast.NumericParameter) {
                    if (Pct.isFalse(strpos((<Ast.NumericParameter>value).value, '.'))) {
                        ret[value.key] = intval((<Ast.NumericParameter>value).value);
                    } else ret[value.key] = floatval((<Ast.NumericParameter>value).value);
                } else if (value instanceof Ast.IdentifierParameter) {
                    ret[value.key] = this.resolveIdentifierFromStack((<Ast.IdentifierParameter>value).value, ctx);
                } else {
                    //we just set this as the actual AST since it is resolved where it's emitted
                    ret[value.key] = (<Ast.InlineParameter>value).value;
                }
            });
            return ret;
        }

        resolveIdentifierFromStack(identifier: Ast.Identifier, ctx: EvaluatorContext) {
            //walk up the stack trying to find the id
            var stackIdx = ctx.stack.length;
            var resolved: any = null;
            do {
                var stack = ctx.stack[--stackIdx];
                //try the value first
                resolved = this.resolveIdentifier(identifier, stack.value);
                //if it's not there, we can try the forced parent
                if (resolved == null && stack.forcedParent) {
                    this.resolveIdentifier(identifier, stack.forcedParent);
                }
                //if it's still not there, we can try parameters
                if (resolved == null && stack.params.length > 0) {
                    //just force an array lookup
                    resolved = this.resolveIdentifier(identifier, stack.params, true);
                }
            } while (resolved == null && stackIdx > 0 && stack.forcedParent == null);
            return resolved;
        }

        resolveIdentifier(identifier: Ast.Identifier, parent: any, forceArrayLookup = false) {
            var key: any;
            if (identifier.key != null) key = identifier.key;
            if (identifier.number != null) key = intval(identifier.number);
            else if (identifier.arrayAccess != null) {
                return this.resolveIdentifier(identifier.arrayAccess, parent, true);
            } else if (identifier.preDot) return parent;
            else return null;
            var result: any;
            //first, try to find on object (if we aren't forcing array lookup)
            if (!forceArrayLookup) result = this.findInObject(key, parent);
            //now, try to find in array
            if (result == null) result = this.findInArrayAccess(key, parent);
            //if it's there and has array access, try to get array child
            if (result != null && identifier.arrayAccess != null) {
                result = this.resolveIdentifier(identifier.arrayAccess, result, true);
            }
            //if it's there and has next, use it
            if (result != null && identifier.next) {
                result = this.resolveIdentifier(identifier.next, result);
            }
            return result;
        }

        findInObject(key: any, parent: any) {
            if (is_object(parent) && !is_numeric(key) && isset((<Object>parent)[key])) {
                return (<Object>parent)[key];
            } else return null;
        }

        findInArrayAccess(key: any, value: any) {
            if ((is_array(value) || value instanceof ArrayAccess) && isset((<Array>value)[key])) {
                return value[key];
            } else return null;
        }

        exists(val: any) {
            return ((is_string(val) && strlen(val) > 0) ||
                (is_bool(val) && <bool>val) ||
                (is_array(val) && !empty(val))) &&
                !is_null(val);
        }

        toDustString(val: any) {
            if (is_bool(val)) return <bool>val ? 'true' : 'false';
            else if (is_array(val)) return implode(',', val);
            else return Pct.castString(val);
        }
    }
}