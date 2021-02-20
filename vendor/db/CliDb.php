<?php
namespace vendor\db;

use PDO;

/**
 * db类，基于PDO
 * 注意：
 * 表名、字段名都会进行小写转换
 * 
 * @author Administrator
 */
class CliDb extends BaseDb
{
	use PdoTrait {
		PdoTrait::result as private;
		PdoTrait::get_last_insert_id as private;
		PdoTrait::inTransaction as private;
		PdoTrait::begin_transaction as private;
		PdoTrait::commit as private;
		PdoTrait::rollback as private;
		PdoTrait::query as private;
		PdoTrait::execute as private;
	}
	
	const MAX_RETRYTIMES = 3;
	
	protected $config = [
			'dsn' => null,
			'username' => null,
			'passwd' => null,
			'options' => [
					PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'', 
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
					PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => 1
			]
	];
	
	protected $retryTimes = 1;
	protected $retryInterval = 0;
	
	/**
	 * 注意设置utf8
	 * @param string $dsn 例如 mysql:host=localhost;dbname=DB;charset=UTF8
	 * @param string $username
	 * @param string $passwd
	 * @param array $options
	 */
	public function __construct($config = [])
	{
		parent::__construct($config);
		
		if (isset($config['retryTimes']) && $config['retryTimes'] >= 0) {
			$config['retryTimes'] = (int)$config['retryTimes'];
			$this->retryTimes = $config['retryTimes'] > self::MAX_RETRYTIMES ? self::MAX_RETRYTIMES : $config['retryTimes'];
		}
		
		if (isset($config['retryInterval'])) {
			if (is_int($config['retryInterval'])) {
				$this->retryInterval = $config['retryInterval'];
			} elseif (is_array($config['retryInterval'])
					&& isset($config['retryInterval'][0], $config['retryInterval'][1])
					&& $config['retryInterval'][0] < $config['retryInterval'][1]) {
				$this->retryInterval = [(int)$config['retryInterval'][0], (int)$config['retryInterval'][1]];
			}
		}
	}
	
	public function __call($name , $args)
	{
		static $funcs = ['result', 'get_last_insert_id', 'inTransaction', 'begin_transaction', 'commit', 'rollback', 'query', 'execute'];
		
		$res = false;
		if (!in_array($name, $funcs, true)) {
			throw new \Exception('CliDb: Unknown Method');
		}
		
		$times = $this->retryTimes + 1;
		for ($i = 0; $i < $times; $i++) {
			try {
				$res = call_user_func_array([$this, $name], $args);
				break;
			} catch (\PDOException $e) {
				if ($this->enable_log) {
					$this->log('PDO error : ' . $e->getMessage(), 'error', $e->getCode(), implode(';', $e->errorInfo));
					$last_sql = $this->get_last_sql();
					$this->log($last_sql, 'sql');
				}
				if (in_array($e->errorInfo[1], [2006, 2013])) {
					if ($this->retryInterval) {
						is_array($this->retryInterval) ? sleep(rand($this->retryInterval[0], $this->retryInterval[1])) : sleep($this->retryInterval);
					}
					$this->connect();
					continue;
				} else {
					switch ($name) {
						case 'result' :
						case 'get_last_insert_id' :
							return null;
						case 'begin_transaction' :
						case 'commit' :
						case 'rollback' :
							throw $e;
						case 'query' :
						case 'execute' :
						default :
                            throw $e;
							//return false; 直接返回抑制异常
					}
					break;
				}
			}
		}
		return $res;
	}
}