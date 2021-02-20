<?php

namespace vendor\base;

class ConsoleRequest extends BaseRequest
{
	public function __construct($config = [])
	{
		if (isset($_SERVER['argv'][1])) {
			$this->_pathinfo = $_SERVER['argv'][1];
			if (strpos($this->_pathinfo, 'scripts/') !== 0) {
				$this->_pathinfo = 'scripts/' . $this->_pathinfo;
			}
		}
		if (isset($_SERVER['argv'][2])) {
			$argv = $_SERVER['argv'];
			unset($argv[0], $argv[1]);
			$this->_bodyParams = array_values($argv) ?: [];
		}
	}
	
	public function getPathinfo()
	{
		return $this->_pathinfo;
	}
	
	public function getBodyParams()
	{
		return $this->_bodyParams;
	}
}

