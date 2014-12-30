<?php
namespace Dust
{
    use Dust\Evaluate\Evaluator;
    use Dust\Parse\Parser;

    class Dust implements \Serializable
    {
        const FILE_EXTENSION = '.dust';

        /**
         * @var \Dust\Parse\Parser
         */
        public $parser = NULL;

        /**
         * @var \Dust\Evaluate\Evaluator
         */
        public $evaluator = NULL;

        /**
         * @var array[string] => Ast\Body
         */
        public $templates = [];

        /**
         * @var array
         */
        public $filters = [];

        /**
         * @var array
         */
        public $helpers = [];

        /**
         * @var array
         */
        public $automaticFilters = [];

        /**
         * @var array
         */
        public $includedDirectories = [];

        /**
         * @var object
         */
        public $autoloaderOverride;

        /**
         * @param null $parser
         * @param null $evaluator
         */
        public function __construct(Parser $parser = NULL, Evaluator $evaluator = NULL) {
            if($parser === NULL)
            {
                $parser = new Parser();
            }

            if($evaluator === NULL)
            {
                $evaluator = new Evaluate\Evaluator($this);
            }

            $this->parser = $parser;
            $this->evaluator = $evaluator;

            $this->filters = [
                "s"  => new Filter\SuppressEscape(),
                "h"  => new Filter\HtmlEscape(),
                "j"  => new Filter\JavaScriptEscape(),
                "u"  => new Filter\EncodeUri(),
                "uc" => new Filter\EncodeUriComponent(),
                "js" => new Filter\JsonEncode(),
                "jp" => new Filter\JsonDecode()
            ];
            $this->helpers = [
                "select"      => new Helper\Select(),
                "math"        => new Helper\Math(),
                "eq"          => new Helper\Eq(),
                "if"          => new Helper\IfHelper(),
                "lt"          => new Helper\Lt(),
                "lte"         => new Helper\Lte(),
                "gt"          => new Helper\Gt(),
                "gte"         => new Helper\Gte(),
                "default"     => new Helper\DefaultHelper(),
                "sep"         => new Helper\Sep(),
                "size"        => new Helper\Size(),
                "contextDump" => new Helper\ContextDump()
            ];

            $this->automaticFilters = [$this->filters['h']];
        }

        /**
         * @param string $source
         * @param string $name
         *
         * @return \Dust\Ast\Body|null
         */
        public function compile($source, $name = NULL) {
            $parsed = $this->parser->parse($source);
            if($name != NULL)
            {
                $this->register($name, $parsed);
            }

            return $parsed;
        }

        /**
         * @param      $source
         * @param null $name
         *
         * @return callable
         */
        public function compileFn($source, $name = NULL) {
            $parsed = $this->compile($source, $name);

            return function ($context) use ($parsed)
            {
                return $this->renderTemplate($parsed, $context);
            };
        }

        /**
         * @param      $path
         * @param null $basePath
         *
         * @return null|string
         */
        public function resolveAbsoluteDustFilePath($path, $basePath = NULL) {
            //add extension if necessary
            if(substr_compare($path, self::FILE_EXTENSION, -5, 5) !== 0)
            {
                $path .= self::FILE_EXTENSION;
            }

            if($basePath != NULL)
            {
                $possible = realpath($basePath . '/' . $path);
                if($possible !== false)
                {
                    return $possible;
                }
            }

            //try the current path
            $possible = realpath($path);

            if($possible !== false)
            {
                return $possible;
            }

            //now try each of the included directories
            for($i = 0; $i < count($this->includedDirectories); $i++)
            {
                $possible = realpath($this->includedDirectories[ $i ] . '/' . $path);
                if($possible !== false)
                {
                    return $possible;
                }
            }

            return NULL;
        }

        /**
         * @param string $path
         * @param string $basePath
         *
         * @return \Dust\Ast\Body|null
         */
        public function compileFile($path, $basePath = NULL) {
            //resolve absolute path
            $absolutePath = $this->resolveAbsoluteDustFilePath($path, $basePath);

            if($absolutePath == NULL)
            {
                return NULL;
            }
            //just compile w/ the path as the name
            $compiled = $this->compile(file_get_contents($absolutePath), $absolutePath);
            $compiled->filePath = $absolutePath;

            return $compiled;
        }

        /**
         * @param string         $name
         * @param \Dust\Ast\Body $template
         */
        public function register($name, Ast\Body $template) {
            $this->templates[ $name ] = $template;
        }

        /**
         * @param string $name
         * @param string $basePath
         *
         * @return Ast\Body|NULL
         */
        public function loadTemplate($name, $basePath = NULL) {
            //if there is an override, use it instead
            if($this->autoloaderOverride != NULL)
            {
                return $this->autoloaderOverride->__invoke($name);
            }
            //is it there w/ the normal name?
            if(!isset($this->templates[ $name ]))
            {
                //what if I used the resolve file version of the name
                $name = $this->resolveAbsoluteDustFilePath($name, $basePath);
                //if name is null, then it's not around
                if($name == NULL)
                {
                    return NULL;
                }
                //if name is null and not in the templates array, put it there automatically
                if(!isset($this->templates[ $name ]))
                {
                    $this->compileFile($name, $basePath);
                }
            }

            return $this->templates[ $name ];
        }

        /**
         * @param string $name
         * @param array  $context
         *
         * @return string
         */
        public function render($name, $context = []) {
            return $this->renderTemplate($this->loadTemplate($name), $context);
        }

        /**
         * @param \Dust\Ast\Body $template
         * @param array          $context
         *
         * @return string
         */
        public function renderTemplate(Ast\Body $template, $context = []) {
            return $this->evaluator->evaluate($template, $context);
        }

        /**
         * @return string
         */
        public function serialize() {
            return serialize($this->templates);
        }

        /**
         * @param string $data
         */
        public function unserialize($data) {
            $this->templates = unserialize($data);
        }

    }

}