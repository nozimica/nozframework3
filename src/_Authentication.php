<?php
/**
 * The main file for Authentication.
 *
 * PHP version 5.3 or higher.
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Authentication
 * @author     Nicolas E. Ozimica <nozimica@gmail.com>
 * @copyright  2010-2012
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 */

/* {{{ Class FactoryAuth
 *
 */
class FactoryAuth {
    public static function CreateAuth($__authOpt) {
        $auth = new OzAuthManager('PDO', $__authOpt);
        return $auth;
    }
}
// }}}

// {{{ Defines
/**
 * Returned if session exceeds idle time
 */
define('AUTH_STATUS_OK',                       0);
/**
 * Returned if session exceeds idle time
 */
define('AUTH_STATUS_NORMAL_EXIT',              1);
/**
 * Returned if session exceeds idle time
 */
define('AUTH_STATUS_IDLED',                    -1);
/**
 * Returned if session has expired
 */
define('AUTH_STATUS_EXPIRED',                  -2);
/**
 * Returned if container is unable to authenticate user/password pair
 */
define('AUTH_STATUS_WRONG_LOGIN',              -3);
/**
 * Returned if no username has been given
 */
define('AUTH_STATUS_EMPTY_LOGIN',              -4);
/**
 * Returned if security system detects a breach
 */
define('AUTH_STATUS_SECURITY_BREACH',          -5);
/**
 * Returned if a container method is not supported.
 */
define('AUTH_STATUS_METHOD_NOT_SUPPORTED',     -6);

/**
 * Auth Log level - NONE
 */
define('AUTH_LOG_NONE',     5);
/**
 * Auth Log level - INFO
 */
define('AUTH_LOG_INFO',     6);
/**
 * Auth Log level - DEBUG
 */
define('AUTH_LOG_DEBUG',    7);
// }}}

/**
 * OzAuthManager
 *
 * Auth management class. Provides means to perform
 * authentication using PHP.
 *
 * @category   Authentication
 * @author     Nicolas E. Ozimica <nozimica@gmail.com>
 * @copyright  2010-2012
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 */
class OzAuthManager {

    // {{{ properties

    /**
     * Current authentication status
     *
     * @var string
     */
    private $_status = '';

    /**
     * Storage object
     *
     * @var object
     * @see Auth()
     */
    private $_storageObj;

    /**
     * Name of this Auth in session-array
     *
     * @var string
     */
    private $_sessionName;

    /**
     * Holds a reference to the session auth variable
     *
     * @var array
     */
    private $_session;

    /**
     * Username key in POST array
     *
     * @var string
     */
    private $_postUsername;

    /**
     * Password key in POST array
     *
     * @var string
     */
    private $_postPassword;

    /**
      * How many times has checkAuth() been called
      *
      * @var int
      * @see checkAuth()
      */
    private $_authChecks = 0;

    /**
      * Assoc array with config values for Driver, from user.
      *
      * @var array
      */
    private $_driverData = array('type' => null, 'opts' => null);

    /**
      * Flag to avoid multiple calls to start().
      *
      * @var array
      * @see start()
      */
    private $_alreadyStarted = false;

    /**
     * Username given by the user (if any).
     *
     * @var string
     */
    public $username;

    /**
     * Password given by the user (if any).
     *
     * @var string
     */
    public $password;

    /**
     * Name of the field (on whatever form the auth data is stored into)
     * that holds the profile information for an authenticated user.
     *
     * @var string
     */
    public $profileFieldName = NULL;

    /**
     * Auth lifetime in seconds
     *
     * If this variable is set to 0, auth never expires
     *
     * @var  integer
     * @see checkAuth()
     */
    public $expire;

    /**
     * Maximum idletime in seconds
     *
     * The difference to $expire is, that the idletime gets
     * refreshed each time checkAuth() is called. If this
     * variable is set to 0, idletime is never checked.
     *
     * @var integer
     * @see checkAuth()
     */
    public $idle;

    /**
     * Level of logs verbosity.
     *
     * Possible values: [AUTH_LOG_NONE || AUTH_LOG_INFO || AUTH_LOG_DEBUG]
     *
     * @var integer
     * @see log()
     */
    public $logLevel;

    /**
     * Whether to regenerate session id everytime start is called, or just
     * when the user logs in.
     *
     * @var boolean
     */
    private $_sessionIdAlwaysUpdated = false;

    // }}}
    // {{{ OzAuthManager() [constructor]

    /**
     * Constructor
     *
     * Sets up the Auth manager and its storage driver.
     *
     * @param string    Type of the storage driver
     * @param mixed     Additional options for the storage driver
     *                  (example: if you are using DB as the storage
     *                  driver, you have to pass the dsn string here)
     * @return void
     */
    public function __construct($driverType, $driverOpts)
    {
        $this->_status = AUTH_STATUS_OK;
        $this->_sanitizeInput($driverOpts);

        $this->_sessionName  = $driverOpts['sessionName'];
        $this->_postUsername = $driverOpts['postUsername'];
        $this->_postPassword = $driverOpts['postPassword'];
        $this->_setLogLevel($driverOpts['authLogLevel']);

        if (isset($driverOpts['profilecol'])) {
            if (is_array($driverOpts['profilecol'])) {
                $this->profileFieldName = $driverOpts['profilecol'][1];
            } else {
                $this->profileFieldName = $driverOpts['profilecol'];
            }
        }
        // To be defined by the user.
        $this->expire    = 36000;
        $this->idle      = 1000;
        $this->_driverData = array('type' => $driverType, 'opts' => $driverOpts);

    } // }}}

    // {{{ _sanitizeInput()
    /**
     * Ensures that the two input arrays have all the keys needed, and those undefined
     * by the caller get here their default values.
     *
     * @param  array   Reference to the driver options array.
     * @return void
     * @access private
     */
    private function _sanitizeInput(&$driverOpts)
    {
        $driverOptsNew = array_merge(array(
              'table'          => 'usuario'
              , 'usernamecol'  => 'usu_username'
              , 'passwordcol'  => 'usu_password'
              , 'profilecol'   => array('usu_rol_id', 'usu_perfil')
              , 'db_fields'    => array('usu_email')
              , 'authLogLevel' => 'none'
              , 'postUsername' => 'username'
              , 'postPassword' => 'password')
            , $driverOpts);
        // Check restricted variables
        $driverOptsNew['authLogLevel'] = strtolower($driverOptsNew['authLogLevel']);
        if ( ! in_array($driverOptsNew['authLogLevel'], array('none', 'info', 'debug'))) {
            $driverOptsNew['authLogLevel'] = 'none';
        }
        $driverOpts = $driverOptsNew;
    } // }}}

    /** Main methods */
    // {{{ start()

    /**
     * Starts new auth session
     *
     * @return void
     * @access public
     */
    public function start()
    {
        if ( ! $this->_alreadyStarted) {
            $this->_alreadyStarted = true;
            $this->log('start() called.', AUTH_LOG_DEBUG);

            // Start the session
            if( "" === session_id()) {
                @session_start() or die("Problem with session start");
                if( "" === session_id()) {
                    $this->log('Session could not be started.', AUTH_LOG_INFO);
                    die();
                }
            }

            // Make Sure Auth session variable is there
            if( ! isset($_SESSION[$this->_sessionName])) {
                $_SESSION[$this->_sessionName] = array();
            }
            $this->_session =& $_SESSION[$this->_sessionName];

            $this->_storageObj = new OzAuthStorage($this->_driverData['type'], $this->_driverData['opts']);
        } else {
            $this->log('Wrong call to start(): it has already been called.', AUTH_LOG_DEBUG);
            die();
        }

        if ($this->_sessionIdAlwaysUpdated) {
            session_regenerate_id(true);
        }

        if ( isset($_POST[$this->_postUsername])
          && trim($_POST[$this->_postUsername]) != '') {
            $this->username = trim($_POST[$this->_postUsername]);
        }
        if ( isset($_POST[$this->_postPassword])
          && trim($_POST[$this->_postPassword]) != '') {
            $this->password = trim($_POST[$this->_postPassword]);
        }
        /*if (!$this->checkAuth() && $this->mustLogin) {
            $this->tryLogin();
        }*/
    } // }}}
    // {{{ checkAuth()

    /**
     * Checks if there is a session with valid auth information.
     *
     * @access public
     * @return boolean  Whether or not the user is authenticated.
     */
    public function checkAuth()
    {
        $this->log('checkAuth() called.', AUTH_LOG_DEBUG);

        $this->_authChecks++;
        if (isset($this->_session)) {
            // Check if authentication session is expired
            if ( $this->expire > 0
              && isset($this->_session['loginTimestamp'])
              && ($this->_session['loginTimestamp'] + $this->expire) < time()) {
                $this->log('Session Expired.', AUTH_LOG_INFO);
                $this->_status = AUTH_STATUS_EXPIRED;
                $this->logout();
                return false;
            }

            // Check if maximum idle time is reached
            if ( $this->idle > 0
              && isset($this->_session['lastTimestamp'])
              && ($this->_session['lastTimestamp'] + $this->idle) < time()) {
                $this->log('Session Idle Time Reached.', AUTH_LOG_INFO);
                $this->_status = AUTH_STATUS_IDLED;
                $this->logout();
                return false;
            }

            if ( isset($this->_session['registered'])
              && isset($this->_session['username'])
              && $this->_session['registered'] === true
              && $this->_session['username'] != '') {

                $this->_session['lastTimestamp'] = time();

                // Only Generate the challenge once
                if ($this->_authChecks == 1) {
                    $this->log('Generating new Challenge Cookie.', AUTH_LOG_INFO);
                    $this->_updateChallengeCookies();
                }

                // Check if the IP of the user has changed, if so we
                // assume a man in the middle attack and log him out
                if ( isset($_SERVER['REMOTE_ADDR'])
                  && $this->_session['sessionip'] != $_SERVER['REMOTE_ADDR']) {
                    $this->log('Security Breach. Remote IP Address changed.', AUTH_LOG_INFO);
                    $this->_status = AUTH_STATUS_SECURITY_BREACH;
                    $this->logout();
                    return false;
                }

                // Check if the IP of the user connecting via proxy has
                // changed, if so we assume a man in the middle attack and log him out.
                if ( isset($_SERVER['HTTP_X_FORWARDED_FOR'])
                  && $this->_session['sessionforwardedfor'] != $_SERVER['HTTP_X_FORWARDED_FOR']) {
                    $this->log('Security Breach. Forwarded For IP Address changed.', AUTH_LOG_INFO);
                    $this->_status = AUTH_STATUS_SECURITY_BREACH;
                    $this->logout();
                    return false;
                }

                // Check if the User-Agent of the user has changed, if
                // so we assume a man in the middle attack and log him out
                if ( isset($_SERVER['HTTP_USER_AGENT'])
                  && $this->_session['sessionuseragent'] != $_SERVER['HTTP_USER_AGENT']) {
                    $this->log('Security Breach. User Agent changed.', AUTH_LOG_INFO);
                    $this->_status = AUTH_STATUS_SECURITY_BREACH;
                    $this->logout();
                    return false;
                }

                // Check challenge cookie here, if challengecookieold is not set
                // this is the first time and check is skipped
                // TODO when user open two pages similtaneuly (open in new window,open
                // in tab) auth breach is caused find out a way around that if possible
                if ( isset($this->_session['challengecookieold'])
                  && $this->_session['challengecookieold'] != $_COOKIE['authchallenge']) {
                    $this->log('Security Breach. Challenge Cookie mismatch.', AUTH_LOG_INFO);
                    $this->_status = AUTH_STATUS_SECURITY_BREACH;
                    $this->logout();
                    $this->tryLogin();
                    return false;
                }

                $this->log('Session OK.', AUTH_LOG_INFO);
                return true;
            }
        } else {
            $this->log('Unable to locate session storage.', AUTH_LOG_DEBUG);
            return false;
        }
        $this->log('No login session.', AUTH_LOG_INFO);
        return false;
    } // }}}
    // {{{ tryLogin()

    /**
     * Attempts to validate current credentials.
     *
     * Internally calls checkauth() if username and passwords match.
     *
     * @return boolean
     * @access public
     * @see checkAuth()
     */
    public function tryLogin()
    {
        $this->log('tryLogin() called.', AUTH_LOG_DEBUG);

        $login_ok = false;

        // When the user has already entered a username, we have to validate it.
        if (!empty($this->username)) {
            if (true === $this->_storageObj->checkCredentials($this->username, $this->password)) {
                $this->_session['challengekey'] = md5($this->username.$this->password);
                $login_ok = true;
                $this->log('Successful login.', AUTH_LOG_INFO);
                $this->setAuth($this->username);
            } else {
                $this->log('Incorrect login.', AUTH_LOG_INFO);
                $this->_status = AUTH_STATUS_WRONG_LOGIN;
            }
        } else {
            $this->log('Empty username.', AUTH_LOG_INFO);
            $this->_status = AUTH_STATUS_EMPTY_LOGIN;
        }

        if ($login_ok) {
            return $this->checkAuth();
        }
        return false;
    } // }}}
    // {{{ logout()

    /**
     * Logout
     *
     * This function clears any auth tokens in the currently active session.
     *
     * @access public
     * @return void
     */
    public function logout()
    {
        if ($this->_status == AUTH_STATUS_OK) {
            $this->_status = AUTH_STATUS_NORMAL_EXIT;
        }
        $this->log("logout() called, with status: {$this->_status}", AUTH_LOG_DEBUG);

        $this->username = '';
        $this->password = '';

        $this->_session = array();
        if (ini_get("session.use_cookies")) {
            $cookieParams = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                    $cookieParams["path"], $cookieParams["domain"],
                    $cookieParams["secure"], $cookieParams["httponly"]
                    );
        }
        session_destroy();

    } // }}}
    // {{{ finish()

    /**
     * Finish
     *
     * This function performs the final actions for this object.
     *
     * @return void
     * @access public
     */
    public function finish()
    {
        $this->log('finish() called.', AUTH_LOG_DEBUG);
    } // }}}

    /** Getters and setters */
    // {{{ setAuth()

    /**
     * Register variable in a session telling that the user
     * has logged in successfully
     *
     * @param  string Username
     * @return void
     * @access public
     */
    public function setAuth($username)
    {
        $this->log('Auth::setAuth() called.', AUTH_LOG_DEBUG);

        // Change the session id to avoid session fixation attacks php 4.3.3 >
        if ( ! $this->_sessionIdAlwaysUpdated) {
            session_regenerate_id(true);
        }

        if ( ! isset($this->_session) || !is_array($this->_session)) {
            $this->_session = array();
        }

        /* Register additional information that is to be stored in the session. */
        $this->_session['userData'] = $this->_storageObj->getAuthData();

        $this->_session['sessionip'] = isset($_SERVER['REMOTE_ADDR'])
            ? $_SERVER['REMOTE_ADDR']
            : '';
        $this->_session['sessionuseragent'] = isset($_SERVER['HTTP_USER_AGENT'])
            ? $_SERVER['HTTP_USER_AGENT']
            : '';
        $this->_session['sessionforwardedfor'] = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? $_SERVER['HTTP_X_FORWARDED_FOR']
            : '';

        // This should be set by the container to something more safe
        // Like md5(passwd.microtime)
        if (empty($this->_session['challengekey'])) {
            $this->_session['challengekey'] = md5($username.microtime());
        }

        $this->_updateChallengeCookies();

        $this->_session['registered'] = true;
        $this->_session['username']   = $username;
        $this->_session['loginTimestamp'] = time();
        $this->_session['lastTimestamp']  = time();
    } // }}}
    // {{{ getAuthData()

    /**
     * Returns additional information that is stored in the session.
     *
     * If no value for the first parameter is passed, the method will
     * return all data that is currently stored.
     *
     * @param  string Name of the data field
     * @return mixed  Value of the data field.
     * @access public
     */
    function getAuthData($key = null)
    {
        if (isset($this->_session['userData'])) {
            if( ! isset($key)) {
                return $this->_session['userData'];
            }
            if (isset($this->_session['userData'][$key])) {
                return $this->_session['userData'][$key];
            }
        }
        return NULL;
    } // }}}
    // {{{ getUsername()

    /**
     * Returns the username
     *
     * @return string
     * @access public
     */
    public function getUsername()
    {
        if (isset($this->_session['username'])) {
            return $this->_session['username'];
        }
        return '';
    } // }}}
    // {{{ getProfile()

    /**
     * Returns the user profile (if defined)
     *
     * @return string
     * @access public
     */
    public function getProfile()
    {
        return $this->getAuthData($this->profileFieldName);
    } // }}}
    // {{{ getStatus()

    /**
     * Returns the status of the current session.
     *
     * @return integer
     * @access public
     */
    public function getStatus()
    {
        return $this->_status;
    } // }}}
    // {{{ _setLogLevel()

    /**
     * Sets the current log level.
     *
     * Converts the ad-hoc input parameter to one of the related constants.
     * This variable is already sanitized, so no default or fallback case is needed.
     *
     * @param  string   The input parameter.
     * @return void
     * @access private
     */
    private function _setLogLevel($logLevelStr)
    {
        switch($logLevelStr) {
          case 'debug':
            $this->logLevel = AUTH_LOG_DEBUG;
            break;
          case 'info':
            $this->logLevel = AUTH_LOG_INFO;
            break;
          case 'none':
            $this->logLevel = AUTH_LOG_NONE;
            break;
        }
    } // }}}

    /** Misc */
    // {{{ log()

    /**
     * Logs a message.
     *
     * It's actually displayed according to the current output level.
     *
     * @param  string  Message to log.
     * @param  string  Level (predefined constant) this message belongs to.
     * @return void
     * @access public
     */
    private function log($msg, $level)
    {
        static $levelNameArr = array(AUTH_LOG_INFO => 'Info', AUTH_LOG_DEBUG => 'DEBUG');
        if ($this->logLevel >= $level) {
            echo "<br />** ({$levelNameArr[$level]}) $msg ** <br />";
        }
    } // }}}
    // {{{ _updateChallengeCookies()

    /**
     * Updates challengecookies.
     * TODO: review this feature.
     *
     * @return void
     * @access private
     */
    private function _updateChallengeCookies()
    {
        if (isset($this->_session['challengecookie'])) {
            $this->_session['challengecookieold'] = $this->_session['challengecookie'];
        }
        $this->_session['challengecookie'] = md5($this->_session['challengekey'].microtime());
        setcookie('authchallenge', $this->_session['challengecookie'], 0, '/', '', false, true);
    } // }}}
}

class OzAuthStorage {
    private $_type;
    private $_options;
    private $_dbObj = NULL;
    private $_querysArr;
    private $_checkedUserData = array();

    public function __construct($type, $storageOpts)
    {
        $this->_type = $type;
        $this->_options = $storageOpts;
        if ($type == 'PDO') {
            $this->_dbObj = new PDO($this->_options['dsn']) or die("PDO: Problem with DB.");
            $this->_dbObj->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); 

            $selectFields = $this->_dbGetSelectFields();
            $this->_querysArr = array('check' => "SELECT $selectFields
                                                  FROM {$this->_options['table']}
                                                  WHERE {$this->_options['usernamecol']} = :user");
        }
    }

    public function checkCredentials($user, $pass)
    {
        if ($this->_dbObj) {
            $stm = $this->_dbObj->prepare($this->_querysArr['check']);
            $stm->execute(array(':user' => $user));
            $res = $stm->fetchAll();
            // TODO: HASH!!!
            if (is_array($res) && count($res) == 1 && $res[0][$this->_options['passwordcol']] == md5($pass)) {
                $this->_checkedUserData = array();
                foreach ($res[0] as $key_i => $val_i) {
                    if ( $key_i == $this->_options['usernamecol']
                      || $key_i == $this->_options['passwordcol']) {
                        continue;
                    }
                    $this->_checkedUserData[$key_i] = $val_i;
                }
                return true;
            }
        }
        return false;
    }

    public function getAuthData()
    {
        return (isset($this->_checkedUserData)) ? $this->_checkedUserData : array();
    }

    private function _dbGetSelectFields()
    {
        $selectFieldsArr = array($this->_options['usernamecol'], $this->_options['passwordcol']);

        if ( ! isset($this->_options['db_fields'])) {
            $this->_options['db_fields'] = array();
        } elseif ($this->_options['db_fields'] == "*") {
            return "*";
        } elseif (is_string($this->_options['db_fields'])) {
            $this->_options['db_fields'] = array($this->_options['db_fields']);
        }

        if (isset($this->_options['profilecol'])) {
            $this->_options['db_fields'][] = $this->_options['profilecol'];
        }
        foreach ($this->_options['db_fields'] as $field_i) {
            if (is_array($field_i)) {
                $selectFieldsArr[] = "{$field_i[0]} AS {$field_i[1]}";
            } else {
                $selectFieldsArr[] = $field_i;
            }
        }

        return implode(", ", $selectFieldsArr);
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
