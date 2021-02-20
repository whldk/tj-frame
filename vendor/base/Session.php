<?php

namespace vendor\base;

class Session
{
    /**
     * @var \SessionHandlerInterface|array an object implementing the SessionHandlerInterface or a configuration array. If set, will be used to provide persistency instead of build-in methods.
     */
    public $handler;

    /**
     * @var array parameter-value pairs to override default session cookie parameters that are used for session_set_cookie_params() function
     * Array may have the following possible keys: 'lifetime', 'path', 'domain', 'secure', 'httponly'
     * @see http://www.php.net/manual/en/function.session-set-cookie-params.php
     */
    private $_cookieParams = ['httponly' => true];

    public function __construct($config = [])
    {
    	if (isset($config['handler'])) {
    		$this->handler = $config['handler'];
    	}
    	foreach ($config as $name => $value) {
    		switch ($name) {
    			case 'name' :
    			case 'savePath' :
    			case 'cookieParams' :
    			case 'useCookies' :
    			case 'gCProbability' :
    			case 'timeout' :
    				$call = 'set' . ucfirst($name);
    				$this->$call($value);
    		}
    	}
        register_shutdown_function([$this, 'close']);
    }
    
    /**
     * Returns a value indicating whether to use custom session storage.
     * This method should be overridden to return true by child classes that implement custom session storage.
     * To implement custom session storage, override these methods: [[openSession()]], [[closeSession()]],
     * [[readSession()]], [[writeSession()]], [[destroySession()]] and [[gcSession()]].
     * @return bool whether to use custom storage.
     */
    public function getUseCustomStorage()
    {
        return false;
    }

    /**
     * Starts the session.
     */
    public function open()
    {
        if ($this->getIsActive()) {
            return;
        }
        
        $this->registerSessionHandler();

        $this->setCookieParamsInternal();
		if (defined('DEBUG') && DEBUG == true) {
        	session_start();
		} else {
			@session_start();
		}
    }

    /**
     * Registers session handler.
     * @throws \yii\base\InvalidConfigException
     */
    protected function registerSessionHandler()
    {
        if ($this->handler !== null) {
            if (!is_object($this->handler)) {
                $this->handler = new $this->handler;
            }
            if (!$this->handler instanceof \SessionHandlerInterface) {
                throw new \Exception('"' . get_class($this) . '::handler" must implement the SessionHandlerInterface.');
            }
            if (defined('DEBUG') && DEBUG == true) {
            	session_set_save_handler($this->handler, false);
            } else {
            	@session_set_save_handler($this->handler, false);
            }
        } elseif ($this->getUseCustomStorage()) {
        	if (defined('DEBUG') && DEBUG == true) {
        		session_set_save_handler(
                    [$this, 'openSession'],
                    [$this, 'closeSession'],
                    [$this, 'readSession'],
                    [$this, 'writeSession'],
                    [$this, 'destroySession'],
                    [$this, 'gcSession']
                );
        	} else {
        		@session_set_save_handler(
                    [$this, 'openSession'],
                    [$this, 'closeSession'],
                    [$this, 'readSession'],
                    [$this, 'writeSession'],
                    [$this, 'destroySession'],
                    [$this, 'gcSession']
                );
        	}
        }
    }

    /**
     * Ends the current session and store session data.
     */
    public function close()
    {
        if ($this->getIsActive()) {
        	if (defined('DEBUG') && DEBUG == true) {
        		session_write_close();
        	} else {
        		@session_write_close();
        	}
        }
    }

    /**
     * Frees all session variables and destroys all data registered to a session.
     */
    public function destroy()
    {
        if ($this->getIsActive()) {
            $sessionId = session_id();
            $this->close();
            $this->setId($sessionId);
            $this->open();
            session_unset();
            session_destroy();
            $this->setId($sessionId);
        }
    }

    /**
     * @return bool whether the session has started
     */
    public function getIsActive()
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    private $_hasSessionId;

    /**
     * Returns a value indicating whether the current request has sent the session ID.
     * The default implementation will check cookie and $_GET using the session name.
     * If you send session ID via other ways, you may need to override this method
     * or call [[setHasSessionId()]] to explicitly set whether the session ID is sent.
     * @return bool whether the current request has sent the session ID.
     */
    public function getHasSessionId()
    {
        if ($this->_hasSessionId === null) {
            $name = $this->getName();
            if (!empty($_COOKIE[$name]) && ini_get('session.use_cookies')) {
                $this->_hasSessionId = true;
            } elseif (!ini_get('session.use_only_cookies') && ini_get('session.use_trans_sid')) {
                $this->_hasSessionId = @$_GET[$name] != '';
            } else {
                $this->_hasSessionId = false;
            }
        }

        return $this->_hasSessionId;
    }

    /**
     * Sets the value indicating whether the current request has sent the session ID.
     * This method is provided so that you can override the default way of determining
     * whether the session ID is sent.
     * @param bool $value whether the current request has sent the session ID.
     */
    public function setHasSessionId($value)
    {
        $this->_hasSessionId = $value;
    }

    /**
     * Gets the session ID.
     * This is a wrapper for [PHP session_id()](http://php.net/manual/en/function.session-id.php).
     * @return string the current session ID
     */
    public function getId()
    {
        return session_id();
    }

    /**
     * Sets the session ID.
     * This is a wrapper for [PHP session_id()](http://php.net/manual/en/function.session-id.php).
     * @param string $value the session ID for the current session
     */
    public function setId($value)
    {
        session_id($value);
    }

    /**
     * Updates the current session ID with a newly generated one .
     * Please refer to <http://php.net/session_regenerate_id> for more details.
     * @param bool $deleteOldSession Whether to delete the old associated session file or not.
     */
    public function regenerateID($deleteOldSession = false)
    {
        if ($this->getIsActive()) {
            // add @ to inhibit possible warning due to race condition
            // https://github.com/yiisoft/yii2/pull/1812
            @session_regenerate_id($deleteOldSession);
        }
    }

    /**
     * Gets the name of the current session.
     * This is a wrapper for [PHP session_name()](http://php.net/manual/en/function.session-name.php).
     * @return string the current session name
     */
    public function getName()
    {
        return session_name();
    }

    /**
     * Sets the name for the current session.
     * This is a wrapper for [PHP session_name()](http://php.net/manual/en/function.session-name.php).
     * @param string $value the session name for the current session, must be an alphanumeric string.
     * It defaults to "PHPSESSID".
     */
    public function setName($value)
    {
        session_name($value);
    }

    /**
     * Gets the current session save path.
     * This is a wrapper for [PHP session_save_path()](http://php.net/manual/en/function.session-save-path.php).
     * @return string the current session save path, defaults to '/tmp'.
     */
    public function getSavePath()
    {
        return session_save_path();
    }

    /**
     * Sets the current session save path.
     * This is a wrapper for [PHP session_save_path()](http://php.net/manual/en/function.session-save-path.php).
     * @param string $value the current session save path. This can be either a directory name or a path alias.
     * @throws InvalidParamException if the path is not a valid directory
     */
    public function setSavePath($path)
    {
        if (is_dir($path)) {
            session_save_path($path);
        } else {
            throw new \Exception("Session save path is not a valid directory: $value");
        }
    }

    /**
     * @return array the session cookie parameters.
     * @see http://php.net/manual/en/function.session-get-cookie-params.php
     */
    public function getCookieParams()
    {
        return array_merge(session_get_cookie_params(), array_change_key_case($this->_cookieParams));
    }

    /**
     * Sets the session cookie parameters.
     * The cookie parameters passed to this method will be merged with the result
     * of `session_get_cookie_params()`.
     * @param array $value cookie parameters, valid keys include: `lifetime`, `path`, `domain`, `secure` and `httponly`.
     * @throws InvalidParamException if the parameters are incomplete.
     * @see http://us2.php.net/manual/en/function.session-set-cookie-params.php
     */
    public function setCookieParams(array $value)
    {
        $this->_cookieParams = $value;
    }

    /**
     * Sets the session cookie parameters.
     * This method is called by [[open()]] when it is about to open the session.
     * @throws InvalidParamException if the parameters are incomplete.
     * @see http://us2.php.net/manual/en/function.session-set-cookie-params.php
     */
    protected function setCookieParamsInternal()
    {
        $data = $this->getCookieParams();
        if (isset($data['lifetime'], $data['path'], $data['domain'], $data['secure'], $data['httponly'])) {
            session_set_cookie_params($data['lifetime'], $data['path'], $data['domain'], $data['secure'], $data['httponly']);
        } else {
            throw new \Exception('Please make sure cookieParams contains these elements: lifetime, path, domain, secure and httponly.');
        }
    }

    /**
     * Returns the value indicating whether cookies should be used to store session IDs.
     * @return bool|null the value indicating whether cookies should be used to store session IDs.
     * @see setUseCookies()
     */
    public function getUseCookies()
    {
        if (ini_get('session.use_cookies') === '0') {
            return false;
        } elseif (ini_get('session.use_only_cookies') === '1') {
            return true;
        } else {
            return null;
        }
    }

    /**
     * Sets the value indicating whether cookies should be used to store session IDs.
     * Three states are possible:
     *
     * - true: cookies and only cookies will be used to store session IDs.
     * - false: cookies will not be used to store session IDs.
     * - null: if possible, cookies will be used to store session IDs; if not, other mechanisms will be used (e.g. GET parameter)
     *
     * @param bool|null $value the value indicating whether cookies should be used to store session IDs.
     */
    public function setUseCookies($value)
    {
        if ($value === false) {
            ini_set('session.use_cookies', '0');
            ini_set('session.use_only_cookies', '0');
        } elseif ($value === true) {
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
        } else {
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '0');
        }
    }

    /**
     * @return float the probability (percentage) that the GC (garbage collection) process is started on every session initialization, defaults to 1 meaning 1% chance.
     */
    public function getGCProbability()
    {
        return (float) (ini_get('session.gc_probability') / ini_get('session.gc_divisor') * 100);
    }

    /**
     * @param float $value the probability (percentage) that the GC (garbage collection) process is started on every session initialization.
     * @throws InvalidParamException if the value is not between 0 and 100.
     */
    public function setGCProbability($value)
    {
        if ($value >= 0 && $value <= 100) {
            // percent * 21474837 / 2147483647 ≈ percent * 0.01
            ini_set('session.gc_probability', floor($value * 21474836.47));
            ini_set('session.gc_divisor', 2147483647);
        } else {
            throw new \Exception('GCProbability must be a value between 0 and 100.');
        }
    }

    /**
     * @return bool whether transparent sid support is enabled or not, defaults to false.
     */
    public function getUseTransparentSessionID()
    {
        return ini_get('session.use_trans_sid') == 1;
    }

    /**
     * @param bool $value whether transparent sid support is enabled or not.
     */
    public function setUseTransparentSessionID($value)
    {
        ini_set('session.use_trans_sid', $value ? '1' : '0');
    }

    /**
     * @return int the number of seconds after which data will be seen as 'garbage' and cleaned up.
     * The default value is 1440 seconds (or the value of "session.gc_maxlifetime" set in php.ini).
     */
    public function getTimeout()
    {
        return (int) ini_get('session.gc_maxlifetime');
    }

    /**
     * @param int $value the number of seconds after which data will be seen as 'garbage' and cleaned up
     */
    public function setTimeout($value)
    {
        ini_set('session.gc_maxlifetime', $value);
    }

    /**
     * Session open handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * Do not call this method directly.
     * @param string $savePath session save path
     * @param string $sessionName session name
     * @return bool whether session is opened successfully
     */
    public function openSession($savePath, $sessionName)
    {
        return true;
    }

    /**
     * Session close handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * Do not call this method directly.
     * @return bool whether session is closed successfully
     */
    public function closeSession()
    {
        return true;
    }

    /**
     * Session read handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * Do not call this method directly.
     * @param string $id session ID
     * @return string the session data
     */
    public function readSession($id)
    {
        return '';
    }

    /**
     * Session write handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * Do not call this method directly.
     * @param string $id session ID
     * @param string $data session data
     * @return bool whether session write is successful
     */
    public function writeSession($id, $data)
    {
        return true;
    }

    /**
     * Session destroy handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * Do not call this method directly.
     * @param string $id session ID
     * @return bool whether session is destroyed successfully
     */
    public function destroySession($id)
    {
        return true;
    }

    /**
     * Session GC (garbage collection) handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * Do not call this method directly.
     * @param int $maxLifetime the number of seconds after which data will be seen as 'garbage' and cleaned up.
     * @return bool whether session is GCed successfully
     */
    public function gcSession($maxLifetime)
    {
        return true;
    }

    /**
     * Returns the session variable value with the session variable name.
     * If the session variable does not exist, the `$defaultValue` will be returned.
     * @param string $key the session variable name
     * @param mixed $defaultValue the default value to be returned when the session variable does not exist.
     * @return mixed the session variable value, or $defaultValue if the session variable does not exist.
     */
    public function get($key, $defaultValue = null)
    {
        $this->open();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $defaultValue;
    }

    /**
     * Adds a session variable.
     * If the specified name already exists, the old value will be overwritten.
     * @param string $key session variable name
     * @param mixed $value session variable value
     */
    public function set($key, $value)
    {
        $this->open();
        $_SESSION[$key] = $value;
    }

    /**
     * Removes a session variable.
     * @param string $key the name of the session variable to be removed
     * @return mixed the removed value, null if no such session variable.
     */
    public function remove($key)
    {
        $this->open();
        if (isset($_SESSION[$key])) {
            $value = $_SESSION[$key];
            unset($_SESSION[$key]);

            return $value;
        } else {
            return null;
        }
    }

    /**
     * Removes all session variables
     */
    public function removeAll()
    {
        $this->open();
        foreach (array_keys($_SESSION) as $key) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * @param mixed $key session variable name
     * @return bool whether there is the named session variable
     */
    public function has($key)
    {
        $this->open();
        return isset($_SESSION[$key]);
    }
}
