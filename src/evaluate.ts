///<reference path="common.ts" />

module Dust.Evaluate {

    export class EvaluatorOptions {
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

    export class EvaluatorContext {
        out = '';
        stack: EvaluatorState[] = [];
        state: EvaluatorState;
    }

    export class EvaluatorState {
        value: any;
        forcedParent: any;
        params: Pct.PhpAssocArray = Pct.newAssocArray();
    }

    export class EvaluateException extends Exception {
        constructor(public ast: Ast.Ast, message: string) {
            super(message);
        }
    }

    export class Evaluator {

        private filters: Pct.PhpAssocArray;

        constructor(private options = new EvaluatorOptions()) {
            this.filters = Pct.unionArray(this.options.filters, EvaluatorOptions.defaultFilters);
        }

        error(ast: Ast.Ast, message: string) {
            throw new EvaluateException(ast, message);
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
                    if (this.isEmpty(resolved)) {
                        this.evaluateElseBody(section, ctx);
                    } else {
                        //push the new state and also set it
                        ctx.state = state;
                        ctx.stack.push(state);
                        //callable means callback
                        if (is_callable(resolved)) {
                            this.handleCallback(section, ctx, resolved);
                        } else if (is_array(resolved)) {
                            //array means loop
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
                    } else {
                        this.evaluateElseBody(section, ctx);
                    }
                    break;
                case '^':
                    //only if it doesn't exist
                    if (!this.exists(resolved)) {
                        this.evaluateBody(section.body, ctx);
                    } else {
                        this.evaluateElseBody(section, ctx);
                    }
                    break;
                case '+':
                    //partial
                    //render contents for now
                    if (section.body != null) this.evaluateBody(section.body, ctx);
                    break;
                default:
                    throw new EvaluateException(section, 'Unrecognized type: ' + section.type);
            }
        }

        evaluateElseBody(section: Ast.Section, ctx: EvaluatorContext) {
            if (section.bodies != null && section.bodies.length > 0) {
                section.bodies.forEach((value: Ast.BodyList) => {
                    if (value.key == 'else') {
                        this.evaluateBody(value.body, ctx);
                    }
                });
            }
        }

        evaluatePartial(partial: Ast.Partial, ctx: EvaluatorContext) {
            throw new EvaluateException(partial, 'Not yet supported');
        }

        evaluateSpecial(spl: Ast.Special, ctx: EvaluatorContext) {
            switch (spl.key) {
                case 'n': 
                    ctx.out += '\n';
                    break;
                case 'r':
                    ctx.out += '\r';
                    break;
                case 'lb':
                    ctx.out += '{';
                    break;
                case 'rb':
                    ctx.out += '}';
                    break;
                case 's':
                    ctx.out += ' ';
                    break;
                default:
                    throw new EvaluateException(spl, 'Unrecognized special: ' + spl.key);
            }
        }

        evaluateReference(ref: Ast.Reference, ctx: EvaluatorContext) {
            //resolve
            var resolved = this.resolveIdentifierFromStack(ref.identifier, ctx);
            if (!this.isEmpty(resolved)) {
                //make the string
                var str = this.toDustString(resolved);
                if (!empty(str)) {
                    if (!empty(ref.filters)) {
                        //apply filters in order...
                        str = ref.filters.reduce((prev: string, curr: Ast.Filter) => {
                            var filter = <Filter.Filter>this.filters[curr.key];
                            if (filter == null) throw new EvaluateException(curr, 'Unrecognized filter');
                            return filter.apply(prev);
                        }, str);
                    }
                }
                ctx.out += str;
            }
        }

        evaluateBuffer(buffer: Ast.Buffer, ctx: EvaluatorContext) {
            ctx.out += buffer.contents;
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
                //forced local?
                if (identifier.preDot) return resolved;
                //if it's not there, we can try the forced parent
                if (resolved === null && stack.forcedParent) {
                    this.resolveIdentifier(identifier, stack.forcedParent);
                }
                //if it's still not there, we can try parameters
                if (resolved === null && stack.params.length > 0) {
                    //just force an array lookup
                    resolved = this.resolveIdentifier(identifier, stack.params, true);
                }
            } while (resolved === null && stackIdx > 0 && stack.forcedParent == null);
            return resolved;
        }

        resolveIdentifier(identifier: Ast.Identifier, parent: any, forceArrayLookup = false) {
            var key: any;
            if (identifier.key != null) key = identifier.key;
            else if (identifier.number != null) key = intval(identifier.number);
            else if (identifier.arrayAccess != null) {
                return this.resolveIdentifier(identifier.arrayAccess, parent, true);
            } else if (identifier.preDot) return parent;
            else return null;
            var result: any;
            //first, try to find on object (if we aren't forcing array lookup)
            if (!forceArrayLookup) result = this.findInObject(key, parent);
            //now, try to find in array
            if (result === null) result = this.findInArrayAccess(key, parent);
            //if it's there and has array access, try to get array child
            if (result !== null && identifier.arrayAccess != null) {
                result = this.resolveIdentifier(identifier.arrayAccess, result, true);
            }
            //if it's there and has next, use it
            if (result !== null && identifier.next && !is_callable(result)) {
                result = this.resolveIdentifier(identifier.next, result);
            }
            return result;
        }

        findInObject(key: any, parent: any) {
            if (is_object(parent) && !is_numeric(key)) {
                //prop or method
                if (isset((<Object>parent)[key])) {
                    return (<Object>parent)[key];
                } else if (method_exists(parent, key)) {
                    return (new ReflectionMethod(parent, key)).getClosureThis();
                }
            } else return null;
        }

        findInArrayAccess(key: any, value: any) {
            if ((is_array(value) || value instanceof ArrayAccess) && isset((<Array>value)[key])) {
                return value[key];
            } else return null;
        }

        isEmpty(val: any) {
            //numeric not empty
            if (is_numeric(val)) return false;
            //otherwise, normal empty check
            return empty(val);
        }

        exists(val: any) {
            //object exists
            if (is_object(val)) return true;
            //numeric exists
            if (is_numeric(val)) return true;
            //empty string does not exist
            if (is_string(val)) return !empty(val);
            //false does not exist
            if (is_bool(val)) return <bool>val;
            //empty arrays do not exist
            if (is_array(val)) return !empty(val);
            //nulls do not exist
            return !is_null(val);
        }

        toDustString(val: any) {
            if (is_bool(val)) return <bool>val ? 'true' : 'false';
            else if (is_array(val)) return implode(',', val);
            else return Pct.castString(val);
        }

        handleCallback(section: Ast.Section, ctx: EvaluatorContext, callback: (...args: any[]) => any) {
            var reflected = new ReflectionFunction(callback);
            var paramCount = reflected.getNumberOfParameters();
            var args = [];
            if (paramCount > 0) {
                args.push(new HandlerChunk(this, ctx));
                if (paramCount > 1) {
                    args.push(new HandlerContext(this, ctx));
                    if (paramCount > 2) {
                        args.push(new HandlerBodies(section));
                        if (paramCount > 3) {
                            args.push(new HandlerParameters(ctx.state));
                        }
                    }
                }
            }
            //invoke
            var result = reflected.invokeArgs(args);
            if (is_string(result)) {
                ctx.out += <string>result;
            } else if (result instanceof HandlerChunk) {
                ctx.out += (<HandlerChunk>result).getOutput();
            }
        }
    }

    export class HandlerChunk {
        private ctx: EvaluatorContext;

        constructor(private evaluator: Evaluator, ctx: EvaluatorContext) {
            //build a new eval context based off the current one
            this.ctx = new EvaluatorContext();
            this.ctx.state = ctx.state;
            this.ctx.stack = Pct.clone(ctx.stack);
        }

        getOutput() {
            return this.ctx.out;
        }

        write(str: string) {
            this.ctx.out += str;
            return this;
        }

        render(ast: Ast.Body) {
            if (ast != null) this.evaluator.evaluateBody(ast, this.ctx);
            return this;
        }
    }

    export class HandlerContext {
        constructor(private evaluator: Evaluator, private ctx: EvaluatorContext) {
        }

        get(str: string) {
            var ident = new Ast.Identifier(-1);
            ident.key = str;
            return this.evaluator.resolveIdentifierFromStack(ident, this.ctx);
        }

        current() {
            return this.ctx.state.value;
        }

        tap(callback: (data: any) => any) {
            //TODO
        }
    }

    export class HandlerBodies implements ArrayAccess {
        public block: Ast.Body;

        constructor(private section: Ast.Section) {
            this.block = section.body;
        }

        offsetExists(offset: string) {
            return this[offset] != null;
        }

        offsetGet(offset: string): Ast.Body {
            for (var i = 0; i < this.section.bodies.length; i++) {
                if (this.section.bodies[i].key == offset) {
                    return this.section.bodies[i].body;
                }
            }
            return null;
        }

        offsetSet(offset: any, value: any) {
            throw new EvaluateException(this.section, 'Unsupported set on bodies');
        }

        offsetUnset(offset: any) {
            throw new EvaluateException(this.section, 'Unsupported unset on bodies');
        }
    }

    export class HandlerParameters {
        constructor(private state: EvaluatorState) {
        }

        __get(name: string) {
            if (isset(this.state.params[name])) return this.state.params[name];
            return null;
        }

        __isset(name: string) {
            return array_key_exists(name, this.state.params);
        }
    }
}