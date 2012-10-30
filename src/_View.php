<?php
//require_once "HTML/Template/IT.php";
require_once "vendor/Twig/lib/Twig/Autoloader.php";
Twig_Autoloader::register();

class FactoryView {
    static public function CreateByOutFormat($outFormat) {
        if ($outFormat == 'html' || $outFormat == 'html-in' || $outFormat == 'html-start') {
            return new HtmlViewBuilder();
        }
        if ($outFormat == 'ajax') {
            return new AjaxViewBuilder();
        }
        return null;
    }
}

/**
 * ViewBase class is the inner core for any View implementation.
 */
class ViewBase {
    protected $controllerObj;

    public function setControllerObj(Controller $obj)
    {
        $this->controllerObj = $obj;
    }
}

/**
 * Interface to be used by the main view implementarion, usually the HTML one.
 */
interface iBasicView {
    public function nfw_printMessage($msg);
    public function nfw_loginAction($res, $afterLogin);
    public function nfw_dieWithMessage($msg);
}

class AjaxViewBase extends ViewBase {
    public function show($msg)
    {
        echo $msg;
    }
}

class HtmlViewBase extends ViewBase implements iBasicView {
    protected $mainView;
    protected $contentView;

    public function __construct() {
        $this->mainView = new HtmlView();
    }   
    
    protected function _completeParsing($token = 'CONTENIDO') {
        $this->mainView->setVariable($token, $this->contentView->toHtml());
        $this->mainView->show();
    }   

    public function nfw_printMessage($msg) { }

    public function nfw_loginAction($resultArr, $afterLogin)
    {
        $this->contentView = new HtmlView('_ingresar');
        $this->contentView->setVariable('INGRESAR', $resultArr);
        $this->contentView->setVariable('AFTER', $afterLogin);
        $this->_completeParsing();
    }

    public function nfw_dieWithMessage($msg)
    {
        $this->mainView->setVariable('CONTENIDO', $msg);
        $this->mainView->show();
    }
}

/**
 * Template Class
 */
class HtmlView {
    protected $templateObj;
    protected $templPath = "templates";
    protected $twigObj;
    protected $templateObjTwig;
    protected $replacements;

    public function __construct($action = null) {
        /*
        $this->templateObj = new HTML_Template_IT($this->templPath);
        if (is_null($action)) {
            $this->templateObj->loadTemplatefile("_main.tpl.html");
            $this->_loadHeadLibs();
        } else {
            $this->templateObj->loadTemplatefile("$action.tpl.html");
        }
        */

        $this->twigObj = new Twig_Environment(new Twig_Loader_Filesystem('templates'), array('autoescape' => false));
        $this->replacements = array();
        if (is_null($action)) {
            $this->templateObjTwig = $this->twigObj->loadTemplate("_main.tpl.html");
            $this->_loadHeadLibs();
        } else {
            $this->templateObjTwig = $this->twigObj->loadTemplate("$action.tpl.html");
        }
    }

    private function _loadHeadLibs() {
        if ( ($libsStr = file_get_contents($this->templPath . '/_htmlHeadLibs.tpl.html')) !== false) {
            //$this->templateObj->setVariable('HEADLIBS', $libsStr);
            $this->replacements['HEADLIBS'] = $libsStr;
        } else {
            //$this->templateObj->setVariable('HEADLIBS', '');
        }
    }

    public function setVariable($varName, $varValue) {
        //$this->templateObj->setVariable($varName, $varValue);
        $this->replacements[$varName] = $varValue;
    }

    public function beginBlock($block) {
        //$this->templateObj->setCurrentBlock($block);
    }

    public function endBlock($block) {
        //$this->templateObj->parseCurrentBlock($block);
    }

    public function parseBlock($block) {
        //$this->templateObj->parse($block);
    }

    public function show($msg=null) {
        //$this->templateObj->show();
        echo $this->templateObjTwig->render($this->replacements);
    }

    public function toHtml() {
        //return $this->templateObj->get();
        return $this->templateObjTwig->render($this->replacements);
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
