<?php

class AjaxViewBuilder extends AjaxViewBase {
    public function creaTrabajoAction($resultArr) {
        $this->show($resultArr);
    }
}

class HtmlViewBuilder extends HtmlViewBase {
    public function inicioAction($resultArr) {
        $this->contentView = new HtmlView('inicio');
        $this->contentView->setVariable('START', 'Start');
        $this->_completeParsing();
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
