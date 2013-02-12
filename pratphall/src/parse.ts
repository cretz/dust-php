///<reference path="common.ts" />

module Dust.Parse {

    export class ParserOptions {

    }

    export class ParserContext {
        offset: number = 0;
        offsetTransactionStack: number[] = [];

        constructor(public str: string) { }

        beginTransaction() {
            this.offsetTransactionStack.push(this.offset);
        }

        rollbackTransaction() {
            this.offset = this.offsetTransactionStack.pop();
        }

        commitTransaction() {
            this.offsetTransactionStack.pop();
        }

        getCurrentLineAndCol() {
            return this.getLineAndColFromOffset(this.offset);
        }

        getLineAndColFromOffset(offset: number) {
            var line = 0;
            var prev = -1;
            while (true) {
                var newPrev = strpos(this.str, '\n', prev + 1);
                if (Pct.isFalse(newPrev) || newPrev > offset) break;
                prev = newPrev;
                line++;
            }
            if (prev == -1) prev = 0;
            return {
                line: line + 1,
                col: offset - prev
            };
        }

        peek(offset = 1) {
            if (this.str.length <= this.offset + (offset - 1)) return null;
            return this.str.charAt(this.offset + (offset - 1));
        }

        next() {
            this.offset++;
            if (this.str.length <= (this.offset - 1)) return null;
            return this.str.charAt((this.offset - 1));
        }

        skipWhitespace() {
            var found = false;
            while (in_array(this.peek(), [' ', '\t', '\v', '\f', '\r', '\n'])) {
                this.offset++;
                found = true;
            }
            return found;
        }
    }

    export class ParseException extends DustException {
        constructor(message: string, public line: number, public col: number) {
            super('(' + line + ',' + col + ') ' + message);
        }
    }

    export class Parser {
        constructor(public options = new ParserOptions()) { }

        error(message: string, ctx: ParserContext) {
            //find line and col
            var loc = ctx.getCurrentLineAndCol();
            throw new ParseException(message, loc.line, loc.col);
        }

        parse(str: string) {
            var ctx = new ParserContext(str);
            var ret = this.parseBody(ctx);
            if (ctx.offset != ctx.str.length) this.error('Unexpected character', ctx);
            return ret;
        }

        parseBody(ctx: ParserContext): Ast.Body {
            var body = new Ast.Body(ctx.offset);
            body.parts = [];
            while (ctx.offset < ctx.str.length) {
                var part = this.parsePart(ctx);
                if (part == null) break;
                body.parts.push(part);
            }
            if (body.parts.length == 0) return null;
            return body;
        }

        parsePart(ctx: ParserContext) {
            var part: Ast.Part = this.parseComment(ctx);
            if (part == null) part = this.parseSection(ctx);
            if (part == null) part = this.parsePartial(ctx);
            if (part == null) part = this.parseSpecial(ctx);
            if (part == null) part = this.parseReference(ctx);
            if (part == null) part = this.parseBuffer(ctx);
            return part;
        }

        parseSection(ctx: ParserContext) {
            if (ctx.peek() != '{') return null;
            var type = ctx.peek(2);
            if (!in_array(type, Ast.Section.acceptableTypes)) return null;
            ctx.beginTransaction();
            var sec = new Ast.Section(ctx.offset);
            sec.type = type;
            ctx.offset += 2;
            ctx.skipWhitespace();
            //can't handle quote
            if (type == '+' && ctx.peek() == '"') {
                ctx.rollbackTransaction();
                return null;
            }
            ctx.commitTransaction();
            sec.identifier = this.parseIdentifier(ctx);
            if (sec.identifier == null) this.error('Expected identifier', ctx);
            sec.context = this.parseContext(ctx);
            sec.parameters = this.parseParameters(ctx);
            ctx.skipWhitespace();
            if (ctx.peek() == '/' && ctx.peek(2) == '}') {
                ctx.offset += 2;
                return sec;
            }
            if (ctx.next() != '}') this.error('Missing end brace', ctx);
            sec.body = this.parseBody(ctx);
            sec.bodies = this.parseBodies(ctx);
            if (ctx.next() != '{') this.error('Missing end tag', ctx);
            if (ctx.next() != '/') this.error('Missing end tag', ctx);
            ctx.skipWhitespace();
            var end = this.parseIdentifier(ctx);
            if (end == null || end.toString() != sec.identifier.toString()) {
                this.error('Expecting end tag for ' + sec.identifier, ctx);
            }
            ctx.skipWhitespace();
            if (ctx.next() != '}') this.error('Missing end brace', ctx);
            return sec;
        }

        parseContext(ctx: ParserContext) {
            if (ctx.peek() != ':') return null;
            var context = new Ast.Context(ctx.offset);
            ctx.offset++;
            context.identifier = this.parseIdentifier(ctx);
            if (context.identifier == null) this.error('Expected identifier', ctx);
            return context;
        }

        parseParameters(ctx: ParserContext) {
            var params: Ast.Parameter[] = [];
            while (true) {
                ctx.beginTransaction();
                if (!ctx.skipWhitespace()) break;
                var beginOffset = ctx.offset;
                var key = this.parseKey(ctx);
                if (key == null) break;
                if (ctx.peek() != '=') break;
                ctx.offset++;
                //different possible types...
                var numVal = this.parseNumber(ctx);
                var param: Ast.Parameter;
                if (numVal != null) {
                    param = new Ast.NumericParameter(beginOffset);
                    (<Ast.NumericParameter>param).value = numVal;
                } else {
                    var identVal = this.parseIdentifier(ctx);
                    if (identVal != null) {
                        param = new Ast.IdentifierParameter(beginOffset);
                        (<Ast.IdentifierParameter>param).value = identVal;
                    } else {
                        var inlineVal = this.parseInline(ctx);
                        if (inlineVal != null) {
                            param = new Ast.InlineParameter(beginOffset);
                            (<Ast.InlineParameter>param).value = inlineVal;
                        } else break;
                    }
                }
                param.key = key;
                params.push(param);
                ctx.commitTransaction();
            }
            ctx.rollbackTransaction();
            return params;
        }

        parseBodies(ctx: ParserContext) {
            var lists: Ast.BodyList[] = [];
            while (true) {
                if (ctx.peek() != '{') break;
                if (ctx.peek(2) != ':') break;
                var list = new Ast.BodyList(ctx.offset);
                ctx.offset += 2;
                list.key = this.parseKey(ctx);
                if (list.key == null) this.error('Expected key', ctx);
                if (ctx.next() != '}') this.error('Expected end brace', ctx);
                list.body = this.parseBody(ctx);
                lists.push(list);
            }
            return lists;
        }

        parseReference(ctx: ParserContext) {
            if (ctx.peek() != '{') return null;
            ctx.beginTransaction();
            var ref = new Ast.Reference(ctx.offset);
            ctx.offset++;
            ref.identifier = this.parseIdentifier(ctx);
            if (ref.identifier == null) {
                ctx.rollbackTransaction();
                return null;
            }
            ctx.commitTransaction();
            ref.filters = this.parseFilters(ctx);
            if (ctx.next() != '}') this.error('Expected end brace', ctx);
            return ref;
        }

        parsePartial(ctx: ParserContext) {
            if (ctx.peek() != '{') return null;
            var type = ctx.peek(2);
            if (type != '>' && type != '+') return null;
            var partial = new Ast.Partial(ctx.offset);
            ctx.offset += 2;
            partial.type = type;
            partial.key = this.parseKey(ctx);
            if (partial.key == null) partial.inline = this.parseInline(ctx);
            partial.context = this.parseContext(ctx);
            partial.parameters = this.parseParameters(ctx);
            ctx.skipWhitespace();
            if (ctx.next() != '/' || ctx.next() != '}') this.error('Expected end of tag', ctx);
            return partial;
        }

        parseFilters(ctx: ParserContext) {
            var filters: Ast.Filter[] = [];
            while (true) {
                if (ctx.peek() != '|') break;
                var filter = new Ast.Filter(ctx.offset);
                ctx.offset++;
                filter.key = this.parseKey(ctx);
                if (filter.key == null) this.error('Expected filter key', ctx);
                filters.push(filter);
            }
            return filters;
        }

        parseSpecial(ctx: ParserContext) {
            if (ctx.peek() != '{' || ctx.peek(2) != '~') return null;
            var special = new Ast.Special(ctx.offset);
            ctx.offset += 2;
            special.key = this.parseKey(ctx);
            if (special.key == null) this.error('Expected key', ctx);
            if (ctx.next() != '}') this.error('Expected ending brace', ctx);
            return special;
        }

        parseIdentifier(ctx: ParserContext, couldHaveNumber = false) {
            ctx.beginTransaction();
            var ident = new Ast.Identifier(ctx.offset);
            ident.preDot = ctx.peek() == '.';
            if (ident.preDot) ctx.offset++;
            ident.key = this.parseKey(ctx);
            if (ctx.peek() == '[') {
                ctx.offset++;
                ident.arrayAccess = this.parseIdentifier(ctx, true);
                if (ident.arrayAccess == null) this.error('Expected array index', ctx);
                if (ctx.next() != ']') this.error('Expected ending bracket', ctx);
            } else if (!ident.preDot && ident.key == null) {
                if (couldHaveNumber) {
                    ident.number = this.parseInteger(ctx);
                } else {
                    ctx.rollbackTransaction();
                    return null;
                }
            }
            ident.next = this.parseIdentifier(ctx, false);
            ctx.commitTransaction();
            return ident;
        }

        parseKey(ctx: ParserContext) {
            //first char has to be letter, _, or $
            //all others can be num, letter, _, $, or -
            var key = '';
            while (true) {
                var chr = ctx.peek();
                if (ctype_alpha(chr) || chr == '_' || chr == '$') key += chr;
                else if (key != '' && (ctype_digit(chr) || chr == '-')) key += chr;
                else if (key == '') return null;
                else return key;
                ctx.offset++;
            }
        }

        parseNumber(ctx: ParserContext) {
            var str = this.parseInteger(ctx);
            if (str == null) return null;
            if (ctx.peek() == '.') {
                ctx.offset++;
                var next = this.parseInteger(ctx);
                if (next == null) this.error('Expecting decimal contents', ctx);
                return str + '.' + next;
            } else return str;
        }

        parseInteger(ctx: ParserContext) {
            var str = '';
            while (ctype_digit(ctx.peek())) str += ctx.next();
            return str.length == 0 ? null : str;
        }

        parseInline(ctx: ParserContext) {
            if (ctx.peek() != '"') return null;
            var inline = new Ast.Inline(ctx.offset);
            inline.parts = [];
            ctx.offset++;
            while (true) {
                var part: Ast.InlinePart = this.parseSpecial(ctx);
                if (part == null) part = this.parseReference(ctx);
                if (part == null) part = this.parseLiteral(ctx);
                if (part == null) break;
                inline.parts.push(part);
            }
            if (ctx.next() != '"') this.error('Expecting ending quote', ctx);
            return inline;
        }

        parseBuffer(ctx: ParserContext) {
            //some speedup here...
            var eol = strpos(ctx.str, '\n', ctx.offset);
            if (Pct.isFalse(eol)) eol = ctx.str.length;
            var line = ctx.str.substr(ctx.offset, eol - ctx.offset);
            //go through string and make sure there's not a tag or comment
            var possibleEnd = -1;
            while (true) {
                possibleEnd = strpos(line, '{', possibleEnd + 1);
                if (Pct.isFalse(possibleEnd)) break;
                if (this.isTag(line, possibleEnd) || this.isComment(line, possibleEnd)) {
                    line = line.substr(0, possibleEnd);
                    break;
                }
            }
            if (empty(line) && (Pct.isFalse(eol) || Pct.isNotFalse(possibleEnd))) return null;
            var buffer = new Ast.Buffer(ctx.offset);
            buffer.contents = line;
            ctx.offset += line.length;
            //we ended at eol? then skip it but add it
            if (ctx.peek() == '\n') {
                ctx.offset++;
                buffer.contents += '\n';
            }
            return buffer;
        }

        isTag(str: string, offset: number) {
            //needs at least this length
            if (str.length - offset < 3) return false;
            //needs to start with brace
            if (str.charAt(offset) != '{') return false;
            //an ending brace needs to be somewhere
            var endingBrace = strpos(str, '}', offset + 1);
            if (Pct.isFalse(endingBrace)) return false;
            //next non whitespace
            var curr = offset + 1;
            while (str.length > curr && Pct.isNotFalse(strpos(' \t\v\f', str.charAt(curr)))) curr++;
            if (str.length <= curr) return false;
            //so, it does start with one of these?
            if (Pct.isFalse(strpos('#?^><+%:@/~%', str.charAt(curr)))) {
                //well then just check for any reference
                var newCtx = new ParserContext(str);
                newCtx.offset = curr - 1;
                if (this.parseReference(newCtx) == null) return false;
            }
            //so now it's all down to whether there is a closing brace
            return Pct.isNotFalse(strpos(str, '}', curr));
        }

        isComment(str: string, offset: number) {
            //for now, just assume the start means it's on
            return str.length - offset > 1 && str.charAt(offset) == '{' && str.charAt(offset + 1) == '!';
        }

        parseLiteral(ctx: ParserContext) {
            //empty is not a literal
            if (ctx.peek() == '"') return null;
            //find first non-escaped quote
            var endQuote = ctx.offset;
            do {
                endQuote = strpos(ctx.str, '"', endQuote + 1);
            } while (Pct.isNotFalse(endQuote) && ctx.str.charAt(endQuote - 1) == '\\');
            //missing end quote is a problem
            if (Pct.isFalse(endQuote)) this.error('Missing end quote', ctx);
            //see if there are any tags in between the current offset and the first quote
            var possibleTag = ctx.offset - 1;
            do {
                possibleTag = strpos(ctx.str, '{', possibleTag + 1);
            } while (Pct.isNotFalse(possibleTag) && possibleTag < endQuote && !this.isTag(ctx.str, possibleTag));
            //substring it
            var endIndex = endQuote;
            if (Pct.isNotFalse(possibleTag) && possibleTag < endQuote) endIndex = possibleTag;
            //empty literal means no literal
            if (endIndex == ctx.offset) return null;
            var literal = new Ast.InlineLiteral(ctx.offset);
            literal.value = ctx.str.substr(ctx.offset, endIndex - ctx.offset);
            ctx.offset += literal.value.length;
            return literal;
        }

        parseComment(ctx: ParserContext) {
            if (ctx.peek() != '{' || ctx.peek(2) != '!') return null;
            var comment = new Ast.Comment(ctx.offset);
            ctx.offset += 2;
            var endIndex = strpos(ctx.str, '!}', ctx.offset);
            if (Pct.isFalse(endIndex)) this.error('Missing end of comment', ctx);
            comment.contents = ctx.str.substr(ctx.offset + 2, endIndex - ctx.offset - 4);
            ctx.offset = endIndex + 2;
            return comment;
        }
    }
}