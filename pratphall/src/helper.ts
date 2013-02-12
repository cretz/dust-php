///<reference path="common.ts" />

module Dust.Helper {

    export class Select {
        __invoke(chunk: Evaluate.Chunk, context: Evaluate.Context, bodies: Evaluate.Bodies) {
            //just eval body with some special state
            return chunk.render(bodies.block, context.pushState(new Evaluate.State({
                '__selectInfo': { 'selectComparisonSatisfied': false }
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
            switch (key) {
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
                    chunk.setError('Unknown key: ' + result);
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
            if (!isset(params['value'])) chunk.setError('Value parameter required');
            //check
            if (this.isValid(context.get('key'), context.get('value'))) {
                var selectInfo = context.get('__selectInfo');
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
            if (eval(cond)) return chunk.render(bodies.block, context);
            else if (isset(bodies['else'])) {
                return chunk.render(bodies['else'], context);
            } else return chunk;
        }
    }

    export class Sep {
        __invoke(chunk: Evaluate.Chunk, context: Evaluate.Context, bodies: Evaluate.Bodies) {
            var index = context.get('idx');
            if (index === null) chunk.setError('Sep must be inside array');
            var len = context.get('len');
            if (index < len - 1) chunk.render(bodies.block, context);
            else return chunk;
        }
    }

    export class Size {
        __invoke(chunk: Evaluate.Chunk, context: Evaluate.Context) {
            var key = context.get('key');
            if (is_null(key)) return chunk;
            else if (is_numeric(key)) return chunk.write(key);
            else if (is_string(key)) return chunk.write(strlen(key));
            else if (is_array(key) || key instanceof Countable) return chunk.write(count(key));
            else if (is_object(key)) return chunk.write(count(get_object_vars(key)));
            else return chunk.write(strlen(strval(key)));
        }
    }
}