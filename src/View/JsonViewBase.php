<?php

class JsonViewBase extends ViewBase
{
    public function show($msg)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($msg);
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

