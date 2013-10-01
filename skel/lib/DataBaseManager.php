<?php

class ModelManager extends Model {
    private $dbMngr;

    public function __construct($dataManager) {
        parent::__construct($dataManager);
        // ...
    }

    public function inicioAction($params) {
        return '';
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
