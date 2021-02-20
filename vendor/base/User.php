<?php
namespace vendor\base;

use vendor\exceptions\InvalidConfigException;

class User
{
	use AppTrait;
	
	/**
	 * session params
	 */
	public $idParam = '__id';
	public $authTimeoutParam = '__expire';
	
	public $authTimeout = 7200;	//30 * 60
	
	/**
	 * cookie related params
	 */
	public $enableAutoLogin = false;
	public $identityCookie = 'i'.APP_NAME;
	public $cookieLifetime = 2592000;	//30 * 24 * 60 * 60
	
	public $identityClass = null;
	
	public $enableExtraCookies = false;
	
	/**
	 * @var IdentityInterface
	 */
	protected $identity = false;
	
	public function __construct($config = [])
	{
		if (!isset($config['identityClass'])) {
			throw new InvalidConfigException('IdentityClass must be set');
		}
		$this->identityClass = $config['identityClass'];
		
		foreach ($config as $property => $value) {
			switch ($property) {
				case 'enableAutoLogin' :
				case 'enableExtraCookies' :
					$this->$property = boolval($value);
					break;
				case 'authTimeout' :
				case 'cookieLifetime' : 
					$this->$property = (int)$value;
					break;
				case 'identityCookie' :
				case 'idParam' :
				case 'authTimeoutParam' : 
					$this->$property = (string)$value;
					break;
			}
		}
		
		if ($this->enableAutoLogin && !$this->identityCookie) {
			throw new InvalidConfigException('IdentityCookie must be set when enableAutoLogin is TRUE');
		}
	}

	public function getRole()
	{
		if (!$this->getIdentity()) {
			return '?';
		} else {
			return $this->identity->getRole();
		}
	}
	
	public function isGuest()
	{
		return !$this->getIdentity();
	}
	
	public function getIdentity()
	{
		if ($this->identity === false) {
			$this->refreshAuth();
		}
		return $this->identity;
	}
	
	public function setIdentity($identity)
	{
		if ($identity instanceof IdentityInterface) {
			$this->identity = $identity;
		} else {
			$this->identity = null;
		}
	}
	
	public function login(IdentityInterface $identity, $remember = false) :bool
	{
		$this->setIdentity($identity);
		if (!$this->identity) {
			return false;
		}
		
		$this->clearAuth(false);
		$this->auth($identity, $remember);
		
		return !$this->isGuest();
	}
	

	public function logout($destroySession = true)
	{
		$this->setIdentity(null);
		
		$this->clearAuth($destroySession);
	
		return $this->isGuest();
	}
	
	protected function auth($identity, $remember)
	{
		$session = $this->getSession();
		if ($session->getIsActive()) {
			$session->set($this->idParam, $identity->getId());
			$this->authTimeout === null ?: $session->set($this->authTimeoutParam, time() + $this->authTimeout);
		}
		
		if ($this->enableAutoLogin && $remember) {
			$this->sendIdentityCookie($identity);
		}
		
		if ($this->enableExtraCookies) {
			$this->setExtraCookies();
		}
	}
	
	protected function clearAuth($destroySession = false)
	{
		$session = $this->getSession();
		if ($destroySession) {
			$session->destroy();
		} else {
			$session->regenerateID(true);
			$session->remove($this->idParam);
			$session->remove($this->authTimeoutParam);
		}
		
		if ($this->enableAutoLogin) {
			$this->removeIdentityCookie();
		}
	}
	
	public function refreshAuth()
	{
		$session = $this->getSession();
	
		if ($session->getIsActive()) {
			$expire = $session->get($this->authTimeoutParam);
			if ($expire !== null && $expire < time()) {
				$this->logout(false);
			} else {
				$id = $session->get($this->idParam);
				$identityClass = $this->identityClass;
				if ($id && ($identity = $identityClass::findById($id))) {
					$this->setIdentity($identity);
					if ($this->identity && $this->authTimeout !== null) {
						$session->set($this->authTimeoutParam, time() + $this->authTimeout);
					}
				} else {
					$this->setIdentity(null);
				}
			}
		}
	
		if ($this->enableAutoLogin) {
			$this->refreshAuthByCookie();
		}
		
		if ($this->enableExtraCookies) {
			$this->renewExtraCookies();
		}
	}
	
	public function refreshAuthByCookie()
	{
		$cookie = $this->request->getCookies()->get($this->identityCookie);
		if (!$cookie) {
			return;
		}
	
		if (($data = json_decode($cookie['value'], true)) && count($data) === 3) {
			if ($this->isGuest()) {
				list($id, $authKey) = $data;
				$identityClass = $this->identityClass;
				if ($id && ($identity = $identityClass::findById($id)) && $identity->validateAuthKey($authKey)) {
					$this->login($identity, true);
				}
			} else {
				//auto renew
				$this->renewIdentityCookie($cookie);
			}
		} else {
			$this->removeIdentityCookie();
		}
	}
	
	protected function renewIdentityCookie($cookie)
	{
		$cookie['expire'] = time() + $this->cookieLifetime;
		$this->response->getCookies()->set($cookie);
	}
	
	protected function removeIdentityCookie()
	{
		$this->response->getCookies()->del($this->identityCookie);
	}
	
  	protected function sendIdentityCookie($identity)
    {
    	$identityCookie = [
    			'name' => $this->identityCookie,
    			'expire' => time() + $this->cookieLifetime,
    			'value' => json_encode([
    					$identity->getId(),
    					$identity->getAuthkey(),
    					$this->cookieLifetime
    			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    	];
    	$this->response->getCookies()->set($identityCookie);
    }
	
	/**
	 * @return \vendor\base\Session
	 */
	public function getSession()
	{
		$session = $this->session;
		$session->open();
		return $session;
	}

	protected function setExtraCookies()
	{
		$identity = $this->getIdentity();
		if (!$identity) {
			return;	
		}
		$identity->setExtraCookies();
	}
	
	protected function delExtraCookies()
	{
		$identity = $this->getIdentity();
		if (!$identity) {
			return;
		}
		$identity->delExtraCookies();
	}
	
	protected function renewExtraCookies()
	{
		$identity = $this->getIdentity();
		if (!$identity) {
			return;
		}
		$identity->renewExtraCookies();
	}
}