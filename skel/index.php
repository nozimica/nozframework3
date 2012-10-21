<?php
error_reporting(E_ALL);

# Manual conf
$pearPath = '/home/mmh/opt/nozFramework3/sharedPear/pear/php';
$frameworkPath = '/home/mmh/opt/nozFramework3/src';

set_include_path($pearPath . PATH_SEPARATOR . get_include_path());
require_once $frameworkPath . '/_Controller.php';

require_once 'lib/ActionsController.php';
require_once 'lib/DataBaseManager.php';
require_once 'lib/ViewHelper.php';

# Authentication:
$authOptions = array(
    //Level of the auth logs. Possible values: [none|info|debug]
    'authLogLevel'   => 'none'
    , 'postUsername' => 'username'
    , 'postPassword' => 'password'
    , 'table'        => 'usuario'
    , 'usernamecol'  => 'usu_username'
    , 'passwordcol'  => 'usu_password'
    // Name of the field that defines the profile of the user
    // (if array, [0] field name, [1] alias):
    , 'profilecol'   => array('usu_rol_id', 'usu_perfil')
    // DB_FIELDS: extra fields to be retrieved when validating user
    // (if array, [0] field name, [1] alias):
    , 'db_fields'    => array('usu_email')
);

$actionObj = new ActionsController('TestFW2012', 'v');
#pgsql:host=localhost;port=5432;dbname=testdb;user=bruce;password=mypass
$actionObj->setDataDriver('sqlite:////home/mmh/software/viewerV4/data/queue_test.sqlite');
$actionObj->useAuth($authOptions);
$actionObj->setAction('home'             , 'Inicio'                , 8, 'html-start');
$actionObj->setAction('nuevaSim'         , 'Nueva simulación'      , 8, 'html');
$actionObj->setAction('listaSim'         , 'Lista de simulaciones' , 8, 'html');
$actionObj->setAction('creaTrabajo'      , 'Crea trabajo'          , 8, 'ajax');
$actionObj->setAction('consultaTrabajos' , 'Consulta trabajos'     , 8, 'ajax');

$actionObj->startAll();

