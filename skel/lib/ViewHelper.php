<?php

class AjaxViewBuilder extends AjaxBase {
    public function creaTrabajoAction($resultArr) {
        $this->show($resultArr);
    }
    public function consultaTrabajosAction($resultArr) {
        $this->show($resultArr);
    }
}

class HtmlViewBuilder extends ViewBuilder {
    public function homeAction($resultArr) {
        $this->contentView = new HtmlView('home');
        $this->contentView->setVariable('START', '');
        $this->_completeParsing();
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
