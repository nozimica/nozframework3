<?php

class FactoryModel
{
    public static function CreateByConfig($modelConf)
    {
        $dbObj = null;
        switch ($modelConf['interface']) {
          case 'PDO':
            $dbObj = new PDO($modelConf['dsn']) or die('PDO: Problem with DB.');
            if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
                $dbObj->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); 
            }
            break;
          case 'PEAR::DB':
          case 'PEAR::MDB2':
            die('PEAR classes no longer supported.');
        }
        return new ModelManager($dbObj);
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

