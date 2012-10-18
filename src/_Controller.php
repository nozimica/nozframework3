<?php
require_once '_Model.php';
require_once '_View.php';
require_once '_Authentication.php';

class Controller {
    // {{{ properties
    public $projName;
    public $perms    = array();
    public $names    = array();
    public $outformats = array();
    public $messages = array();

    public $authObj;
    public $modelManager;
    public $mainViewObj;

    public $actionName;
    public $actionParams;
    // Default names for login/logout/start actions.
    public $loginlogoutstart  = array('login' => 'login', 'logout' => 'logout', 'start' => 'start');

    // q.- Quiet, v.- Verbose, vv.- Very Verbose.
    public $verboseLevel;

    // these come from configuration
    public $dataDriver;
    public $authOpts;
    private $actionsArr;
    // }}}

    // {{{ Controller() [constructor]
    /**
     * Constructor
     *
     * Sets up the Controller layer.
     *
     * @param string    The codename of this project.
     * @param char      Verbosity level. May be 'q', 'v', 'vv'.
     * @return void
     */
    public function __construct($projName, $verboseLevel = 'q')
    {
        $this->projName       = $projName;
        $this->actionsArr     = array();
        $this->dataDriver     = null;

        if ( ! isset($_SERVER['SERVER_NAME']))    $_SERVER['SERVER_NAME'] = 'localhost';

        $this->verboseLevel = $verboseLevel;
    } // }}}

    // {{{ setAction()
    /**
     * Defines a new action so that it gets properly managed by controller.
     *
     * @param accion string       Action's code.
     * @param accion string       Action's name.
     * @param accion string       Action's profile.
     * @param accion string       Action's output type.
     * @return void
     */
    public function setAction($actCode, $actName, $actProfile, $actOutput)
    {
        $this->actionsArr[$actCode] = array('name' => $actName, 'profile' => $actProfile, 'output' => $actOutput);
    } //}}}

    // {{{ setDataDriver()
    /**
     * Defines the way the FW gets all the data.
     *
     * This must be a dsn if using a DataBase.
     *
     * @param accion string       DSN if using DataBase.
     * @return void
     */
    public function setDataDriver($ddriver)
    {
        $this->dataDriver = $ddriver;
    } //}}}

    // {{{ useAuth()
    /**
     * Defines the way the FW performs auth.
     *
     * @param accion array       Hash with the parameters to validate and query users info.
     * @return void
     */
    public function useAuth($authConf)
    {
        $this->authOpts = $authConf;
    } //}}}

    // {{{ startAll()
    /**
     * Performs all the steps of the framework, one by one.
     *
     * @return void
     */
    public function startAll()
    {
        $this->start($accion, $params);
        $this->run();
        $this->finish();
    } //}}}

    // {{{ start()
    /**
     * Inicia las acciones del framework.
     *
     * @param accion string       Módulo a ejecutar por el controlador.
     * @param params string       Parámetros opcionales.
     * @return void
     */
    public function start($accion=null, $params=null) {
        if ($accion == null) {
            $accion = (isset($_REQUEST['accion'])) ? $_REQUEST['accion'] : '';
        }
        if ($params == null) {
            $params = (isset($_REQUEST['params'])) ? $_REQUEST['params'] : '';
        }
        foreach ($this->actionsArr as $act_key => $act_i) {
            $this->perms[$act_key]      = ($act_i['profile'] != 0) ? $act_i['profile'] : 1;
            $this->names[$act_key]      = $act_i['name'];
            $this->outformats[$act_key] = (isset($act_i['output'])) ? $act_i['output'] : 'html';
            if ($this->outformats[$act_key] == 'html-in')    $this->loginlogoutstart['login'] = $act_key;
            if ($this->outformats[$act_key] == 'html-out')   $this->loginlogoutstart['logout'] = $act_key;
            if ($this->outformats[$act_key] == 'html-start') $this->loginlogoutstart['start'] = $act_key;
        }

        if (strlen(trim($accion)) == 0) {
            $this->actionName = $this->loginlogoutstart['start'];
        } else {
            $this->actionName = trim($accion);
        }
        $this->actionParams = trim($params);

        // not registered action
        if (!in_array($this->actionName, array_keys($this->names))) {
            if ($this->verboseLevel == 'v' || $this->verboseLevel == 'vv') {
                $this->dieNow("Acci&oacute;n {$this->actionName} no registrada");
            }
            $this->actionName = $this->loginlogoutstart['start'];
        }

        /*
        * ModelManager
        */
        $this->modelManager = FactoryModel::CreateByConfig($this->dataDriver);

        /*
        * Auth
        */
        $this->authObj     = null;
        if (isset($this->authOpts) && is_array($this->authOpts)) {
            $this->authOpts['sessionName'] = "Session_" . $this->projName;
            $this->authOpts['dsn']         = $this->dataDriver;

            // Always prevent logs from ajax actions
            if ($this->outformats[$this->actionName] == 'ajax') {
                $this->authOpts['authLogLevel'] = "";
            }

            $this->authObj = FactoryAuth::CreateAuth($this->authOpts);
            $this->authObj->start();
        }

        // Try to Auth user, and fix action names.
        if ( ! $this->_hasAuth()) {
            // Avoid nonsense
            if ( $this->actionName == $this->loginlogoutstart['login']
              || $this->actionName == $this->loginlogoutstart['logout']) {
                $this->actionName = $this->loginlogoutstart['start'];
            }
        } else {
            // process logout
            if ($this->actionName == $this->loginlogoutstart['logout']) {
                if ($this->authObj->checkAuth()) {
                    $this->_logMessage("User '{$this->authObj->getUsername()}' logs out.");
                    $this->authObj->logout();
                }
                $this->actionName = $this->loginlogoutstart['login'];
            } else {
                if ( ! $this->authObj->checkAuth()) {
                    if ( ! $this->authObj->tryLogin()) {
                        $this->actionName = $this->loginlogoutstart['login'];
                    } else if ($this->actionName == $this->loginlogoutstart['login']) {
                        $this->actionName = $this->loginlogoutstart['start'];
                    }
                } else if ($this->actionName == $this->loginlogoutstart['login']) {
                    $this->actionName = $this->loginlogoutstart['start'];
                }
            }
        }

        /*
        * View
        */
        $this->mainViewObj = FactoryView::CreateByOutFormat($this->outformats[$this->actionName]);
    } // }}}

    // {{{ run()
    /**
     * Runs framework.
     *
     * @return void
     */
    public function run()
    {
        // trata de ejecutar app no autorizada, habiendo autenticacion
        if ( $this->_hasAuth() ) {
            $profile = $this->authObj->getProfile();
            if ( ! ($this->_profileInAction($profile, $this->perms[$this->actionName]))) {
                die("M&oacute;dulo ( {$this->actionName} ) no autorizado para su perfil de usuario ($profile).");
            }
        }
        $this->_logMessage("Se ejecuta accion " . $this->actionName);

        $actionFunc = $this->actionName . 'Action';

        if (method_exists($this->modelManager, $actionFunc)) {
            $result = $this->modelManager->$actionFunc($this->actionParams);
        } else {
            //$result = "Error con llamado: " . $this->actionName;
            $result = null;
        }
        /** Failure from Model: let's see later if this is useful.
        if ($this->modelManager->hasFailed()) {
            list($accion, $params) = $this->modelManager->getActionAfterFailure();
            $this->start($accion, $params);
            $this->run();
            return false;
        }*/

        if (method_exists($this->mainViewObj, $actionFunc)) {
            $viewRetCode = $this->mainViewObj->$actionFunc($this->actionParams, $result);
            if ( $viewRetCode == -1) {
                $this->dieNow("Error en 'View'.");
            }
        } else {
            echo "View no posee m&eacute;todo para acci&oacute;n: " . $this->actionName;
        }
    } //}}}

    // {{{ finish()
    /**
     * Performs final tasks for framework, if any.
     *
     * @return void
     * @access public
     */
    public function finish()
    {
        if ($this->_hasAuth()) {
            $this->authObj->finish();
        }
    } // }}}

    // {{{ dieNow()
    /**
     * Aborts framework execution. Calls finish() among others.
     *
     * @return void
     * @see finish()
     */
    public function dieNow($msg)
    {
        echo $msg;
        $this->finish();
        die();
    } // }}}

    // {{{ _hasAuth()
    /**
     * Tells if the framework is actually using an Auth object.
     *
     * @return boolean
     */
    private function _hasAuth()
    {
        return !(is_null($this->authObj));
    } // }}}

    // {{{ _logMessage()
    /**
     * Stores a message to be diplayed later.
     *
     * @param string    Message to be stored.
     * @return void
     */
    protected function _logMessage($msg) {
        $this->messages[] = $msg;
    } // }}}

    // {{{ _profileInAction()
    /**
     * Tells if the profile of the current user can run a requested action.
     *
     * @param integer    Profile of the current user.
     * @param string     Action being requested.
     * @return boolean
     */
    private function _profileInAction($profile, $action) {
        if ($action == 0) {
            return true;
        }
        if ($profile == "") {
            $profile = 1;
        }
        $reverseBinary = strrev(decbin($action));
        if (isset($reverseBinary[($profile-1)]) && $reverseBinary[($profile-1)] == 1) {
            return true;
        }
        return false;
    } // }}}
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
