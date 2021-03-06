<?php

class Controller
{
    // {{{ properties
    public $projName;
    public $messages   = array();

    /**
     * Action properties.
     *
     * These properties are gathered from every call to setAction.
     *
     * @var array
     */
    private $actionDefs = array();

    /**
     * specialActDefs
     *
     * Default names for login/logout/start actions.
     * @var array
     */
    private $specialActDefs  = array('login' => 'login', 'logout' => 'logout', 'start' => 'start');

    private $currActionCode;
    private $currActionParams;

    // Shortcut
    private $currActionDefs = null;

    public
        $authObj      = null,
        $modelManager = null,
        $mainViewObj  = null;

    private $loginMessage = '';

    // q.- Quiet, v.- Verbose, vv.- Very Verbose.
    public $verboseLevel;

    // these come from configuration
    public $dataDriver = array('dsn' => null, 'abl' => null);
    public $authOpts;

    // bootstrapper config
    private $indexFile = 'index.php';
    private
        $actionKey = 'a',
        $paramKey  = 'p';
    private $redirectUrl = '';

    // flags
    private
        $_hasDataDriver   = false,
        $_hasAuth         = false,
        $_propagateParams = 'string';
    // }}}

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
        if ($_SERVER['SERVER_PORT'] !== '80') {
            $this->redirectUrl = $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
        } else {
            $this->redirectUrl = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];         
        }
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        $this->finish();
    }

    /**
     * Defines a new action so that it gets properly managed by controller.
     *
     * @param string       Action's code.
     * @param string       Action's name.
     * @param integer      Action's profile.
     * @param string       Action's output type.
     * @return void
     */
    public function setAction($actCode, $actName, $actProfile = 1, $actOutput = 'html')
    {
        // Cannot define from outside an action called logout, even if it's html-out.
        if ($actCode == $this->specialActDefs['logout']) {
            $this->dieNow('Forbidden action name.');
        }
        $actProfile = intval($actProfile);
        $actProfile = ($actProfile != 0) ? $actProfile : 1;
        $actOutput = preg_replace("/[^A-Za-z-.]/", "", strval($actOutput));
        if ($actOutput === false || strlen($actOutput) == 0) {
            $this->dieNow('Invalid output type.');
        }

        $this->actionDefs[$actCode] = array(
            'name' => $actName
          , 'perm' => $actProfile
          , 'outf' => $actOutput
        );

        switch ($actOutput) {
        case 'html-in':
            $this->specialActDefs['login'] = $actCode;
            break;
        case 'html-out':
            $this->specialActDefs['logout'] = $actCode;
            break;
        case 'html-start':
            // if overriding start action
            $this->specialActDefs['start'] = $actCode;
            break;
        }
    }

    /**
     * Defines the way the FW gets all the data.
     *
     * This must be a dsn if using a DataBase.
     *
     * @param string       (Optional) DSN if using DataBase. Can be null or empty to emphasize not using DB.
     * @param string       (Optional) DB abstraction layer to be used. If omitted, FactoryModel decides which one to use.
     * @return void
     */
    public function setDataDriver($ddriver = null, $abstractionLayer=null)
    {
        $this->_hasDataDriver = true;
        if (!is_null($ddriver) && strlen($ddriver) > 0) {
            $this->dataDriver['dsn'] = $ddriver;
            if (is_string($abstractionLayer) && strlen($abstractionLayer) > 0) {
                $this->dataDriver['abl'] = $abstractionLayer;
            }
        }
    }

    /**
     * Defines the way the FW performs auth.
     *
     * @param array       Hash with the parameters to validate and query users info.
     * @return void
     */
    public function useAuth($authConf)
    {
        $this->authOpts = $authConf;
    }

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
    }

    /**
     * Starts the framework actions.
     *
     * @param string       Module to be executed.
     * @param string       Optional parameters.
     * @return void
     */
    public function start($action=null, $params=null) {
        if ( ! isset($_SERVER['REDIRECT_URL'])) {
            if ($action == null) {
                $action = (isset($_REQUEST[$this->actionKey])) ? $_REQUEST[$this->actionKey] : '';
            }
            if ($params == null) {
                $params = (isset($_REQUEST[$this->paramKey])) ? $_REQUEST[$this->paramKey] : '';
            }
        } else {
            $baseDir = dirname($_SERVER['SCRIPT_NAME']);
            $requestToken = str_replace($baseDir, '', $_SERVER['REDIRECT_URL']);
            preg_match('/\/([^\/]*)\/?(.*)/', $requestToken, $matches);
            $action = $matches[1];
            $params = $matches[2];
        }

        // sanitize action code.
        $this->currActionCode = trim(preg_replace("/[^A-Za-z0-9_-]/", "", $action));

        if (strlen($this->currActionCode) == 0) {
            $this->currActionCode = $this->specialActDefs['start'];
        }

        // test if action is not registered, in which case start action is followed
        if (!in_array($this->currActionCode, array_keys($this->actionDefs))) {
            $this->_logMessage("There is no registered action called '{$this->currActionCode}'.");
            $this->currActionCode = $this->specialActDefs['start'];
        }

        $this->currActionParams = trim($params, " /\t\n\r\0\x0B");
        if ($this->_propagateParams === 'array') {
          //&& (strpos($this->currActionParams, '/') !== false)) {
            $this->currActionParams = explode('/', $this->currActionParams);
        }

        // shortcuts
        $this->currActionDefs = $this->actionDefs[$this->currActionCode];

        /*
        * ModelManager
        */
        if ($this->_hasDataDriver) {
            $this->setModelObj(FactoryModel::CreateByConfig($this->dataDriver['dsn'], $this->dataDriver['abl']));
        }

        /*
        * Auth
        */
        $this->authObj = null;
        if ( isset($this->authOpts) && is_array($this->authOpts) && $this->_hasDataDriver
            // TODO: leave auth if action has visitor profile:
          && $this->currActionDefs['perm'] > 1) {
            $this->authOpts['sessionName'] = "Session_" . $this->projName;

            // Always prevent logs from ajax or json actions
            if ($this->_isAjax()) {
                $this->authOpts['authLogLevel'] = "";
            }

            $this->authObj = FactoryAuth::CreateAuth($this->authOpts, $this->modelManager);
            $this->_hasAuth = true;
            $this->authObj->start();

            // Try to Auth user, and fix action names.
            // process logout
            if ($this->currActionCode == $this->specialActDefs['logout']) {
                if ($this->authObj->checkAuth()) {
                    $this->_logMessage("User '{$this->authObj->getUsername()}' logs out.");
                    $this->authObj->logout();
                }
                $this->redirectUrl = str_replace('logout', '', $this->redirectUrl);
                header("Location: http://{$this->redirectUrl}");
                exit();
            } else {
                if ( ! $this->authObj->checkAuth()) {
                    if ( ! $this->authObj->tryLogin()) {
                        $this->currActionCode = $this->specialActDefs['login'];
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
                    } else {
                        // successfully logged in
                        if ($this->_isAjax()) {
                            header("Location: http://{$this->redirectUrl}");
                            exit();
                        }
                    }
                }
            }
        } else {
            // Avoid nonsense: no auth at all, but there's a login or logout.
            if ( $this->currActionCode == $this->specialActDefs['login']
              || $this->currActionCode == $this->specialActDefs['logout']) {
                $this->currActionCode = $this->specialActDefs['start'];
            }
        }

        /*
        * View
        */
        $this->setViewObj(FactoryView::CreateByOutFormat($this->currActionDefs['outf']));
    }

    /**
     * Runs framework.
     *
     * @return void
     */
    public function run()
    {
        $this->_logMessage("Executing action '{$this->currActionCode}'.");

        if ($this->currActionCode == $this->specialActDefs['login']) {
            $this->mainViewObj->nfw_loginAction($this->loginMessage);
            return;
        }

        $actionFunc = $this->currActionCode . 'Action';

        // tries to execute non authorized action, even though user is authenticated.
        if ($this->_hasAuth) {
            $profile = $this->authObj->getProfile();
            if ( ! ($this->_profileInAction($profile, $this->currActionDefs['perm']))) {
                $this->mainViewObj->nfw_dieWithMessage("The module '{$this->currActionCode}' is not authorized for your profile ($profile).");
                $this->dieNow('');
            }
        }

        // TODO: #5: Sharing data between Auth and Model.
        if ($this->_hasDataDriver && $this->_hasAuth) {
            $authDataArr = $this->authObj->getAuthData();
            $authDataArr['usu_username'] = $this->authObj->getUsername();
            $this->modelManager->receiveAuthData($authDataArr);
        }

        // Invokes model
        if ($this->modelManager && method_exists($this->modelManager, $actionFunc)) {
            $result = $this->modelManager->$actionFunc($this->currActionParams);
        } else {
            $result = null;
        }

        // Invokes view
        if (method_exists($this->mainViewObj, $actionFunc)) {
            $viewRetCode = $this->mainViewObj->$actionFunc($result);
            if ( $viewRetCode == -1) {
                $this->dieNow("Error en 'View'.");
            }
        } else if ($this->_isAjax()) {
            // ajax and json views are not mandatory.
            $viewRetCode = $this->mainViewObj->show($result);
            if ( $viewRetCode == -1) {
                $this->dieNow("Error en 'View'.");
            }
        } else {
            echo "View has no method for the current action '{$this->currActionCode}'.";
        }
    }

    /**
     * Performs final tasks for framework, if any.
     *
     * @return void
     * @access public
     */
    public function finish()
    {
        static $finishCalled = 0;
        if ($finishCalled === 0) {
            $this->printMessages();
            if ($this->_hasAuth) {
                $this->authObj->finish();
            }
        }
        $finishCalled++;
    }


    /**
     * Sets the propagation of params as an array, if more than one has been given.
     *
     * After this call, every action receives an array with
     * the parameters, exploding the slashes '/'.
     *
     * @return void
     */
    public function propagateParamsAsArrays()
    {
        $this->_propagateParams = 'array';
    }

    /**
     * Sets the model object to be used.
     *
     * @param Object The model object to be set.
     * @return void
     */
    protected function setModelObj(Model $obj)
    {
        $this->modelManager = $obj;
    }

    /**
     * Sets the view object to be used.
     *
     * @param Object The view object to be set.
     * @return void
     */
    protected function setViewObj(ViewBase $obj)
    {
        $this->mainViewObj = $obj;
        $this->mainViewObj->setControllerObj($this);
    }

    /**
     * Aborts framework execution. Calls finish() among others.
     *
     * @param string The message to show before dying.
     * @return void
     */
    public function dieNow($msg)
    {
        echo $msg;
        $this->finish();
        die();
    }

    /**
     * Stores a message to be diplayed later.
     *
     * @param string    Message to be stored.
     * @return void
     */
    protected function _logMessage($msg)
    {
        $this->messages[] = $msg;
    }

    /**
     * Prints the stored messages, if the current verbose level allows that.
     *
     * @return void
     */
    protected function printMessages() {
        if ($this->verboseLevel == 'v' || $this->verboseLevel == 'vv') {
            foreach ($this->messages as $msg) {
                $this->mainViewObj->nfw_printMessage($msg);
            }
        }
    }

    /**
     * Tells if the profile of the current user can run a requested action.
     *
     * @param integer    Profile of the current user.
     * @param integer    Action's permission being requested (sum of powers of 2).
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
    }


    /**
     * _isAjax
     *
     * Tells if the current action is of ajax type (ajax | json).
     *
     * @return boolean
     */
    private function _isAjax() {
        if ( $this->currActionDefs['outf'] == 'ajax'
          || $this->currActionDefs['outf'] == 'json') {
            return true;
        }
        return false;
    }


    /**
     * Returns the name of the current project.
     *
     * @return string
     */
    public function getProjName()
    {
        return $this->projName;
    }

    /**
     * Returns the name of the current action.
     *
     * @return string
     */
    public function getCurrentAction()
    {
        return $this->currActionCode;
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
