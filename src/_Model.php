<?php
require_once 'DB.php';

class FactoryModel {
    static public function CreateByConfig($modelConf) {
        if (empty($modelConf)) {
            return new ModelManager(NULL);
        }

        // TODO: Replace PEAR::DB
        return new ModelManager(NULL);


        $mainObj = null;
        // TODO: check if $modelConf is a dsn
        $dbObj = DB::connect($modelConf);
        if (PEAR::isError($dbObj)) {
            echo 'Error en conexi&oacute;n a BD';
            //echo $dbObj->getMessage() . ' ' . $dbObj->getUserInfo();
        }
        $dbObj->setFetchMode(DB_FETCHMODE_ASSOC);

        $mainObj = new DataBaseManager($dbObj);
        return new ModelManager($mainObj);
    }
}

class DataManager {
}

class Model {
    public $dataManager;

    public function __construct($dataManager) {
        $this->dataManager = $dataManager;
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
