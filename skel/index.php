<?php
error_reporting(E_ALL);

$frameworkPath = '__FW_HOME__/src';
set_include_path($frameworkPath . PATH_SEPARATOR . get_include_path());
require_once '_Controller.php';

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
    // db_fields: extra fields to be retrieved when validating user
    , 'db_fields'    => array('usu_email', 'usu_nombre')
);

$actionObj = new ActionsController('__PROJ_NAME__', '');

$actionObj->setDataDriver(__DB_DSN__);
//$actionObj->useAuth($authOptions);

$actionObj->setAction('inicio', 'Inicio', 8+4, 'html-start');

$actionObj->startAll();
