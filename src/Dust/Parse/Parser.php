<?php
namespace Dust\Parse;

use Dust\Ast;
class Parser {
    public $options;
    
    public function __construct($options = null) { if ($options === null) $options = new ParserOptions(); $this->options = $options; }
    
    public function error($message, ParserContext $ctx) {
        //find line and col
        $loc = $ctx->getCurrentLineAndCol();
        throw new ParseException($message, $loc->line, $loc->col);
    }
    
    public function parse($str) {
        $ctx = new ParserContext($str);
        $ret = $this->parseBody($ctx);
        if ($ctx->offset != strlen($ctx->str)) $this->error('Unexpected character', $ctx);
        return $ret;
    }
    
    public function parseBody(ParserContext $ctx) {
        $body = new Ast\Body($ctx->offset);
        $body->parts = [];
        while ($ctx->offset < strlen($ctx->str)) {
            $part = $this->parsePart($ctx);
            if ($part == null) break;
            $body->parts[] = $part;
        }
        if (count($body->parts) == 0) return null;
        return $body;
    }
    
    public function parsePart(ParserContext $ctx) {
        $part = $this->parseComment($ctx);
        if ($part == null) $part = $this->parseSection($ctx);
        if ($part == null) $part = $this->parsePartial($ctx);
        if ($part == null) $part = $this->parseSpecial($ctx);
        if ($part == null) $part = $this->parseReference($ctx);
        if ($part == null) $part = $this->parseBuffer($ctx);
        return $part;
    }
    
    public function parseSection(ParserContext $ctx) {
        if ($ctx->peek() != '{') return null;
        $type = $ctx->peek(2);
        if (!in_array($type, Ast\Section::$acceptableTypes)) return null;
        $ctx->beginTransaction();
        $sec = new Ast\Section($ctx->offset);
        $sec->type = $type;
        $ctx->offset += 2;
        $ctx->skipWhitespace();
        //can't handle quote
        if ($type == '+' && $ctx->peek() == '"') {
            $ctx->rollbackTransaction();
            return null;
        }
        $ctx->commitTransaction();
        $sec->identifier = $this->parseIdentifier($ctx);
        if ($sec->identifier == null) $this->error('Expected identifier', $ctx);
        $sec->context = $this->parseContext($ctx);
        $sec->parameters = $this->parseParameters($ctx);
        $ctx->skipWhitespace();
        if ($ctx->peek() == '/' && $ctx->peek(2) == '}') {
            $ctx->offset += 2;
            return $sec;
        }
        if ($ctx->next() != '}') $this->error('Missing end brace', $ctx);
        $sec->body = $this->parseBody($ctx);
        $sec->bodies = $this->parseBodies($ctx);
        if ($ctx->next() != '{') $this->error('Missing end tag', $ctx);
        if ($ctx->next() != '/') $this->error('Missing end tag', $ctx);
        $ctx->skipWhitespace();
        $end = $this->parseIdentifier($ctx);
        if ($end == null || strval($end) != strval($sec->identifier)) {
            $this->error('Expecting end tag for ' . $sec->identifier, $ctx);
        }
        $ctx->skipWhitespace();
        if ($ctx->next() != '}') $this->error('Missing end brace', $ctx);
        return $sec;
    }
    
    public function parseContext(ParserContext $ctx) {
        if ($ctx->peek() != ':') return null;
        $context = new Ast\Context($ctx->offset);
        $ctx->offset++;
        $context->identifier = $this->parseIdentifier($ctx);
        if ($context->identifier == null) $this->error('Expected identifier', $ctx);
        return $context;
    }
    
    public function parseParameters(ParserContext $ctx) {
        $params = [];
        while (true) {
            $ctx->beginTransaction();
            if (!$ctx->skipWhitespace()) break;
            $beginOffset = $ctx->offset;
            $key = $this->parseKey($ctx);
            if ($key == null) break;
            if ($ctx->peek() != '=') break;
            $ctx->offset++;
            //different possible types...
            $numVal = $this->parseNumber($ctx);
            if ($numVal != null) {
                $param = new Ast\NumericParameter($beginOffset);
                $param->value = $numVal;
            } else {
                $identVal = $this->parseIdentifier($ctx);
                if ($identVal != null) {
                    $param = new Ast\IdentifierParameter($beginOffset);
                    $param->value = $identVal;
                } else {
                    $inlineVal = $this->parseInline($ctx);
                    if ($inlineVal != null) {
                        $param = new Ast\InlineParameter($beginOffset);
                        $param->value = $inlineVal;
                    } else break;
                }
            }
            $param->key = $key;
            $params[] = $param;
            $ctx->commitTransaction();
        }
        $ctx->rollbackTransaction();
        return $params;
    }
    
    public function parseBodies(ParserContext $ctx) {
        $lists = [];
        while (true) {
            if ($ctx->peek() != '{') break;
            if ($ctx->peek(2) != ':') break;
            $list = new Ast\BodyList($ctx->offset);
            $ctx->offset += 2;
            $list->key = $this->parseKey($ctx);
            if ($list->key == null) $this->error('Expected key', $ctx);
            if ($ctx->next() != '}') $this->error('Expected end brace', $ctx);
            $list->body = $this->parseBody($ctx);
            $lists[] = $list;
        }
        return $lists;
    }
    
    public function parseReference(ParserContext $ctx) {
        if ($ctx->peek() != '{') return null;
        $ctx->beginTransaction();
        $ref = new Ast\Reference($ctx->offset);
        $ctx->offset++;
        $ref->identifier = $this->parseIdentifier($ctx);
        if ($ref->identifier == null) {
            $ctx->rollbackTransaction();
            return null;
        }
        $ctx->commitTransaction();
        $ref->filters = $this->parseFilters($ctx);
        if ($ctx->next() != '}') $this->error('Expected end brace', $ctx);
        return $ref;
    }
    
    public function parsePartial(ParserContext $ctx) {
        if ($ctx->peek() != '{') return null;
        $type = $ctx->peek(2);
        if ($type != '>' && $type != '+') return null;
        $partial = new Ast\Partial($ctx->offset);
        $ctx->offset += 2;
        $partial->type = $type;
        $partial->key = $this->parseKey($ctx);
        if ($partial->key == null) $partial->inline = $this->parseInline($ctx);
        $partial->context = $this->parseContext($ctx);
        $partial->parameters = $this->parseParameters($ctx);
        $ctx->skipWhitespace();
        if ($ctx->next() != '/' || $ctx->next() != '}') $this->error('Expected end of tag', $ctx);
        return $partial;
    }
    
    public function parseFilters(ParserContext $ctx) {
        $filters = [];
        while (true) {
            if ($ctx->peek() != '|') break;
            $filter = new Ast\Filter($ctx->offset);
            $ctx->offset++;
            $filter->key = $this->parseKey($ctx);
            if ($filter->key == null) $this->error('Expected filter key', $ctx);
            $filters[] = $filter;
        }
        return $filters;
    }
    
    public function parseSpecial(ParserContext $ctx) {
        if ($ctx->peek() != '{' || $ctx->peek(2) != '~') return null;
        $special = new Ast\Special($ctx->offset);
        $ctx->offset += 2;
        $special->key = $this->parseKey($ctx);
        if ($special->key == null) $this->error('Expected key', $ctx);
        if ($ctx->next() != '}') $this->error('Expected ending brace', $ctx);
        return $special;
    }
    
    public function parseIdentifier(ParserContext $ctx, $couldHaveNumber = false) {
        $ctx->beginTransaction();
        $ident = new Ast\Identifier($ctx->offset);
        $ident->preDot = $ctx->peek() == '.';
        if ($ident->preDot) $ctx->offset++;
        $ident->key = $this->parseKey($ctx);
        if ($ctx->peek() == '[') {
            $ctx->offset++;
            $ident->arrayAccess = $this->parseIdentifier($ctx, true);
            if ($ident->arrayAccess == null) $this->error('Expected array index', $ctx);
            if ($ctx->next() != ']') $this->error('Expected ending bracket', $ctx);
        } elseif (!$ident->preDot && $ident->key == null) {
            if ($couldHaveNumber) {
                $ident->number = $this->parseInteger($ctx);
            } else {
                $ctx->rollbackTransaction();
                return null;
            }
        }
        $ident->next = $this->parseIdentifier($ctx, false);
        $ctx->commitTransaction();
        return $ident;
    }
    
    public function parseKey(ParserContext $ctx) {
        //first char has to be letter, _, or $
        //all others can be num, letter, _, $, or -
        $key = '';
        while (true) {
            $chr = $ctx->peek();
            if (ctype_alpha($chr) || $chr == '_' || $chr == '$') $key .= $chr;
            elseif ($key != '' && (ctype_digit($chr) || $chr == '-')) $key .= $chr;
            elseif ($key == '') return null;
            else return $key;
            $ctx->offset++;
        }
    }
    
    public function parseNumber(ParserContext $ctx) {
        $str = $this->parseInteger($ctx);
        if ($str == null) return null;
        if ($ctx->peek() == '.') {
            $ctx->offset++;
            $next = $this->parseInteger($ctx);
            if ($next == null) $this->error('Expecting decimal contents', $ctx);
            return $str . '.' . $next;
        } else return $str;
    }
    
    public function parseInteger(ParserContext $ctx) {
        $str = '';
        while (ctype_digit($ctx->peek())) $str .= $ctx->next();
        return strlen($str) == 0 ? null : $str;
    }
    
    public function parseInline(ParserContext $ctx) {
        if ($ctx->peek() != '"') return null;
        $inline = new Ast\Inline($ctx->offset);
        $inline->parts = [];
        $ctx->offset++;
        while (true) {
            $part = $this->parseSpecial($ctx);
            if ($part == null) $part = $this->parseReference($ctx);
            if ($part == null) $part = $this->parseLiteral($ctx);
            if ($part == null) break;
            $inline->parts[] = $part;
        }
        if ($ctx->next() != '"') $this->error('Expecting ending quote', $ctx);
        return $inline;
    }
    
    public function parseBuffer(ParserContext $ctx) {
        //some speedup here...
        $eol = strpos($ctx->str, "\n", $ctx->offset);
        if ($eol === false) $eol = strlen($ctx->str);
        $line = substr($ctx->str, $ctx->offset, $eol - $ctx->offset);
        //go through string and make sure there's not a tag or comment
        $possibleEnd = -1;
        while (true) {
            $possibleEnd = strpos($line, '{', $possibleEnd + 1);
            if ($possibleEnd === false) break;
            if ($this->isTag($line, $possibleEnd) || $this->isComment($line, $possibleEnd)) {
                $line = substr($line, 0, $possibleEnd);
                break;
            }
        }
        if (empty($line) && ($eol === false || $possibleEnd !== false)) return null;
        $buffer = new Ast\Buffer($ctx->offset);
        $buffer->contents = $line;
        $ctx->offset += strlen($line);
        //we ended at eol? then skip it but add it
        if ($ctx->peek() == "\n") {
            $ctx->offset++;
            $buffer->contents .= "\n";
        }
        return $buffer;
    }
    
    public function isTag($str, $offset) {
        //needs at least this length
        if (strlen($str) - $offset < 3) return false;
        //needs to start with brace
        if ($str[$offset] != '{') return false;
        //an ending brace needs to be somewhere
        $endingBrace = strpos($str, '}', $offset + 1);
        if ($endingBrace === false) return false;
        //next non whitespace
        $curr = $offset + 1;
        while (strlen($str) > $curr && strpos(" \t\v\f", $str[$curr]) !== false) $curr++;
        if (strlen($str) <= $curr) return false;
        //so, it does start with one of these?
        if (strpos('#?^><+%:@/~%', $str[$curr]) === false) {
            //well then just check for any reference
            $newCtx = new ParserContext($str);
            $newCtx->offset = $curr - 1;
            if ($this->parseReference($newCtx) == null) return false;
        }
        //so now it's all down to whether there is a closing brace
        return strpos($str, '}', $curr) !== false;
    }
    
    public function isComment($str, $offset) {
        //for now, just assume the start means it's on
        return strlen($str) - $offset > 1 && $str[$offset] == '{' && $str[$offset + 1] == '!';
    }
    
    public function parseLiteral(ParserContext $ctx) {
        //empty is not a literal
        if ($ctx->peek() == '"') return null;
        //find first non-escaped quote
        $endQuote = $ctx->offset;
        do {
            $endQuote = strpos($ctx->str, '"', $endQuote + 1);
        } while ($endQuote !== false && $ctx->str[$endQuote - 1] == "\\");
        //missing end quote is a problem
        if ($endQuote === false) $this->error('Missing end quote', $ctx);
        //see if there are any tags in between the current offset and the first quote
        $possibleTag = $ctx->offset - 1;
        do {
            $possibleTag = strpos($ctx->str, '{', $possibleTag + 1);
        } while ($possibleTag !== false && $possibleTag < $endQuote && !$this->isTag($ctx->str, $possibleTag));
        //substring it
        $endIndex = $endQuote;
        if ($possibleTag !== false && $possibleTag < $endQuote) $endIndex = $possibleTag;
        //empty literal means no literal
        if ($endIndex == $ctx->offset) return null;
        $literal = new Ast\InlineLiteral($ctx->offset);
        $literal->value = substr($ctx->str, $ctx->offset, $endIndex - $ctx->offset);
        $ctx->offset += strlen($literal->value);
        return $literal;
    }
    
    public function parseComment(ParserContext $ctx) {
        if ($ctx->peek() != '{' || $ctx->peek(2) != '!') return null;
        $comment = new Ast\Comment($ctx->offset);
        $ctx->offset += 2;
        $endIndex = strpos($ctx->str, '!}', $ctx->offset);
        if ($endIndex === false) $this->error('Missing end of comment', $ctx);
        $comment->contents = substr($ctx->str, $ctx->offset + 2, $endIndex - $ctx->offset - 4);
        $ctx->offset = $endIndex + 2;
        return $comment;
    }
    
}