<?php
namespace vendor\db;

/**
 * 只支持Db，不支持CliDb
 */
class MultiDb
{
	protected $db = [];
	
	public function __construct($config = [])
	{
		if (isset($config['db'])) {
			$this->db = $config['db'];
		}
	}
	
	public function db($name)
	{
		if (!isset($this->db[$name])) {
			return null;
		}
		if (!$this->db[$name] instanceof BaseDb) {
			$this->db[$name] = new Db($this->db[$name]);
		}
		
		return $this->db[$name];	
	}
}