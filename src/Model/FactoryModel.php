<?php

class FactoryModel
{
    /**
     * CreateByConfig
     *
     * Factory method that creates and returns a new ModelManager object.
     *
     * @param string $dsn The DSN string with all DB connection parameters.
     * @param string $abstractionLayer Which abstaction layer will be used to connect to DB.
     *
     * @return void
     */
    public static function CreateByConfig($dsn, $abstractionLayer = null)
    {
        $dbObj = null;
        if (!is_null($dsn)) {
            if (is_null($abstractionLayer)) {
                $abstractionLayer = 'PDO';
            }
            switch ($abstractionLayer) {
                case 'PDO':
                    $dbObj = new PDO($dsn) or die('PDO: Problem with DB.');
                    if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
                        $dbObj->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); 
                    }
                    break;
                case 'PEAR::DB':
                case 'PEAR::MDB2':
                    die('PEAR classes no longer supported.');
                default:
                    die('A valid DB abstaction layer must be specified.');
            }
        }
        return new ModelManager($dbObj);
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

