///<reference path="common.ts" />

module Dust.Helper {

    export class Select {
        __invoke(chunk: Evaluate.Chunk, context: Evaluate.Context, bodies: Evaluate.Bodies, params: Evaluate.Parameters) {
            //evaluate key here
            if (!isset(params['key'])) chunk.setError('Key parameter required');
            var key = params['key'];
            //just eval body with some special state
            return chunk.render(bodies.block, context.pushState(new Evaluate.State({
                '__selectInfo': {
                    'selectComparisonSatisfied': false,
                    'key': key
                }
            })));
        }
    }

    export class Math {
        __invoke(chunk: Evaluate.Chunk, context: Evaluate.Context, bodies: Evaluate.Bodies) {
            var result = context.get('key');
            if (result === null) chunk.setError('Key required');
            var method = context.get('method');
            if (method === null) chunk.setError('Method required');
            var operand = context.get('operand');
            switch (method) {
                case 'add':
                    <number>result += <number>operand;
                    break;
                case 'subtract':
                    result -= operand;
                    break;
                case 'multiply':
                    result *= operand;
                    break;
                case 'divide':
                    result /= operand;
                    break;
                case 'mod':
                    result %= operand;
                    break;
                case 'abs':
                    result = abs(result);
                    break;
                case 'floor':
                    result = floor(result);
                    break;
                case 'ceil':
                    result = ceil(result);
                    break;
                default:
                    chunk.setError('Unknown method: ' + method);
            }
            //no bodies means just write
            if (bodies == null || bodies.block == null) return chunk.write(result);
            else {
                //just eval body with some special state
                return chunk.render(bodies.block, context.pushState(new Evaluate.State({
                    '__selectInfo': { 'selectComparisonSatisfied': false },
                    'key': result
                })));
            }
        }
    }

    export class Comparison {
        __invoke(chunk: Evaluate.Chunk, context: Evaluate.Context,
                bodies: Evaluate.Bodies, params: Evaluate.Parameters) {
            //load value
            if (!isset(params['value'])) chunk.setError('Value parameter required');
            var value = params['value'];
            //load select info
            var selectInfo = context.get('__selectInfo');
            //load key
            var key = null;
            if (isset(params['key'])) key = params['key'];
            else if (selectInfo != null) key = selectInfo.key;
            else chunk.setError('Must be in select or have key parameter');
            //check
            if (this.isValid(key, value)) {
                if (selectInfo != null) {
                    selectInfo.selectComparisonSatisfied = true;
                }
                return chunk.render(bodies.block, context);
            } else if (isset(bodies['else'])) {
                return chunk.render(bodies['else'], context);
            } else return chunk;
        }

        isValid(key: any, value: any) { return false; }
    }

    export class Eq extends Comparison {
        isValid(key: any, value: any) { return key == value; }
    }

    export class Lt extends Comparison {
        isValid(key: any, value: any) { return key < value; }
    }

    export class Lte extends Comparison {
        isValid(key: any, value: any) { return key <= value; }
    }

    export class Gt extends Comparison {
        isValid(key: any, value: any) { return key > value; }
    }

    export class Gte extends Comparison {
        isValid(key: any, value: any) { return key >= value; }
    }

    export class DefaultHelper {
        __invoke(chunk: Evaluate.Chunk, context: Evaluate.Context, bodies: Evaluate.Bodies) {
            var selectInfo = context.get('__selectInfo');
            if (selectInfo == null) chunk.setError('Default must be inside select');
            //check
            if (!selectInfo.selectComparisonSatisfied) {
                return chunk.render(bodies.block, context);
            } else return chunk;
        }
    }

    export class IfHelper {
        __invoke(chunk: Evaluate.Chunk, context: Evaluate.Context, bodies: Evaluate.Bodies) {
            //scary and dumb! won't include in default...
            var cond = context.get('cond');
            if (cond === null) chunk.setError('Unable to find cond for if');
            if (eval('return ' + cond + ';')) return chunk.render(bodies.block, context);
            else if (isset(bodies['else'])) {
                return chunk.render(bodies['else'], context);
            } else return chunk;
        }
    }

    export class Sep {
        __invoke(chunk: Evaluate.Chunk, context: Evaluate.Context, bodies: Evaluate.Bodies) {
            var iterationCount = context.get('$iter');
            if (iterationCount === null) chunk.setError('Sep must be inside array');
            var len = context.get('$len');
            if (iterationCount < len - 1) return chunk.render(bodies.block, context);
            else return chunk;
        }
    }

    export class Size {
        __invoke(chunk: Evaluate.Chunk, context: Evaluate.Context, bodies: Evaluate.Bodies, params: Evaluate.Parameters) {
            if (!isset(params['key'])) chunk.setError('Parameter required: key');
            var key = params['key'];
            if (is_null(key)) return chunk.write('0');
            if (is_numeric(key)) return chunk.write(key);
            if (is_string(key)) return chunk.write(strlen(key));
            if (is_array(key) || key instanceof Countable) return chunk.write(count(key));
            if (is_object(key)) return chunk.write(count(get_object_vars(key)));
            return chunk.write(strlen(strval(key)));
        }
    }

    export class ContextDump {
        __invoke(chunk: Evaluate.Chunk, context: Evaluate.Context, bodies: Evaluate.Bodies, params: Evaluate.Parameters) {
            //get config
            var current = !isset(params['key']) || params['key'] != 'full';
            var output = !isset(params['to']) || params['to'] != 'console';
            //ok, basically we're gonna give parent object w/ two extra values, "__forcedParent__", "__child__", and "__params__"
            var getContext = (ctx: Evaluate.Context) => {
                //first parent
                var parent = !current && ctx.parent != null ? getContext(ctx.parent) : { };
                //now start adding pieces
                parent.__child__ = ctx.head == null ? null : ctx.head.value;
                if (ctx.head != null && ctx.head.forcedParent !== null) {
                    parent.__forcedParent__ = ctx.head.forcedParent;
                }
                if (ctx.head != null && !empty(ctx.head.params)) {
                    parent.__params__ = ctx.head.params;
                }
                return parent;
            };
            //now json_encode
            var str = context.parent == null ? '{ }' : json_encode(getContext(context.parent), JSON_PRETTY_PRINT);
            //now put where necessary
            if (output) return chunk.write(str);
            echo(str + '\n');
            return chunk;
        }
    }
}