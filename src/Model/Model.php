<?php

class Model
{
    public $dataManager;
    protected $authData;

    public function __construct($dataManager)
    {
        $this->dataManager = $dataManager;
    }

    public function __destruct()
    {
        $this->dataManager = null;
    }

    public function fetchAll($sql, $inputParams=null)
    {
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

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

