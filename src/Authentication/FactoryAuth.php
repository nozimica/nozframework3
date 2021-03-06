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
define('AUTH_STATUS_SECURITY_BREACH',            -5);
define('AUTH_STATUS_SECURITY_BREACH_REMOTEADDR', -51);
define('AUTH_STATUS_SECURITY_BREACH_FORWARDED',  -52);
define('AUTH_STATUS_SECURITY_BREACH_USERAGENT',  -53);
define('AUTH_STATUS_SECURITY_BREACH_CHALLENGE',  -54);
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
 * FactoryAuth
 */
class FactoryAuth {
    public static function CreateAuth($__authOpt, $modelObj) {
        $auth = new OzAuthManager($__authOpt, $modelObj);
        return $auth;
    }
}

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
     * @see OzAuthStorage
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
      * Assoc array with config values for auth, from user.
      *
      * @var array
      */
    private $_authOpts = null;

    /**
      * Model object
      *
      * @var object
      */
    private $_modelObj = null;

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

    /**
     * Parameters to be used when setting cookies.
     *
     * @var string
     */
    private $_cookieParams, $_usingCookies;

    // }}}

    /**
     * Constructor
     *
     * Sets up the Auth manager and its storage driver.
     *
     * @param  mixed  driverOpts    Additional options for the storage driver.
     * @param  object modelObj      Model object from user.
     * @return void
     */
    public function __construct($driverOpts, $modelObj)
    {
        $this->_status = AUTH_STATUS_OK;
        $this->_sanitizeInput($driverOpts);

        $this->_sessionName  = $driverOpts['sessionName'];
        $this->_postUsername = $driverOpts['postUsername'];
        $this->_postPassword = $driverOpts['postPassword'];
        $this->_setLogLevel($driverOpts['authLogLevel']);
        $this->_cookieParams = array('lifetime'  => 0
                                    , 'path'     => rtrim(dirname($_SERVER['PHP_SELF']), '/\\')
                                    , 'domain'   => $_SERVER['SERVER_NAME']
                                    , 'secure'   => false
                                    , 'httponly' => true);
        $this->_usingCookies = ini_get("session.use_cookies");

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
        $this->_authOpts = $driverOpts;
        $this->_modelObj = $modelObj;

    }

    /**
     * Ensures that the two input arrays have all the keys needed, and those undefined
     * by the caller get here their DEFAULT values.
     *
     * This method is able to use both the old and new definition of auth db data.
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
        // Check alternative postFields definition
        if (isset($driverOpts['postFields']) && strpos($driverOpts['postFields'], ':') !== false) {
            $postFields = explode(':', $driverOpts['postFields']);
            $driverOptsNew['postUsername'] = trim($postFields[0]);
            $driverOptsNew['postPassword'] = trim($postFields[1]);
        }
        // Check alternative authFields definition
        if (isset($driverOpts['authFields'])) {
            preg_match('/([a-zA-Z]+)\(([_a-zA-Z]+),([_a-zA-Z]+)(?:,([,_a-zA-Z]*)|)\)/', $driverOpts['authFields'], $matches);
            if (count($matches) >= 4) {
                $driverOptsNew['table'] = trim($matches[1]);
                $driverOptsNew['usernamecol'] = trim($matches[2]);
                $driverOptsNew['passwordcol'] = trim($matches[3]);
                if (isset($matches[4])) {
                    $driverOptsNew['db_fields'] = explode(',', trim($matches[4]));
                }
            }
        }
        $driverOpts = $driverOptsNew;
    }

    /** Main methods */
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

            session_name($this->_sessionName);
            if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
                session_set_cookie_params($this->_cookieParams['lifetime'], $this->_cookieParams['path']
                                        , $this->_cookieParams['domain'], $this->_cookieParams['secure']
                                        , $this->_cookieParams['httponly']);
            } else {
                session_set_cookie_params($this->_cookieParams['lifetime'], $this->_cookieParams['path']
                                        , $this->_cookieParams['domain'], $this->_cookieParams['secure']);
            }
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
            $this->_storageObj = new OzAuthStorage($this->_modelObj, $this->_authOpts);
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
    }

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

            if ( isset($this->_session['username'])
              && trim($this->_session['username']) != '') {

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
                    $this->_status = AUTH_STATUS_SECURITY_BREACH_REMOTEADDR;
                    $this->logout();
                    return false;
                }

                // Check if the IP of the user connecting via proxy has
                // changed, if so we assume a man in the middle attack and log him out.
                if ( isset($_SERVER['HTTP_X_FORWARDED_FOR'])
                  && $this->_session['sessionforwardedfor'] != $_SERVER['HTTP_X_FORWARDED_FOR']) {
                    $this->log('Security Breach. Forwarded For IP Address changed.', AUTH_LOG_INFO);
                    $this->_status = AUTH_STATUS_SECURITY_BREACH_FORWARDED;
                    $this->logout();
                    return false;
                }

                // Check if the User-Agent of the user has changed, if
                // so we assume a man in the middle attack and log him out
                if ( isset($_SERVER['HTTP_USER_AGENT'])
                  && $this->_session['sessionuseragent'] != $_SERVER['HTTP_USER_AGENT']) {
                    $this->log('Security Breach. User Agent changed.', AUTH_LOG_INFO);
                    $this->_status = AUTH_STATUS_SECURITY_BREACH_USERAGENT;
                    $this->logout();
                    return false;
                }

                // Check challenge cookie here, if challengecookieold is not set
                // this is the first time and check is skipped
                if ( isset($this->_session['challengecookieold'])
                  && $this->_session['challengecookieold'] != $_COOKIE['authchallenge']) {
                    $this->log('Security Breach. Challenge Cookie mismatch.', AUTH_LOG_INFO);
                    $this->_status = AUTH_STATUS_SECURITY_BREACH_CHALLENGE;
                    $this->logout();
                    $this->tryLogin();
                    return false;
                }

                $this->log('Session OK.', AUTH_LOG_INFO);
                return true;
            }
        } else {
            $this->log('checkAuth(): no reference to session has been found.', AUTH_LOG_DEBUG);
        }
        $this->log('Unable to locate session storage.', AUTH_LOG_DEBUG);
        $this->log('No login session.', AUTH_LOG_INFO);
        return false;
    }

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
                //$this->_session['challengekey'] = md5($this->username.$this->password);
                $login_ok = true;
                $this->log('Successful login.', AUTH_LOG_INFO);
                $this->setAuth($this->username);
            } else {
                $this->log('Incorrect login.', AUTH_LOG_INFO);
                $this->_status = AUTH_STATUS_WRONG_LOGIN;
            }
        } else {
            // record this status only if no other status has been recorded before
            if ($this->_status == AUTH_STATUS_OK) {
                $this->log('Empty username.', AUTH_LOG_INFO);
                $this->_status = AUTH_STATUS_EMPTY_LOGIN;
            }
        }

        if ($login_ok) {
            return $this->checkAuth();
        }
        return false;
    }

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

        $_SESSION = array();
        $this->_setCookie(session_name(), null);
        $this->_setCookie('authchallenge', null);
        session_destroy();

    }

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
    }

    /** Getters and setters */
    /**
     * Register variable in a session telling that the user
     * has logged in successfully
     *
     * @param  string Username
     * @return void
     * @access public
     * @see tryLogin()
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
        /*$this->_session['sessionreferer'] = isset($_SERVER['HTTP_REFERER'])
            ? $_SERVER['HTTP_REFERER']
            : '';*/

        // This should be set by the container to something more safe
        // Like md5(passwd.microtime)
        /*if (empty($this->_session['challengekey'])) {
            $this->_session['challengekey'] = md5($username.microtime());
        }*/

        $this->_session['username']   = $username;
        $this->_session['loginTimestamp'] = time();
        $this->_session['lastTimestamp']  = time();

        $this->_updateChallengeCookies();
    }

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
    }

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
    }

    /**
     * Returns the user profile (if defined)
     *
     * @return string
     * @access public
     */
    public function getProfile()
    {
        return $this->getAuthData($this->profileFieldName);
    }

    /**
     * Returns the status of the current session.
     *
     * @return integer
     * @access public
     */
    public function getStatus()
    {
        return $this->_status;
    }

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
    }

    /** Misc */
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
    }

    /**
     * Updates challengecookies.
     *
     * @return void
     * @access private
     */
    private function _updateChallengeCookies()
    {
        if (isset($this->_session['challengecookie'])) {
            $this->_session['challengecookieold'] = $this->_session['challengecookie'];
        }
        $this->_session['challengecookie'] = md5($this->_session['username'].microtime());
        $this->_setCookie('authchallenge', $this->_session['challengecookie']);
    }

    /**
     * Creates or updates a cookie.
     *
     * @param string    Cookie name.
     * @param mixed     Cookie value. If null, then remove cookie.
     * @return void
     * @access private
     */
    private function _setCookie($cName, $cValue)
    {
        if ($this->_usingCookies) {
            $expiry = ( ! is_null($cValue)) ? $this->_cookieParams['lifetime'] : (time() - 42000);
            if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
                setcookie($cName, $cValue, $expiry
                  , $this->_cookieParams['path']
                  , $this->_cookieParams['domain']
                  , $this->_cookieParams['secure']
                  , $this->_cookieParams['httponly']);
            } else {
                setcookie($cName, $cValue, $expiry
                  , $this->_cookieParams['path']
                  , $this->_cookieParams['domain']
                  , $this->_cookieParams['secure']);
            }
        }
    }
}

class OzAuthStorage {
    // {{{ properties
    private $_options;
    private $_dbObj = NULL;
    private $_querysArr;
    private $_checkedUserData = array();
    // }}}

    /**
     * Constructor
     *
     * Sets up the Auth manager and its storage driver.
     *
     * @param mixed     Additional options for the storage driver.
     * @param object    Model object from user.
     * @return void
     */
    public function __construct($modelObj, $authOpts)
    {
        $this->_options = $authOpts;
        $this->_dbObj = $modelObj;
        $selectFields = $this->_dbGetSelectFields();
        $this->_querysArr = array('check' => "SELECT $selectFields
                                              FROM {$this->_options['table']}
                                              WHERE {$this->_options['usernamecol']} = :user");
    }

    /**
     * @return boolean
     * @access public
     */
    public function checkCredentials($user, $pass)
    {
        $res = $this->_dbObj->fetchAll($this->_querysArr['check'], array(':user' => $user));
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
        return false;
    }

    /**
     * @return array
     * @access public
     */
    public function getAuthData()
    {
        return (isset($this->_checkedUserData)) ? $this->_checkedUserData : array();
    }

    /**
     * @return string
     * @access private
     */
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

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
