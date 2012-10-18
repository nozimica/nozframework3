<?php
require_once "HTML/Template/IT.php";

class FactoryView {
    static public function CreateByOutFormat($outFormat) {
        if ($outFormat == 'html' || $outFormat == 'html-in' || $outFormat == 'html-out' || $outFormat == 'html-start') {
            return new HtmlViewBuilder();
        }
        if ($outFormat == 'ajax') {
            return new AjaxViewBuilder();
        }
        return null;
    }
}

class View {
    public function show() { }
}

class AjaxView extends View { }

class HtmlView extends View {
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
        if ( ($libsStr = file_get_contents($this->templPath . '/htmlHeadLibs.tpl.html')) !== false) {
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

    public function show() {
        $this->templateObj->show();
    }

    public function toHtml() {
        return $this->templateObj->get();
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
