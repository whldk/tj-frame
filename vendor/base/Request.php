<?php
namespace vendor\base;

class Request extends BaseRequest
{
	protected $_url;
	protected $_path;
	protected $_pathinfo;
	protected $_headers;
	protected $_scriptUrl;
	protected $_baseUrl;
	protected $_scriptFile;
	protected $_bodyParams;
	protected $_queryParams;
	protected $_rawBody;
	
	public $enableCookieValidation = true;
	public $cookieValidationKey;
	public $validateCookies = [];
	
	private $_cookies;
	
	public function __construct($config = [])
	{
		if (isset($config['enableCookieValidation'])) {
			$this->enableCookieValidation = boolval($config['enableCookieValidation']);
		}
		if (isset($config['cookieValidationKey'])) {
			$this->cookieValidationKey = strval($config['cookieValidationKey']);
		}
		if (isset($config['validateCookies'])) {
			$this->validateCookies = (array)$config['validateCookies'];
		}
	}
	
	/**
	 * Returns the user IP address.
	 * @return string|null user IP address, null if not available
	 */
	public function getUserIP()
	{
// 		return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

		if ($ip = getenv("HTTP_CLIENT_IP")) {
			return $ip;
		} elseif ($ip = getenv("HTTP_X_FORWARDED_FOR")) {
			return $ip;
		} elseif ($ip = getenv("REMOTE_ADDR")) {
			return $ip;
		} else {
			return null;
		}
	}
	
	public function getCookies()
	{
		if (!$this->_cookies instanceof Cookies) {
			$this->_cookies = new Cookies();
			$this->_cookies->load($this->enableCookieValidation, $this->cookieValidationKey, $this->validateCookies);
		}
		return $this->_cookies;
	}

	public function getUrl()
	{
		if ($this->_url === null) {
			$this->_url = $this->resolveRequestUri();
		}
	
		return $this->_url;
	}
	
	public function getPath()
	{
		if (!$this->_path) {
			$path = $this->getUrl();
			if (($pos = strpos($path, '?')) !== false) {
				$path = substr($path, 0, $pos);
			}
			$this->_path = ltrim(urldecode($path), '/');
		}
		
        return $this->_path;
	}
	
	/**
	 * Returns the URL referrer.
	 * @return string|null URL referrer, null if not available
	 */
	public function getReferrer()
	{
		return $this->getHeaders('Referer');
	}
	
	protected function resolveRequestUri()
	{
		if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // IIS
			$requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
		} elseif (isset($_SERVER['REQUEST_URI'])) {
			$requestUri = $_SERVER['REQUEST_URI'];
			if ($requestUri !== '' && $requestUri[0] !== '/') {
				$requestUri = preg_replace('/^(http|https):\/\/[^\/]+/i', '', $requestUri);
			}
		} elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0 CGI
			$requestUri = $_SERVER['ORIG_PATH_INFO'];
			if (!empty($_SERVER['QUERY_STRING'])) {
				$requestUri .= '?' . $_SERVER['QUERY_STRING'];
			}
		} else {
			throw new \Exception('Unable to determine the request URI.');
		}
	
		return $requestUri;
	}
	
	/**
	 * Returns the header collection.
	 * The header collection contains incoming HTTP headers.
	 * @return array the header collection
	 */
	public function getHeaders($name = null)
	{
		if ($this->_headers === null) {
			if (function_exists('getallheaders')) {
				$this->_headers = getallheaders();
			} else {
				foreach ($_SERVER as $key => $value) {
					if (strncmp($key, 'HTTP_', 5) === 0) {
						$key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
						$this->_headers[$key] = $value;
					}
				}
			}
		}
	
		if ($name !== null) {
			$name = str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', $name))));
			return isset($this->_headers[$name]) ? $this->_headers[$name] : null;
		}
		
		return $this->_headers;
	}
	
	public function getPathinfo()
	{
		if ($this->_pathinfo === null) {
			$this->_pathinfo = $this->resolvePathInfo();
		}
		return $this->_pathinfo;
	}
	
 	protected function resolvePathInfo()
    {
        $pathInfo = $this->getUrl();

        if (($pos = strpos($pathInfo, '?')) !== false) {
            $pathInfo = substr($pathInfo, 0, $pos);
        }

        $pathInfo = urldecode($pathInfo);

        // try to encode in UTF8 if not so
        // http://w3.org/International/questions/qa-forms-utf-8.html
        if (!preg_match('%^(?:
            [\x09\x0A\x0D\x20-\x7E]              # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
            )*$%xs', $pathInfo)
        ) {
            $pathInfo = utf8_encode($pathInfo);
        }

        $scriptUrl = $this->getScriptUrl();
        $baseUrl = $this->getBaseUrl();
        if (strpos($pathInfo, $scriptUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($scriptUrl));
        } elseif ($baseUrl === '' || strpos($pathInfo, $baseUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($baseUrl));
        } elseif (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], $scriptUrl) === 0) {
            $pathInfo = substr($_SERVER['PHP_SELF'], strlen($scriptUrl));
        } else {
            throw new \Exception('Unable to determine the path info of the current request.');
        }

        if (substr($pathInfo, 0, 1) === '/') {
            $pathInfo = substr($pathInfo, 1);
        }

        return (string) $pathInfo;
    }
    
    public function getScriptUrl()
    {
    	if ($this->_scriptUrl === null) {
    		$scriptFile = $this->getScriptFile();
    		$scriptName = basename($scriptFile);
    		if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $scriptName) {
    			$this->_scriptUrl = $_SERVER['SCRIPT_NAME'];
    		} elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $scriptName) {
    			$this->_scriptUrl = $_SERVER['PHP_SELF'];
    		} elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $scriptName) {
    			$this->_scriptUrl = $_SERVER['ORIG_SCRIPT_NAME'];
    		} elseif (isset($_SERVER['PHP_SELF']) && ($pos = strpos($_SERVER['PHP_SELF'], '/' . $scriptName)) !== false) {
    			$this->_scriptUrl = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $scriptName;
    		} elseif (!empty($_SERVER['DOCUMENT_ROOT']) && strpos($scriptFile, $_SERVER['DOCUMENT_ROOT']) === 0) {
    			$this->_scriptUrl = str_replace('\\', '/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $scriptFile));
    		} else {
    			throw new \Exception('Unable to determine the entry script URL.');
    		}
    	}
    
    	return $this->_scriptUrl;
    }
    
    public function getScriptFile()
    {
    	if ($this->_scriptFile) {
    		return $this->_scriptFile;
    	} elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
    		return $_SERVER['SCRIPT_FILENAME'];
    	} else {
    		return '';
    	}
    }
    
    public function getBaseUrl()
    {
    	if ($this->_baseUrl === null) {
    		$this->_baseUrl = rtrim(dirname($this->getScriptUrl()), '\\/');
    	}
    
    	return $this->_baseUrl;
    }
    
    public function getQueryString()
    {
    	return isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    }
    
    public function getQueryParams()
    {
    	if ($this->_queryParams === null) {
    		return $_GET;
    	}
    
    	return $this->_queryParams;
    }
    
    public function getBodyParams()
    {
    	if ($this->_bodyParams === null) {
    		$rawContentType = $this->getContentType();
    		if (($pos = strpos($rawContentType, ';')) !== false) {
    			// e.g. application/json; charset=UTF-8
    			$contentType = substr($rawContentType, 0, $pos);
    		} else {
    			$contentType = $rawContentType;
    		}
    
    		if ($contentType == 'application/json') {
    			$decode = json_decode($this->getRawBody(), true);
    			$this->_bodyParams = $decode ? $decode : [];
    		} elseif ($this->getMethod() === 'POST') {
    			// PHP has already parsed the body so we have all params in $_POST
    			$this->_bodyParams = $_POST;
    		} else {
    			$this->_bodyParams = [];
    			mb_parse_str($this->getRawBody(), $this->_bodyParams);
    			$this->_bodyParams ?: $this->_bodyParams = [];
    		}
    	}
    
    	return $this->_bodyParams;
    }
    
    public function getMethod()
    {
    	if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    		return strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    	}
    
    	if (isset($_SERVER['REQUEST_METHOD'])) {
    		return strtoupper($_SERVER['REQUEST_METHOD']);
    	}
    
    	return 'GET';
    }
    
    public function getContentType()
    {
    	if (isset($_SERVER['CONTENT_TYPE'])) {
    		return $_SERVER['CONTENT_TYPE'];
    	} elseif (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
    		//fix bug https://bugs.php.net/bug.php?id=66606
    		return $_SERVER['HTTP_CONTENT_TYPE'];
    	}
    
    	return null;
    }
    
    public function getRawBody()
    {
    	if ($this->_rawBody === null) {
    		$this->_rawBody = file_get_contents('php://input');
    	}
    
    	return $this->_rawBody;
    }
}