<?php
require_once "HTML/Template/IT.php";

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

interface iBasicView {
    public function nfw_printMessage($msg);
    public function nfw_loginAction($res, $afterLogin);
    public function nfw_dieWithMessage($msg);
}

class AjaxBase {
    public function show($msg)
    {
        echo $msg;
    }
}

class ViewBuilder implements iBasicView {
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
    var $templateObj;
    //var $templPath = "../templates";
    var $templPath = "templates";

    public function __construct($action = null) {
        $this->templateObj = new HTML_Template_IT($this->templPath);
        if (is_null($action)) {
            $this->templateObj->loadTemplatefile("_main.tpl.html");
            $this->_loadHeadLibs();
        } else {
            $this->templateObj->loadTemplatefile("$action.tpl.html");
        }
    }

    private function _loadHeadLibs() {
        if ( ($libsStr = file_get_contents($this->templPath . '/_htmlHeadLibs.tpl.html')) !== false) {
            $this->templateObj->setVariable('HEADLIBS', $libsStr);
        } else {
            $this->templateObj->setVariable('HEADLIBS', '');
        }
    }

    public function setVariable($varName, $varValue) {
        $this->templateObj->setVariable($varName, $varValue);
    }

    public function beginBlock($block) {
        $this->templateObj->setCurrentBlock($block);
    }

    public function endBlock($block) {
        $this->templateObj->parseCurrentBlock($block);
    }

    public function parseBlock($block) {
        $this->templateObj->parse($block);
    }

    public function show($msg=null) {
        $this->templateObj->show();
    }

    public function toHtml() {
        return $this->templateObj->get();
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
