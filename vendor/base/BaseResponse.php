<?php
namespace vendor\base;

abstract class BaseResponse
{
	const FORMAT_JSON = 'json';
	
	public $format = self::FORMAT_JSON;
	
	public $data;
	
	public $content;
	
	public static $statuses = [
			200 => 'OK',
			201 => 'Created',
			204 => 'No Content',
			400 => 'Bad Request',
			403 => 'Forbidden',
			404 => 'Not Found',
	];
	
	protected $_statusCode = 200;
	
	public $statusText = 'OK';
	
	public $encodeOptions = 320;

	public $exitStatus = 0;
	
	public function __construct($config = []){}
	
	public function getStatusCode()
	{
		return $this->_statusCode;
	}
	
	public function setStatusCode($value, $text = null)
	{
		if ($value === null) {
			$value = 200;
		}
		$this->_statusCode = (int) $value;
		if ($text === null) {
			$this->statusText = isset(static::$statuses[$this->_statusCode]) ? static::$statuses[$this->_statusCode] : '';
		} else {
			$this->statusText = $text;
		}
	}
	
	abstract public function send();
	
	abstract protected function prepare();
	
	abstract protected function sendContent();
	
	abstract public function clear();
}