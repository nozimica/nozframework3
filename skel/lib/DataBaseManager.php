<?php
require_once 'DB.php';

class DataBaseManager extends DataManager {
    var $dbObj;

    public function __construct($dbObj) {
        $this->dbObj = $dbObj;
    }
}

class ModelManager extends Model {
    public function __construct($dataManager) {
        parent::__construct($dataManager);
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
