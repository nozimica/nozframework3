<?php

/**
 * Template Class
 */
class HtmlView
{
    protected $templateObj;
    protected $templPath = "templates";
    protected $twigObj;
    protected $templateObjTwig;
    protected $replacements;

    public function __construct($action = null)
    {
        $this->twigObj = new Twig_Environment(new Twig_Loader_Filesystem('templates'), array('autoescape' => false));
        $this->replacements = array();
        if (is_null($action)) {
            $this->templateObjTwig = $this->twigObj->loadTemplate("_main.tpl.html");
        } else {
            $this->templateObjTwig = $this->twigObj->loadTemplate("$action.tpl.html");
        }
    }

    public function setVariable($varName, $varValue)
    {
        $this->replacements[$varName] = $varValue;
    }

    public function show($msg=null)
    {
        echo $this->toHtml();
    }

    public function toHtml()
    {
        $baseDir = dirname($_SERVER['SCRIPT_NAME']);
        if ($baseDir == '/')    $baseDir = '';
        $this->replacements['PROJ_ROOT'] =  $baseDir;
        return $this->templateObjTwig->render($this->replacements);
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

