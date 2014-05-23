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

    /**
     * nfw_printMessage
     *
     * Function that just prints a message. To be overriden by app implementation.
     *
     * @param string $msg
     * @return void
     */
    public function nfw_printMessage($msg)
    {
        echo $msg;
    }

    /**
     * nfw_loginAction
     *
     * Calls buildPage to generate the login form page.
     *
     * @param string $loginMsg    Message to be displayed along with login.
     * @param string $afterLogin  Action to be performed after login is successful.
     * @return void
     */
    public function nfw_loginAction($loginMsg, $afterLogin = '')
    {
        $this->buildPage('_login', array('LOGIN_MSG' => $loginMsg, 'ACTION' => $afterLogin));
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
