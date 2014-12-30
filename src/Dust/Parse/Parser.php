<?php
namespace Dust\Parse
{
    use Dust\Ast;

    class Parser
    {
        const T_SECTION_BEGIN = '{';
        const T_SECTION_END = '}';
        const T_SECTION_END_TAG_BEGIN = '/';

        /**
         * @var \Dust\Parse\ParserOptions
         */
        protected $options = NULL;

        /**
         * @param null $options
         */
        public function __construct(ParserOptions $options = NULL) {
            if($options === NULL)
            {
                $options = new ParserOptions();
            }

            $this->options = $options;
        }

        /**
         * @param string                    $message
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @throws \Dust\Parse\ParseException
         */
        public function error($message, ParserContext $ctx) {
            //find line and col
            $loc = $ctx->getCurrentLineAndCol();
            throw new ParseException($message, $loc->line, $loc->col);
        }

        /**
         * @param string $str
         *
         * @return \Dust\Ast\Body|null
         * @throws \Dust\Parse\ParseException
         */
        public function parse($str) {
            $ctx = new ParserContext($str);
            $ret = $this->parseBody($ctx);
            if($ctx->offset != strlen($ctx->str))
            {
                $this->error('Unexpected character', $ctx);
            }

            return $ret;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return \Dust\Ast\Body|null
         */
        public function parseBody(ParserContext $ctx) {
            $body = new Ast\Body($ctx->offset);
            $body->parts = [];

            while($ctx->offset < strlen($ctx->str))
            {
                $part = $this->parsePart($ctx);

                if($part == NULL)
                {
                    break;
                }

                $body->parts[] = $part;
            }

            if(count($body->parts) == 0)
            {
                return NULL;
            }

            return $body;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return \Dust\Ast\Ast
         */
        public function parsePart(ParserContext $ctx) {
            $part = $this->parseComment($ctx);

            // TODO: find a better way of doing this

            if($part == NULL)
            {
                $part = $this->parseSection($ctx);
            }
            if($part == NULL)
            {
                $part = $this->parsePartial($ctx);
            }
            if($part == NULL)
            {
                $part = $this->parseSpecial($ctx);
            }
            if($part == NULL)
            {
                $part = $this->parseReference($ctx);
            }
            if($part == NULL)
            {
                $part = $this->parseBuffer($ctx);
            }

            return $part;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return \Dust\Ast\Section
         * @throws \Dust\Parse\ParseException
         * @todo   Clean this shit up
         */
        public function parseSection(ParserContext $ctx) {
            if($ctx->peek() != self::T_SECTION_BEGIN)
            {
                return NULL;
            }

            $type = $ctx->peek(2);

            if(!in_array($type, Ast\Section::$acceptableTypes))
            {
                return NULL;
            }

            $ctx->beginTransaction();

            $sec = new Ast\Section($ctx->offset);
            $sec->type = $type;

            $ctx->offset += 2;
            $ctx->skipWhitespace();

            //can't handle quote
            if($type == '+' && $ctx->peek() == '"')
            {
                $ctx->rollbackTransaction();

                return NULL;
            }
            $ctx->commitTransaction();
            $sec->identifier = $this->parseIdentifier($ctx);
            if($sec->identifier == NULL)
            {
                $this->error('Expected identifier', $ctx);
            }
            $sec->context = $this->parseContext($ctx);
            $sec->parameters = $this->parseParameters($ctx);
            $ctx->skipWhitespace();
            if($ctx->peek() == self::T_SECTION_END_TAG_BEGIN && $ctx->peek(2) == self::T_SECTION_END)
            {
                $ctx->offset += 2;

                return $sec;
            }
            if($ctx->next() != self::T_SECTION_END)
            {
                $this->error('Missing end brace', $ctx);
            }
            $sec->body = $this->parseBody($ctx);
            $sec->bodies = $this->parseBodies($ctx);
            if($ctx->next() != self::T_SECTION_BEGIN)
            {
                $this->error('Missing end tag', $ctx);
            }
            if($ctx->next() != self::T_SECTION_END_TAG_BEGIN)
            {
                $this->error('Missing end tag', $ctx);
            }
            $ctx->skipWhitespace();
            $end = $this->parseIdentifier($ctx);
            if($end == NULL || strval($end) != strval($sec->identifier))
            {
                $this->error('Expecting end tag for ' . $sec->identifier, $ctx);
            }
            $ctx->skipWhitespace();
            if($ctx->next() != self::T_SECTION_END)
            {
                $this->error('Missing end brace', $ctx);
            }

            return $sec;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return \Dust\Ast\Context|null
         * @throws \Dust\Parse\ParseException
         */
        public function parseContext(ParserContext $ctx) {
            if($ctx->peek() != ':')
            {
                return NULL;
            }
            $context = new Ast\Context($ctx->offset);
            $ctx->offset++;
            $context->identifier = $this->parseIdentifier($ctx);
            if($context->identifier == NULL)
            {
                $this->error('Expected identifier', $ctx);
            }

            return $context;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return array
         */
        public function parseParameters(ParserContext $ctx) {
            $params = [];

            while(true)
            {
                $ctx->beginTransaction();

                if(!$ctx->skipWhitespace())
                {
                    break;
                }

                $beginOffset = $ctx->offset;
                $key = $this->parseKey($ctx);

                if($key == NULL)
                {
                    break;
                }

                if($ctx->peek() != '=')
                {
                    break;
                }

                $ctx->offset++;

                //different possible types...
                $numVal = $this->parseNumber($ctx);
                if($numVal != NULL)
                {
                    $param = new Ast\NumericParameter($beginOffset);
                    $param->value = $numVal;
                }
                else
                {
                    $identVal = $this->parseIdentifier($ctx);
                    if($identVal != NULL)
                    {
                        $param = new Ast\IdentifierParameter($beginOffset);
                        $param->value = $identVal;
                    }
                    else
                    {
                        $inlineVal = $this->parseInline($ctx);
                        if($inlineVal != NULL)
                        {
                            $param = new Ast\InlineParameter($beginOffset);
                            $param->value = $inlineVal;
                        }
                        else
                        {
                            break;
                        }
                    }
                }
                $param->key = $key;
                $params[] = $param;
                $ctx->commitTransaction();
            }
            $ctx->rollbackTransaction();

            return $params;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return array
         * @throws \Dust\Parse\ParseException
         */
        public function parseBodies(ParserContext $ctx) {
            $lists = [];
            while(true)
            {
                if($ctx->peek() != '{')
                {
                    break;
                }
                if($ctx->peek(2) != ':')
                {
                    break;
                }
                $list = new Ast\BodyList($ctx->offset);
                $ctx->offset += 2;
                $list->key = $this->parseKey($ctx);
                if($list->key == NULL)
                {
                    $this->error('Expected key', $ctx);
                }
                if($ctx->next() != '}')
                {
                    $this->error('Expected end brace', $ctx);
                }
                $list->body = $this->parseBody($ctx);
                $lists[] = $list;
            }

            return $lists;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return \Dust\Ast\Reference|null
         * @throws \Dust\Parse\ParseException
         */
        public function parseReference(ParserContext $ctx) {
            if($ctx->peek() != '{')
            {
                return NULL;
            }
            $ctx->beginTransaction();
            $ref = new Ast\Reference($ctx->offset);
            $ctx->offset++;
            $ref->identifier = $this->parseIdentifier($ctx);
            if($ref->identifier == NULL)
            {
                $ctx->rollbackTransaction();

                return NULL;
            }
            $ctx->commitTransaction();
            $ref->filters = $this->parseFilters($ctx);
            if($ctx->next() != '}')
            {
                $this->error('Expected end brace', $ctx);
            }

            return $ref;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return \Dust\Ast\Partial|null
         * @throws \Dust\Parse\ParseException
         */
        public function parsePartial(ParserContext $ctx) {
            if($ctx->peek() != '{')
            {
                return NULL;
            }
            $type = $ctx->peek(2);
            if($type != '>' && $type != '+')
            {
                return NULL;
            }
            $partial = new Ast\Partial($ctx->offset);
            $ctx->offset += 2;
            $partial->type = $type;
            $partial->key = $this->parseKey($ctx);
            if($partial->key == NULL)
            {
                $partial->inline = $this->parseInline($ctx);
            }
            $partial->context = $this->parseContext($ctx);
            $partial->parameters = $this->parseParameters($ctx);
            $ctx->skipWhitespace();
            if($ctx->next() != '/' || $ctx->next() != '}')
            {
                $this->error('Expected end of tag', $ctx);
            }

            return $partial;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return array
         * @throws \Dust\Parse\ParseException
         */
        public function parseFilters(ParserContext $ctx) {
            $filters = [];
            while(true)
            {
                if($ctx->peek() != '|')
                {
                    break;
                }
                $filter = new Ast\Filter($ctx->offset);
                $ctx->offset++;
                $filter->key = $this->parseKey($ctx);
                if($filter->key == NULL)
                {
                    $this->error('Expected filter key', $ctx);
                }
                $filters[] = $filter;
            }

            return $filters;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return \Dust\Ast\Special|null
         * @throws \Dust\Parse\ParseException
         */
        public function parseSpecial(ParserContext $ctx) {
            if($ctx->peek() != '{' || $ctx->peek(2) != '~')
            {
                return NULL;
            }
            $special = new Ast\Special($ctx->offset);
            $ctx->offset += 2;
            $special->key = $this->parseKey($ctx);
            if($special->key == NULL)
            {
                $this->error('Expected key', $ctx);
            }
            if($ctx->next() != '}')
            {
                $this->error('Expected ending brace', $ctx);
            }

            return $special;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         * @param bool                      $couldHaveNumber
         *
         * @return \Dust\Ast\Identifier|null
         * @throws \Dust\Parse\ParseException
         */
        public function parseIdentifier(ParserContext $ctx, $couldHaveNumber = false) {
            $ctx->beginTransaction();
            $ident = new Ast\Identifier($ctx->offset);
            $ident->preDot = $ctx->peek() == '.';
            if($ident->preDot)
            {
                $ctx->offset++;
            }
            $ident->key = $this->parseKey($ctx);
            if($ctx->peek() == '[')
            {
                $ctx->offset++;
                $ident->arrayAccess = $this->parseIdentifier($ctx, true);
                if($ident->arrayAccess == NULL)
                {
                    $this->error('Expected array index', $ctx);
                }
                if($ctx->next() != ']')
                {
                    $this->error('Expected ending bracket', $ctx);
                }
            }
            elseif(!$ident->preDot && $ident->key == NULL)
            {
                if($couldHaveNumber)
                {
                    $ident->number = $this->parseInteger($ctx);
                }
                else
                {
                    $ctx->rollbackTransaction();

                    return NULL;
                }
            }
            $ident->next = $this->parseIdentifier($ctx, false);
            $ctx->commitTransaction();

            return $ident;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return null|string
         */
        public function parseKey(ParserContext $ctx) {
            //first char has to be letter, _, or $
            //all others can be num, letter, _, $, or -
            $key = '';
            while(true)
            {
                $chr = $ctx->peek();
                if(ctype_alpha($chr) || $chr == '_' || $chr == '$')
                {
                    $key .= $chr;
                }
                elseif($key != '' && (ctype_digit($chr) || $chr == '-'))
                {
                    $key .= $chr;
                }
                elseif($key == '')
                {
                    return NULL;
                }
                else
                {
                    return $key;
                }
                $ctx->offset++;
            }
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return null|string
         * @throws \Dust\Parse\ParseException
         */
        public function parseNumber(ParserContext $ctx) {
            $str = $this->parseInteger($ctx);
            if($str == NULL)
            {
                return NULL;
            }
            if($ctx->peek() == '.')
            {
                $ctx->offset++;
                $next = $this->parseInteger($ctx);
                if($next == NULL)
                {
                    $this->error('Expecting decimal contents', $ctx);
                }

                return $str . '.' . $next;
            }
            else
            {
                return $str;
            }
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return null|string
         */
        public function parseInteger(ParserContext $ctx) {
            $str = '';
            while(ctype_digit($ctx->peek()))
                $str .= $ctx->next();

            return strlen($str) == 0 ? NULL : $str;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return \Dust\Ast\Inline|null
         * @throws \Dust\Parse\ParseException
         */
        public function parseInline(ParserContext $ctx) {
            if($ctx->peek() != '"')
            {
                return NULL;
            }
            $inline = new Ast\Inline($ctx->offset);
            $inline->parts = [];
            $ctx->offset++;
            while(true)
            {
                $part = $this->parseSpecial($ctx);
                if($part == NULL)
                {
                    $part = $this->parseReference($ctx);
                }
                if($part == NULL)
                {
                    $part = $this->parseLiteral($ctx);
                }
                if($part == NULL)
                {
                    break;
                }
                $inline->parts[] = $part;
            }
            if($ctx->next() != '"')
            {
                $this->error('Expecting ending quote', $ctx);
            }

            return $inline;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return \Dust\Ast\Buffer|null
         */
        public function parseBuffer(ParserContext $ctx) {
            //some speedup here...
            $eol = strpos($ctx->str, "\n", $ctx->offset);
            if($eol === false)
            {
                $eol = strlen($ctx->str);
            }
            $line = substr($ctx->str, $ctx->offset, $eol - $ctx->offset);
            //go through string and make sure there's not a tag or comment
            $possibleEnd = -1;
            while(true)
            {
                $possibleEnd = strpos($line, '{', $possibleEnd + 1);
                if($possibleEnd === false)
                {
                    break;
                }
                if($this->isTag($line, $possibleEnd) || $this->isComment($line, $possibleEnd))
                {
                    $line = substr($line, 0, $possibleEnd);
                    break;
                }
            }
            if(empty($line) && ($eol === false || $possibleEnd !== false))
            {
                return NULL;
            }
            $buffer = new Ast\Buffer($ctx->offset);
            $buffer->contents = $line;
            $ctx->offset += strlen($line);
            //we ended at eol? then skip it but add it
            if($ctx->peek() == "\n")
            {
                $ctx->offset++;
                $buffer->contents .= "\n";
            }

            return $buffer;
        }

        /**
         * @param $str
         * @param $offset
         *
         * @return bool
         */
        public function isTag($str, $offset) {
            //needs at least this length
            if(strlen($str) - $offset < 3)
            {
                return false;
            }
            //needs to start with brace
            if($str[ $offset ] != '{')
            {
                return false;
            }
            //an ending brace needs to be somewhere
            $endingBrace = strpos($str, '}', $offset + 1);
            if($endingBrace === false)
            {
                return false;
            }
            //next non whitespace
            $curr = $offset + 1;
            while(strlen($str) > $curr && strpos(" \t\v\f", $str[ $curr ]) !== false)
                $curr++;
            if(strlen($str) <= $curr)
            {
                return false;
            }
            //so, it does start with one of these?
            if(strpos('#?^><+%:@/~%', $str[ $curr ]) === false)
            {
                //well then just check for any reference
                $newCtx = new ParserContext($str);
                $newCtx->offset = $curr - 1;
                if($this->parseReference($newCtx) == NULL)
                {
                    return false;
                }
            }

            //so now it's all down to whether there is a closing brace
            return strpos($str, '}', $curr) !== false;
        }

        /**
         * @param $str
         * @param $offset
         *
         * @return bool
         */
        public function isComment($str, $offset) {
            //for now, just assume the start means it's on
            return strlen($str) - $offset > 1 && $str[ $offset ] == '{' && $str[ $offset + 1 ] == '!';
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return \Dust\Ast\InlineLiteral|null
         * @throws \Dust\Parse\ParseException
         */
        public function parseLiteral(ParserContext $ctx) {
            //empty is not a literal
            if($ctx->peek() == '"')
            {
                return NULL;
            }
            //find first non-escaped quote
            $endQuote = $ctx->offset;
            do
            {
                $endQuote = strpos($ctx->str, '"', $endQuote + 1);
            } while($endQuote !== false && $ctx->str[ $endQuote - 1 ] == "\\");
            //missing end quote is a problem
            if($endQuote === false)
            {
                $this->error('Missing end quote', $ctx);
            }
            //see if there are any tags in between the current offset and the first quote
            $possibleTag = $ctx->offset - 1;
            do
            {
                $possibleTag = strpos($ctx->str, '{', $possibleTag + 1);
            } while($possibleTag !== false && $possibleTag < $endQuote && !$this->isTag($ctx->str, $possibleTag));
            //substring it
            $endIndex = $endQuote;
            if($possibleTag !== false && $possibleTag < $endQuote)
            {
                $endIndex = $possibleTag;
            }
            //empty literal means no literal
            if($endIndex == $ctx->offset)
            {
                return NULL;
            }
            $literal = new Ast\InlineLiteral($ctx->offset);
            $literal->value = substr($ctx->str, $ctx->offset, $endIndex - $ctx->offset);
            $ctx->offset += strlen($literal->value);

            return $literal;
        }

        /**
         * @param \Dust\Parse\ParserContext $ctx
         *
         * @return \Dust\Ast\Comment|null
         * @throws \Dust\Parse\ParseException
         */
        public function parseComment(ParserContext $ctx) {
            if($ctx->peek() != self::T_SECTION_BEGIN || $ctx->peek(2) != '!')
            {
                return NULL;
            }

            $comment = new Ast\Comment($ctx->offset);
            $ctx->offset += 2;
            $endIndex = strpos($ctx->str, '!}', $ctx->offset);

            if($endIndex === false)
            {
                $this->error('Missing end of comment', $ctx);
            }

            $comment->contents = substr($ctx->str, $ctx->offset + 2, $endIndex - $ctx->offset - 4);
            $ctx->offset = $endIndex + 2;

            return $comment;
        }
    }
}