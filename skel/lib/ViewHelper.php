<?php

class AjaxViewBuilder extends AjaxViewBase {
    public function creaTrabajoAction($resultArr) {
        $this->show($resultArr);
    }
}

class HtmlViewBuilder extends HtmlViewBase {
    public function nfw_dieWithMessage($msg) {
        parent::nfw_dieWithMessage($msg);
    }

    public function nfw_printMessage($msg) {
        echo $msg;
    }

    public function inicioAction($resultArr) {
        $this->contentView = new HtmlView('inicio');
        $this->contentView->setVariable('START', 'Start');
        $this->_completeParsing();
    }

    public function listaAction($resultArr) {
        $this->contentView = new HtmlView('lista');
        $this->contentView->setVariable('FINISHED', $resultArr[2]);
        $this->_completeParsing();
    }

}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
