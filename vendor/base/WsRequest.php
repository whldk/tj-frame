<?php
namespace vendor\base;

class WsRequest extends BaseRequest
{
	public function __construct($config = [])
	{
		$this->_pathinfo = isset($config['route']) ? $config['route'] : '';
		$this->_bodyParams = isset($config['params']) ? $config['params'] : [];
		$_GET = isset($config['_get']) ? $config['_get'] : [];
	}
	
	public function getPathinfo()
	{
		return $this->_pathinfo;
	}
	
	public function getBodyParams()
	{
		if (is_string($this->_bodyParams)) {
			$decode = json_decode($this->_bodyParams, true);
			$this->_bodyParams = $decode ? $decode : [];
		}
		return $this->_bodyParams;
	}
}