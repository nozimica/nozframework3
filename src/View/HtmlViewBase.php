<?php

class HtmlViewBase extends ViewBase implements iBasicView
{
    protected $mainView;
    protected $contentView;

    public function __construct()
    {
        $this->mainView = new HtmlView();
    }

    protected function _completeParsing($token = 'CONTENIDO')
    {
        $this->mainView->setVariable($token, $this->contentView->toHtml());
        $this->mainView->show();
    }

    public function nfw_printMessage($msg)
    {
        echo $msg;
    }

    public function nfw_loginAction($resultArr, $afterLogin)
    {
        $this->buildPage('_ingresar', array('LOGIN_MSG' => $resultArr, 'ACTION' => $afterLogin));
    }

    public function nfw_dieWithMessage($msg)
    {
        $this->mainView->setVariable('CONTENIDO', $msg);
        $this->mainView->show();
    }

    public function buildPage($tplName, $varsArr)
    {
        $this->contentView = new HtmlView($tplName);
        foreach ($varsArr as $varName => $varValue) {
            $this->contentView->setVariable($varName, $varValue);
        }
        $this->_completeParsing();
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
