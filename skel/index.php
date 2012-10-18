<?php
error_reporting(E_ALL);

$pearPath = '../../lib/sharedPear/pear/php';
$frameworkPath = '../../lib/nozframework/src';

set_include_path($pearPath . PATH_SEPARATOR . get_include_path());

require_once $frameworkPath . '/_Controller.php';

require_once 'lib/ActionsController.php';
require_once 'lib/DataBaseManager.php';
require_once 'lib/ViewHelper.php';

$accion = (isset($_REQUEST['accion'])) ? $_REQUEST['accion'] : '';
$params = (isset($_REQUEST['params'])) ? $_REQUEST['params'] : '';

$actionObj = new ActionsController('conf/');
$actionObj->start($accion, $params);
$actionObj->run();
$actionObj->finish();

