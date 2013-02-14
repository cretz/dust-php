///<reference path="common.ts" />

module Dust.Evaluate {

    export interface EvaluationCallback extends Pct.CompileTimeOnly {
        (chunk?: Chunk, context?: Context, bodies?: Bodies, params?: Parameters): any;
    }

    export class EvaluatorOptions {
    }

    export class EvaluateException extends Exception {
        constructor(public ast?: Ast.Ast, message?: string) {
            super(message);
        }
    }

    export class Evaluator {

        constructor(public dust: Dust, public options = new EvaluatorOptions()) {
        }

        error(ast?: Ast.Ast, message?: string) {
            throw new EvaluateException(ast, message);
        }

        evaluate(source: Ast.Body, state: any) {
            return trim(this.evaluateBody(source, new Context(this, null, new State(state)), new Chunk(this)).out);
        }

        evaluateBody(body: Ast.Body, ctx: Context, chunk: Chunk) {
            //go ahead and set the file path on the current context
            ctx.currentFilePath = body.filePath;
            body.parts.forEach((part: Ast.Part) => {
                if (part instanceof Ast.Comment) { }
                else if (part instanceof Ast.Section) chunk = this.evaluateSection(<Ast.Section>part, ctx, chunk);
                else if (part instanceof Ast.Partial) chunk = this.evaluatePartial(<Ast.Partial>part, ctx, chunk);
                else if (part instanceof Ast.Special) chunk = this.evaluateSpecial(<Ast.Special>part, ctx, chunk);
                else if (part instanceof Ast.Reference) chunk = this.evaluateReference(<Ast.Reference>part, ctx, chunk);
                else if (part instanceof Ast.Buffer) chunk = this.evaluateBuffer(<Ast.Buffer>part, ctx, chunk);
            });
            return chunk;
        }

        evaluateSection(section: Ast.Section, ctx: Context, chunk: Chunk) {
            //stuff that doesn't need resolution
            if (section.type == '+') {
                if (section.identifier.key == null) {
                    this.error(section.identifier, 'Evaluated identifier for partial not supported');
                }
                //mark beginning
                var block = chunk.markNamedBlockBegin(section.identifier.key);
                //render default contents
                if (section.body != null) {
                    chunk = this.evaluateBody(section.body, ctx, chunk);
                    //mark ending
                    chunk.markNamedBlockEnd(block);
                }
                //go ahead and try to replace
                chunk.replaceNamedBlock(section.identifier.key);
            } else if (section.type == '<') {
                if (section.identifier.key == null) {
                    this.error(section.identifier, 'Evaluated identifier for partial not supported');
                }
                chunk.setAndReplaceNamedBlock(section, ctx);
            } else if (section.type == '@') {
                if (section.identifier.key == null) {
                    this.error(section.identifier, 'Evaluated identifier for helper not supported');
                }
                //do we have the helper?
                if (!isset(this.dust.helpers[section.identifier.key])) {
                    this.error(section.identifier, 'Unable to find helper');
                }
                var helper = this.dust.helpers[section.identifier.key];
                //build state w/ no current value
                var state = new State(null);
                //do we have an explicit context?
                if (section.context != null) {
                    state.forcedParent = ctx.resolve(section.context.identifier);
                }
                //how about params?
                if (!empty(section.parameters)) {
                    state.params = this.evaluateParameters(section.parameters, ctx);
                }
                //now run the helper
                chunk = this.handleCallback(ctx.pushState(state), helper, chunk, section);
            } else {
                //build a new state set
                var resolved = ctx.resolve(section.identifier);
                //build state if not empty
                var state = new State(resolved);
                //do we have an explicit context?
                if (section.context != null) {
                    state.forcedParent = ctx.resolve(section.context.identifier);
                }
                //how about params?
                if (!empty(section.parameters)) {
                    state.params = this.evaluateParameters(section.parameters, ctx);
                }
                //normalize resolution
                resolved = this.normalizeResolved(ctx.pushState(state), resolved, chunk, section);
                //do the needful per type
                switch (section.type) {
                    case '#':
                        //empty means try else
                        if (this.isEmpty(resolved)) {
                            chunk = this.evaluateElseBody(section, ctx, chunk);
                        } else if (is_array(resolved) || resolved instanceof Traversable) {
                            //array means loop
                            var iterationCount = -1;
                            (<any[]>resolved).forEach((value: any, index: any) => {
                                //run body
                                chunk = this.evaluateBody(section.body, ctx.push(
                                    value, index, (<any[]>resolved).length, ++iterationCount), chunk);
                            });
                        } else if (resolved instanceof Chunk) {
                            chunk = <Chunk>resolved;
                        } else {
                            //otherwise, just do the body
                            chunk = this.evaluateBody(section.body, ctx.pushState(state), chunk);
                        }
                        break;
                    case '?':
                        //only if it exists
                        if (this.exists(resolved)) {
                            chunk = this.evaluateBody(section.body, ctx, chunk);
                        } else {
                            chunk = this.evaluateElseBody(section, ctx, chunk);
                        }
                        break;
                    case '^':
                        //only if it doesn't exist
                        if (!this.exists(resolved)) {
                            chunk = this.evaluateBody(section.body, ctx, chunk);
                        } else {
                            chunk = this.evaluateElseBody(section, ctx, chunk);
                        }
                        break;
                    default:
                        throw new EvaluateException(section, 'Unrecognized type: ' + section.type);
                }
            }
            return chunk;
        }

        evaluateElseBody(section: Ast.Section, ctx: Context, chunk: Chunk) {
            if (section.bodies != null && section.bodies.length > 0) {
                section.bodies.forEach((value: Ast.BodyList) => {
                    if (value.key == 'else') {
                        chunk = this.evaluateBody(value.body, ctx, chunk);
                    }
                });
            }
            return chunk;
        }

        evaluatePartial(partial: Ast.Partial, ctx: Context, chunk: Chunk) {
            var partialName = partial.key;
            if (partialName == null) partialName = this.toDustString(this.normalizeResolved(ctx, partial.inline, chunk));
            if (partialName == null) return chunk;
            //+ is a named block
            if (partial.type == '+') {
                //mark beginning
                chunk.markNamedBlockBegin(partialName);
                //go ahead and try to replace
                chunk.replaceNamedBlock(partialName);
                return chunk;
            }
            //otherwise, we're >
            //get base directory
            var basePath = ctx.currentFilePath;
            if (basePath != null) basePath = dirname(basePath);
            //load partial
            var partialBody = this.dust.loadTemplate(partialName, basePath);
            if (partialBody == null) return chunk;
            //null main state
            var state = new State(null);
            //partial context?
            if (partial.context != null) {
                state.forcedParent = ctx.resolve(partial.context.identifier);
            }
            //params?
            if (!empty(partial.parameters)) {
                state.params = this.evaluateParameters(partial.parameters, ctx);
            }
            //render the partial then
            return this.evaluateBody(partialBody, ctx.pushState(state), chunk);
        }

        evaluateSpecial(spl: Ast.Special, ctx: Context, chunk: Chunk) {
            switch (spl.key) {
                case 'n':
                    chunk.write('\n');
                    break;
                case 'r':
                    chunk.write('\r');
                    break;
                case 'lb':
                    chunk.write('{');
                    break;
                case 'rb':
                    chunk.write('}');
                    break;
                case 's':
                    chunk.write(' ');
                    break;
                default:
                    throw new EvaluateException(spl, 'Unrecognized special: ' + spl.key);
            }
            return chunk;
        }

        evaluateReference(ref: Ast.Reference, ctx: Context, chunk: Chunk) {
            //resolve
            var resolved = this.normalizeResolved(ctx, ctx.resolve(ref.identifier), chunk);
            if (!this.isEmpty(resolved)) {
                if (resolved instanceof Chunk) {
                    return <Chunk>resolved;
                }
                //make the string
                if (empty(ref.filters)) {
                    //default filters
                    resolved = this.dust.automaticFilters.reduce((prev: any, filter: Filter.Filter) => {
                        return filter.apply(prev);
                    }, resolved);
                } else {
                    //apply filters in order...
                    resolved = ref.filters.reduce((prev: any, curr: Ast.Filter) => {
                        if (array_key_exists(curr.key, this.dust.filters)) {
                            var filter = <Filter.Filter>this.dust.filters[curr.key];
                            return filter.apply(prev);
                        } else return prev;
                    }, resolved);
                }
                chunk.write(this.toDustString(resolved));
            }
            return chunk;
        }

        evaluateBuffer(buffer: Ast.Buffer, ctx: Context, chunk: Chunk) {
            chunk.write(buffer.contents);
            return chunk;
        }

        evaluateParameters(params: Ast.Parameter[], ctx: Context) {
            var ret = Pct.newAssocArray();
            params.forEach((value: Ast.Parameter) => {
                if (value instanceof Ast.NumericParameter) {
                    if (Pct.isFalse(strpos((<Ast.NumericParameter>value).value, '.'))) {
                        ret[value.key] = intval((<Ast.NumericParameter>value).value);
                    } else ret[value.key] = floatval((<Ast.NumericParameter>value).value);
                } else if (value instanceof Ast.IdentifierParameter) {
                    ret[value.key] = ctx.resolve((<Ast.IdentifierParameter>value).value);
                } else {
                    //we just set this as the actual AST since it is resolved where it's emitted
                    ret[value.key] = (<Ast.InlineParameter>value).value;
                }
            });
            return ret;
        }

        normalizeResolved(ctx: Context, resolved: any, chunk: Chunk, section?: Ast.Section) {
            var handledSpecial = true;
            while (handledSpecial) {
                if (is_callable(resolved)) {
                    //call callback
                    resolved = this.handleCallback(ctx, resolved, chunk, section);
                } else if (resolved instanceof Ast.Inline) {
                    //resolve full inline parameter
                    var newChunk = chunk.newChild();
                    (<Ast.Inline>resolved).parts.forEach((value: Ast.InlinePart) => {
                        if (value instanceof Ast.Reference) newChunk = this.evaluateReference(<Ast.Reference>value, ctx, newChunk);
                        else if (value instanceof Ast.Special) newChunk = this.evaluateSpecial(<Ast.Special>value, ctx, newChunk);
                        else newChunk.write(strval(value));
                    });
                    resolved = newChunk.out;
                    break;
                } else {
                    handledSpecial = false;
                }
            }
            return resolved;
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
            if (is_array(val)) return implode(',', val);
            if (is_object(val) && !method_exists(val, '__toString')) return get_class(val);
            return Pct.castString(val);
        }

        handleCallback(ctx: Context, callback: EvaluationCallback, chunk: Chunk, section?: Ast.Section) {
            //reset "this" on closures
            if (callback instanceof Closure) {
                //find non-closure new "this"
                var newThis = ctx.head.value;
                if (newThis instanceof Closure) {
                    //forced parent?
                    if (ctx.head.forcedParent !== null) newThis = ctx.head.forcedParent;
                    else if (ctx.parent !== null) newThis = ctx.parent.head.value;
                }
                //must be non-closure object
                if (is_object(newThis) && !(newThis instanceof Closure)) {
                    callback = <EvaluationCallback><any>Closure.bind(<Closure><any>callback, newThis);
                }
            }
            var reflected: ReflectionFunctionAbstract;
            if (is_object(callback) && method_exists(callback, '__invoke')) {
                reflected = new ReflectionMethod(callback, '__invoke');
            } else {
                reflected = new ReflectionFunction(callback);
            }
            var paramCount = reflected.getNumberOfParameters();
            var args = [];
            if (paramCount > 0) {
                args.push(chunk);
                if (paramCount > 1) {
                    args.push(ctx);
                    if (paramCount > 2 && section != null) {
                        args.push(new Bodies(section));
                        if (paramCount > 3) {
                            args.push(new Parameters(this, ctx));
                        }
                    }
                }
            }
            //invoke
            return call_user_func_array(callback, args);
        }
    }

    declare export interface PendingNamedBlock extends Pct.CompileTimeOnly {
        begin: number;
        end: number;
    }

    export class Chunk {
        out = '';
        tapStack: { (data: string): string; }[];
        //keyed by name, value has 'begin' and nullable 'end'
        pendingNamedBlocks = Pct.newAssocArray();
        pendingNamedBlockOffset = 0;
        //keyed by name, value is string
        setNamedStrings = Pct.newAssocArray();

        constructor(public evaluator: Evaluator) {
        }

        newChild() {
            var chunk = new Chunk(this.evaluator);
            chunk.tapStack = Pct.byRef(this.tapStack);
            chunk.pendingNamedBlocks = Pct.byRef(this.pendingNamedBlocks);
            return chunk;
        }

        write(str: any) {
            this.out += str;
            return this;
        }

        markNamedBlockBegin(name: string) {
            if (!array_key_exists(name, this.pendingNamedBlocks)) {
                this.pendingNamedBlocks[name] = [];
            }
            var block = { begin: this.out.length, end: null };
            (<PendingNamedBlock[]>this.pendingNamedBlocks[name]).push(block);
            return block;
        }

        markNamedBlockEnd(block: PendingNamedBlock) {
            block.end = this.out.length;
        }

        replaceNamedBlock(name: string) {
            //we need to replace inside of chunk the begin/end
            if (array_key_exists(name, this.pendingNamedBlocks) &&
                    array_key_exists(name, this.setNamedStrings)) {
                var namedString = <string>this.setNamedStrings[name];
                //get all blocks
                var blocks = <PendingNamedBlock[]>this.pendingNamedBlocks[name];
                //we need to reverse the order to replace backwards first to keep line counts right
                usort(blocks, (a: PendingNamedBlock, b: PendingNamedBlock) => {
                    return a.begin > b.begin ? -1 : 1;
                });
                //hold on to pre-count
                var preCount = this.out.length;
                //loop and splice string
                blocks.forEach((value: PendingNamedBlock) => {
                    var text = this.out.substr(0, value.begin + this.pendingNamedBlockOffset) + namedString;
                    if (value.end != null) text += this.out.substr(value.end + this.pendingNamedBlockOffset);
                    else text += this.out.substr(value.begin + this.pendingNamedBlockOffset);
                    this.out = text;
                });
                //now we have to update all the pending offset
                this.pendingNamedBlockOffset += this.out.length - preCount;
            }
        }

        setAndReplaceNamedBlock(section: Ast.Section, ctx: Context) {
            var output = ''
            //if it has no body, we don't do anything
            if (section != null && section.body != null) {
                //run the body
                output = this.evaluator.evaluateBody(section.body, ctx, this.newChild()).out;
            }
            //save it
            this.setNamedStrings[section.identifier.key] = output;
            //try and replace
            this.replaceNamedBlock(section.identifier.key);
        }

        setError(error: string, ast?: Ast.Body) {
            this.evaluator.error(ast, error);
            return this;
        }

        render(ast: Ast.Body, context: Context) {
            var text = this;
            if (ast != null) {
                var text = this.evaluator.evaluateBody(ast, context, this);
                if (this.tapStack != null) {
                    this.tapStack.forEach((value: (data: string) => string) => {
                        text.out = value(text.out);
                    });
                }
            }
            return text;
        }

        tap(callback: (data: string) => string) {
            this.tapStack.push(callback);
            return this;
        }

        untap() {
            this.tapStack.pop();
            return this;
        }
    }

    export class Context {

        currentFilePath: string;

        constructor(public evaluator: Evaluator, public parent?: Context, public head?: State) {
            if (parent != null) this.currentFilePath = parent.currentFilePath;
        }

        get(str: string) {
            var ident = new Ast.Identifier(-1);
            ident.key = str;
            var resolved = this.resolve(ident);
            resolved = this.evaluator.normalizeResolved(this, resolved, new Chunk(this.evaluator));
            if (resolved instanceof Chunk) return resolved.out;
            return resolved;
        }

        push(head: any, index?: any, length?: number, iterationCount?: number) {
            var state = new State(head);
            if (index !== null) state.params['$idx'] = index;
            if (length !== null) state.params['$len'] = length;
            if (iterationCount !== null) state.params['$iter'] = iterationCount;
            return this.pushState(state);
        }

        pushState(head: State) {
            return new Context(this.evaluator, this, head);
        }

        resolve(identifier: Ast.Identifier, forceArrayLookup = false, mainValue = this.head.value) {
            //try local
            var resolved = this.resolveLocal(identifier, mainValue, forceArrayLookup);
            //forced local?
            if (identifier.preDot) return resolved;
            //if it's not there, we can try the forced parent
            if (resolved === null && this.head.forcedParent) {
                resolved = this.resolveLocal(identifier, this.head.forcedParent, forceArrayLookup);
            }
            //if it's still not there, we can try parameters
            if (resolved === null && this.head.params.length > 0) {
                //just force an array lookup
                resolved = this.resolveLocal(identifier, this.head.params, true);
            }
            //not there and not forced parent? walk up
            if (resolved === null && this.head.forcedParent === null && this.parent != null) {
                resolved = this.parent.resolve(identifier, forceArrayLookup);
            }
            return resolved;
        }

        resolveLocal(identifier: Ast.Identifier, parentObject: any, forceArrayLookup = false) {
            var key: any = null;
            if (identifier.key != null) key = identifier.key;
            else if (identifier.number != null) {
                key = intval(identifier.number);
                //if this isn't an array lookup, just return the number
                if (!forceArrayLookup) return key;
            }
            var result = null;
            //no key, no array, but predot means result is just the parent
            if (key === null && identifier.preDot && identifier.arrayAccess == null) {
                result = parentObject;
            }
            //try to find on object (if we aren't forcing array lookup)
            if (!forceArrayLookup && key !== null) result = this.findInObject(key, parentObject);
            //now, try to find in array
            if (result === null && key !== null) result = this.findInArrayAccess(key, parentObject);
            //if it's there (or has predot) and has array access, try to get array child
            if (identifier.arrayAccess != null) {
                //find the key
                var arrayKey = this.resolve(identifier.arrayAccess, false, parentObject);
                if (arrayKey !== null) {
                    var keyIdent = new Ast.Identifier(-1);
                    if (is_numeric(arrayKey)) keyIdent.number = strval(arrayKey);
                    else keyIdent.key = Pct.castString(arrayKey);
                    //lookup by array key
                    if (result !== null) result = this.resolveLocal(keyIdent, result, true);
                    else if (identifier.preDot) result = this.resolveLocal(keyIdent, parentObject, true);
                }
            }
            //if it's there and has next, use it
            if (result !== null && identifier.next && !is_callable(result)) {
                result = this.resolveLocal(identifier.next, result);
            }
            return result;
        }

        findInObject(key: any, parent: any) {
            if (is_object(parent) && !is_numeric(key)) {
                //prop or method
                if (key in parent) {
                    return (<Object>parent)[key];
                } else if (method_exists(parent, key)) {
                    return (new ReflectionMethod(parent, key)).getClosure(parent);
                }
            } else return null;
        }

        findInArrayAccess(key: any, value: any) {
            if ((is_array(value) || value instanceof ArrayAccess) && isset((<any[]>value)[key])) {
                return (<any[]>value)[key];
            } else return null;
        }

        current() {
            if (this.head.forcedParent != null) return this.head.forcedParent;
            return this.head.value;
        }

        rebase(head: any) {
            return this.rebaseState(new State(head));
        }

        rebaseState(head: State) {
            //gotta get top parent
            var topParent = this;
            while (topParent.parent != null) topParent = topParent.parent;
            //now create
            return new Context(this.evaluator, topParent, head);
        }
    }

    export class State {
        forcedParent: any;
        params: Pct.PhpAssocArray = Pct.newAssocArray();

        constructor(public value: any) {

        }
    }

    export class Bodies implements ArrayAccess {
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

    export class Parameters {
        constructor(private evaluator: Evaluator, private ctx: Context) {
        }

        __get(name: string) {
            if (isset(this.ctx.head.params[name])) {
                var resolved = this.ctx.head.params[name];
                var newChunk = new Chunk(this.evaluator);
                resolved = this.evaluator.normalizeResolved(this.ctx, resolved, newChunk);
                if (resolved instanceof Chunk) return resolved.out;
                return resolved;
            }
            return null;
        }

        __isset(name: string) {
            return isset(this.ctx.head.params) && array_key_exists(name, this.ctx.head.params);
        }
    }
}