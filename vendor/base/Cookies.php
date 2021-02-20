<?php
namespace vendor\base;

class Cookies 
{
	private $cookie = [
			'name' => null,
			'value' => '',
			'domain' => '',
			'expire' => 0,
			'path' => '/',
			'secure' => false,
			'httpOnly' => true
	];
	private $cookies = [];
	
	public function getAll()
	{
		return $this->cookies;
	}
	
	public function load($enableValidation = false, $validationKey = '', $validateCookies = [])
	{
		if ($this->cookies) {
			return ;
		}
		
		if ($enableValidation) {
			if (!$validationKey) {
				throw new \Exception('cookieValidationKey must be configured with a secret key.');
			}
			$hashCookies = array_intersect_key($_COOKIE, array_fill_keys($validateCookies, null));
			$cookies = array_diff_key($_COOKIE, $hashCookies);
		} else {
			$hashCookies = [];
			$cookies = $_COOKIE;
		}
		
		foreach ($hashCookies as $name => $value) {
			if (!is_string($value)) {
				continue;
			}
			$data = \App::getInstance()->security->validateData($value, $validationKey);
			if ($data === false) {
				continue;
			}
			$data = @unserialize($data);
			if (is_array($data) && isset($data[0], $data[1]) && $data[0] === $name) {
				$this->cookies[$name] = [
						'name' => $name,
						'value' => $data[1],
						'expire' => null,
				];
			}
		}
		foreach ($cookies as $name => $value) {
			$this->cookies[$name] = [
					'name' => $name,
					'value' => $value,
					'expire' => null,
			];
		}
	}
	
	public function send($enableValidation = false, $validationKey = '', $validateCookies = [])
	{
		if ($enableValidation) {
			if (!$validationKey) {
				throw new \Exception('cookieValidationKey must be configured with a secret key.');
			}
		}
		
		foreach ($this->cookies as $cookie) {
			if ($cookie['expire'] != 1  && $enableValidation && in_array($cookie['name'], $validateCookies)) {
				$cookie['value'] = \App::getInstance()->security->hashData(serialize([$cookie['name'], $cookie['value']]), $validationKey);
			}
			setcookie($cookie['name'], $cookie['value'], $cookie['expire'], $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httpOnly']);
		}
	}
	
	public function set($cookie)
	{
		if (!isset($cookie['name']) || !$cookie['name']) {
			return false;
		}
		$cookie = array_intersect_key($cookie, $this->cookie) + $this->cookie;
		$this->cookies[$cookie['name']] = $cookie;
	}
	
	public function del($name, $expire = true)
	{
		if ($expire) {
			$this->set(['name' => $name, 'expire' => 1, 'value' => '']);
		} else {
			unset($this->cookies[$name]);
		}
	}
	
	public function get($name)
	{
		return isset($this->cookies[$name]) ? $this->cookies[$name] : null;
	}
}