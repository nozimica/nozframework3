<?php
require_once '_Model.php';
require_once '_View.php';
require_once '_Authentication.php';

class Controller {
    // {{{ properties
    public $projName;
    public $perms      = array();
    public $names      = array();
    public $outformats = array();
    public $messages   = array();

    public $authObj;
    public $modelManager = null;
    public $mainViewObj;

    public $actionName;
    public $actionParams;
    public $afterLoginAction;
    // Default names for login/logout/start actions.
    public $loginlogoutstart  = array('login' => 'login', 'logout' => 'logout', 'start' => 'start');
    private $loginMessage = '';

    // q.- Quiet, v.- Verbose, vv.- Very Verbose.
    public $verboseLevel;

    // these come from configuration
    public $dataDriver = array('dsn' => null, 'interface' => null);
    public $authOpts;

    // bootstrapper config
    private $indexFile = 'index.php';
    private $actionKey = 'accion';

    // flags
    private $_dataDriverSet = false;
    private $_hasAuth       = false;
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
        if ( ! isset($_SERVER['SERVER_NAME']))    $_SERVER['SERVER_NAME'] = 'localhost';
        $this->projName     = $projName;
        $this->verboseLevel = $verboseLevel;
        // Force needed features for login and logout
        $this->outformats[$this->loginlogoutstart['login']]  = 'html-in';
        $this->outformats[$this->loginlogoutstart['logout']] = 'html';
        $this->names[$this->loginlogoutstart['login']]  = '';
        $this->names[$this->loginlogoutstart['logout']] = '';
    } // }}}

    // {{{ setAction()
    /**
     * Defines a new action so that it gets properly managed by controller.
     *
     * @param string       Action's code.
     * @param string       Action's name.
     * @param string       Action's profile.
     * @param string       Action's output type.
     * @return void
     */
    public function setAction($actCode, $actName, $actProfile, $actOutput)
    {
        if ($actCode == $this->loginlogoutstart['logout']) {
            $this->dieNow('Forbidden action name.');
        }
        $this->perms[$actCode]      = ($actProfile != 0) ? $actProfile : 1;
        $this->names[$actCode]      = $actName;
        $this->outformats[$actCode] = (isset($actOutput)) ? $actOutput : 'html';
        //if ($this->outformats[$actCode] == 'html-in')    $this->loginlogoutstart['login'] = $actCode;
        //if ($this->outformats[$actCode] == 'html-out')   $this->loginlogoutstart['logout'] = $actCode;
        if ($this->outformats[$actCode] == 'html-start') $this->loginlogoutstart['start'] = $actCode;
    } //}}}
    // {{{ setDataDriver()
    /**
     * Defines the way the FW gets all the data.
     *
     * This must be a dsn if using a DataBase.
     *
     * @param string       DSN if using DataBase.
     * @param string       (Optional) Class to be used: PDO (default) or PEAR::DB or PEAR::MDB2.
     * @return void
     */
    public function setDataDriver($ddriver, $interf='PDO')
    {
        $_avails = array('PDO', 'PEAR::DB', 'PEAR::MDB2');
        if (! in_array($interf, $_avails)) {
            $this->dieNow('Bad DB interface selected. Must be one among: [' . implode(', ', $_avails) . '].');
        }
        $this->dataDriver['dsn'] = $ddriver;
        $this->dataDriver['interface'] = $interf;
        $this->_dataDriverSet = true;
    } //}}}
    // {{{ useAuth()
    /**
     * Defines the way the FW performs auth.
     *
     * @param array       Hash with the parameters to validate and query users info.
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
        $this->start();
        $this->run();
        $this->finish();
    } //}}}
    // {{{ start()
    /**
     * Starts the framework actions.
     *
     * @param string       Module to be executed.
     * @param string       Optional parameters.
     * @return void
     */
    public function start($action=null, $params=null) {
        if (strlen($this->loginlogoutstart['start']) == 0) {
            $this->dieNow('There must be a start action.');
        }

        if ($action == null) {
            $action = (isset($_REQUEST[$this->actionKey])) ? $_REQUEST[$this->actionKey] : '';
        }
        if ($params == null) {
            $params = (isset($_REQUEST['params'])) ? $_REQUEST['params'] : '';
        }

        $this->actionName = trim(preg_replace("/[^A-Za-z0-9_-]/", "", $action));

        if (strlen($this->actionName) == 0) {
            $this->actionName = $this->loginlogoutstart['start'];
        }
        $this->actionParams = trim($params);

        // not registered action
        if (!in_array($this->actionName, array_keys($this->names))) {
            if ($this->verboseLevel == 'v' || $this->verboseLevel == 'vv') {
                $this->dieNow("There is no registered action called '{$this->actionName}'.");
            }
            $this->actionName = $this->loginlogoutstart['start'];
        }

        /*
        * ModelManager
        */
        if ($this->_dataDriverSet) {
            $this->modelManager = FactoryModel::CreateByConfig($this->dataDriver);
        }

        /*
        * Auth
        */
        $this->authObj = null;
        if (isset($this->authOpts) && is_array($this->authOpts) && $this->_dataDriverSet) {
            $this->authOpts['sessionName'] = "Session_" . $this->projName;

            // Always prevent logs from ajax actions
            if ($this->outformats[$this->actionName] == 'ajax') {
                $this->authOpts['authLogLevel'] = "";
            }

            $this->authObj = FactoryAuth::CreateAuth($this->authOpts, $this->modelManager);
            $this->_hasAuth = true;
            $this->authObj->start();

            // Try to Auth user, and fix action names.
            // process logout
            if ($this->actionName == $this->loginlogoutstart['logout']) {
                if ($this->authObj->checkAuth()) {
                    $this->_logMessage("User '{$this->authObj->getUsername()}' logs out.");
                    $this->authObj->logout();
                }
                $host  = $_SERVER['HTTP_HOST'];
                $uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                header("Location: http://$host$uri/{$this->indexFile}");
                exit();
            } else {
                if ( ! $this->authObj->checkAuth()) {
                    if ( ! $this->authObj->tryLogin()) {
                        $this->afterLoginAction = $this->actionName;
                        $this->actionName = $this->loginlogoutstart['login'];
                        switch ($this->authObj->getStatus()) {
                          case AUTH_STATUS_WRONG_LOGIN:
                            $this->loginMessage = 'Login failed.';
                            break;
                          case AUTH_STATUS_IDLED:
                            $this->loginMessage = 'Login idled.';
                            break;
                          case AUTH_STATUS_EXPIRED:
                            $this->loginMessage = 'Login expired.';
                            break;
                        }
                    }
                }
            }
        } else {
            // Avoid nonsense
            if ( $this->actionName == $this->loginlogoutstart['login']
              || $this->actionName == $this->loginlogoutstart['logout']) {
                $this->actionName = $this->loginlogoutstart['start'];
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
        $this->_logMessage("Executing action '{$this->actionName}'.");
        $actionFunc = $this->actionName . 'Action';

        if ($this->actionName == $this->loginlogoutstart['login']) {
            $afterLogin = sprintf("%s?%s=%s", $this->indexFile, $this->actionKey, $this->afterLoginAction);
            $viewRetCode = $this->mainViewObj->nfw_loginAction($this->loginMessage, $afterLogin);
            if ( $viewRetCode == -1) {
                $this->dieNow("Error en 'View'.");
            }
            return;
        }

        // tries to execute non authorized action, even though user is authenticated.
        if ($this->_hasAuth) {
            $profile = $this->authObj->getProfile();
            if ( ! ($this->_profileInAction($profile, $this->perms[$this->actionName]))) {
                $this->mainViewObj->nfw_dieWithMessage("The module '{$this->actionName}' is not authorized for your profile ($profile).");
                $this->dieNow();
            }
        }

        // TODO: #5: Sharing data between Auth and Model.
        $authDataArr = $this->authObj->getAuthData();
        $authDataArr['usu_username'] = $this->authObj->getUsername();
        $this->modelManager->receiveAuthData($authDataArr);

        if ($this->modelManager && method_exists($this->modelManager, $actionFunc)) {
            $result = $this->modelManager->$actionFunc($this->actionParams);
        } else {
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
            $viewRetCode = $this->mainViewObj->$actionFunc($result);
            if ( $viewRetCode == -1) {
                $this->dieNow("Error en 'View'.");
            }
        } else {
            echo "View has no method for the current action '{$this->actionName}'.";
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
        $this->printMessages();
        if ($this->_hasAuth) {
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
    // {{{ printMessages()
    /**
     * Prints the stored messages, if the verbose levels allow that.
     *
     * @return void
     */
    protected function printMessages() {
        if ($this->verboseLevel == 'v' || $this->verboseLevel == 'vv') {
            foreach ($this->messages as $msg) {
                $this->mainViewObj->nfw_printMessage($msg);
            }
        }
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
