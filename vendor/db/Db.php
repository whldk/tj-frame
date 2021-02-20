<?php
namespace vendor\db;

/**
 * db类，基于PDO
 * 注意：
 * 表名、字段名都会进行小写转换
 * 
 * @author Administrator
 */
class Db extends BaseDb
{
	use PdoTrait;
	
	public function __construct($config = [])
	{
		parent::__construct($config);
		
		if (isset($config['defaultCallbackOnError']) && is_callable($config['defaultCallbackOnError'])) {
			$this->defaultCallbackOnError = [$config['defaultCallbackOnError'], []];
		}
	}
}