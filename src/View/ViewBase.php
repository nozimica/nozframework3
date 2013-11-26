<?php

/**
 * ViewBase class is the inner core for any View implementation.
 */
class ViewBase
{
    protected $controllerObj;

    public function setControllerObj(Controller $obj) {
        $this->controllerObj = $obj;
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

