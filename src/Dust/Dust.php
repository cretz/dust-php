<?php
namespace Dust;

class Dust implements \Serializable {
    public $parser;
    
    public $evaluator;
    
    public $templates;
    
    public $filters;
    
    public $helpers;
    
    public $automaticFilters;
    
    public function __construct($parser = null, $evaluator = null) {
        if ($parser === null) $parser = new Parse\Parser();
        if ($evaluator === null) $evaluator = new Evaluate\Evaluator($this);
        $this->parser = $parser;
        $this->evaluator = $evaluator;
        $this->templates = [];
        $this->filters = [
            "s" => new Filter\SuppressEscape(),
            "h" => new Filter\HtmlEscape(),
            "j" => new Filter\JavaScriptEscape(),
            "u" => new Filter\EncodeUri(),
            "uc" => new Filter\EncodeUriComponent(),
            "js" => new Filter\JsonEncode(),
            "jp" => new Filter\JsonDecode()
        ];
        $this->helpers = [
            "select" => new Helper\Select(),
            "math" => new Helper\Math(),
            "eq" => new Helper\Eq(),
            "lt" => new Helper\Lt(),
            "lte" => new Helper\Lte(),
            "gt" => new Helper\Gt(),
            "gte" => new Helper\Gte(),
            "default" => new Helper\DefaultHelper(),
            "sep" => new Helper\Sep(),
            "size" => new Helper\Size()
        ];
        $this->automaticFilters = [$this->filters['h']];
    }
    
    public function compile($source, $name = null) {
        $parsed = $this->parser->parse($source);
        if ($name != null) $this->register($name, $parsed);
        return $parsed;
    }
    
    public function compileFn($source, $name = null) {
        $parsed = $this->compile($source, $name);
        return function ($context) use ($parsed) { return $this->renderTemplate($parsed, $context); };
    }
    
    public function register($name, Ast\Body $template) {
        $this->templates[$name] = $template;
    }
    
    public function loadTemplate($name) {
        if (!isset($this->templates[$name])) return false;
        return $this->templates[$name];
    }
    
    public function templateExists($name) {
        return isset($this->templates[$name]);
    }
    
    public function render($name, $context) {
        return $this->renderTemplate($this->loadTemplate($name), $context);
    }
    
    public function renderTemplate(Ast\Body $template, $context) {
        return $this->evaluator->evaluate($template, $context);
    }
    
    public function serialize() { return serialize($this->templates); }
    
    public function unserialize($data) { $this->templates = unserialize($data); }
    
}