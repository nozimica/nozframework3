<?php

/**
 * Interface to be used by the main view implementation, usually the HTML one.
 */
interface iBasicView
{
    public function nfw_printMessage($msg);
    public function nfw_loginAction($res, $afterLogin);
    public function nfw_dieWithMessage($msg);
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

