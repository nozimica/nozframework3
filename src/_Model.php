<?php

class FactoryModel {
    static public function CreateByConfig($modelConf) {
        $dbObj = null;
        switch ($modelConf['interface']) {
          case 'PDO':
            $dbObj = new PDO($modelConf['dsn']) or die('PDO: Problem with DB.');
            $dbObj->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); 
            break;
          case 'PEAR::DB':
            require_once 'DB.php';
            $dbObj = DB::connect($modelConf['dsn']);
            if (PEAR::isError($dbObj)) {
                die('PEAR::DB: Error with DB.');
            }
            $dbObj->setFetchMode(DB_FETCHMODE_ASSOC);
            break;
          case 'PEAR::MDB2':
            require_once 'MDB2.php';
            $dbObj = MDB2::connect($modelConf['dsn']);
            if (PEAR::isError($dbObj)) {
                die('PEAR::DB: Error with DB.');
            }
            $dbObj->setFetchMode(MDB2_FETCHMODE_ASSOC);
            break;
        }
        return new ModelManager($dbObj);
    }
}

class Model {
    public $dataManager;
    protected $authData;

    public function __construct($dataManager)
    {
        $this->dataManager = $dataManager;
    }

    public function fetchAll($sql, $inputParams=null)
    {
        // TODO: only for PDO by now
        $stm = $this->dataManager->prepare($sql);
        if ( ! $stm) {
            return false;
        }
        $stm->execute($inputParams);
        return $stm->fetchAll();
    }

    public function receiveAuthData($authData)
    {
        $this->authData = $authData;
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
