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
            $tplName = "_main.tpl.html";
        } else {
            $tplName = sprintf("%s.tpl.html", preg_replace("/[^A-Za-z-_.\/]/", "", strval($action)));
        }
        try {
            $this->templateObjTwig = $this->twigObj->loadTemplate($tplName);
        } catch (Twig_Error_Loader $e) {
            die(sprintf("Error inside View: %s", $e->getRawMessage()));
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

