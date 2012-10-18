<?php

class AjaxViewBuilder {
}

class HtmlViewBuilder {
    private $mainView;
    private $contentView;

    public function __construct() {
        $this->mainView = new HtmlView();
    }

    private function _completeParsing($token = 'CONTENIDO') {
        $this->mainView->setVariable($token, $this->contentView->toHtml());
        $this->mainView->show();
    }

    public function inicioAction($resultArr) {
        $this->contentView = new HtmlView('inicio');
        $this->contentView->setVariable('DUMMY', 'Start editing "lib/ViewHelper.php"...!');
        $this->_completeParsing('CONTENIDO');
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
