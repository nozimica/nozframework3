<?php

class AjaxViewBuilder extends AjaxViewBase {
    public function creaTrabajoAction($resultArr) {
        $this->show($resultArr);
    }
}

class HtmlViewBuilder extends HtmlViewBase {
    public function inicioAction($resultArr) {
        $this->buildPage('inicio', array('START' => $resultArr));
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
