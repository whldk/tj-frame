<?php
namespace vendor\cache;

use vendor\base\LogTrait;

/**
 * reconnect when redis is gone for poor network
 */
class Redis
{
	use LogTrait;
	
	const M_NORMAL = 1;
	const M_PERSIST = 2;
	const MAX_RETRYTIMES = 3;
	
	public static $mode = self::M_NORMAL;
	
	protected $retryTimes = 1;
	protected $retryInterval = 0;
	
	protected static $config = [
			'host' => '127.0.0.1',
			'port' => 6379,
			'timeout' => 0,
			'persistent_id' => null,
			'reserved' => null,
			'retry_interval' => null,
			'require_pass' => null
	];
	protected static $option = [];
	
	protected $redis;
	
	public function __construct($config = [])
	{
		if (isset($config['mode']) && in_array($config['mode'], [self::M_NORMAL, self::M_PERSIST])) {
			self::$mode = $config['mode'];
		}
		if (isset($config['server'])) {
			$config['server'] = array_intersect_key($config['server'], self::$config);
			self::$config = $config['server'] + self::$config;
		}
		if (isset($config['option'])) {
			self::$option = $config['option'];
		}
		$this->redis = new \Redis();
		if ($this->connect() === false) {
			throw new \Exception('Redis connection failed : ' . $this->redis->getLastError());
		}
		
		if (isset($config['retryTimes']) && $config['retryTimes'] > 0) {
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
		
		/* trait construct */
		$this->logTrait($config + ['log_file' => DIR_LOG . DIRECTORY_SEPARATOR . 'redis.log']);
	}
	
	public function connect($persistent_id = null)
	{
		if ($this->redis instanceof \Redis) {
			try {
				if (self::$mode === self::M_PERSIST) {
					$res = $this->redis->pconnect(
							self::$config['host'],
							self::$config['port'],
							self::$config['timeout'],
							$persistent_id === null ? self::$config['persistent_id'] : $persistent_id,
							self::$config['retry_interval']
						);
				} else {
					$res = $this->redis->connect(
							self::$config['host'], 
							self::$config['port'],
							self::$config['timeout'],
							self::$config['reserved'],
							self::$config['retry_interval']
						);
				}
				if ($res === true && isset(self::$config['require_pass'])) {
					$res = $this->redis->auth();
				}
				foreach (self::$option as $param => $value) {
					if ($this->redis->setOption($param, $value) === false) {
						return false;
					}
				}
			} catch (\RedisException $e) {
				return false;
			}
			return $res;
		}
		return false;
	}
	
	public function __call($name, $args)
	{
		$res = null;
		
		$times = $this->retryTimes + 1;
		for ($i = 0; $i < $times; $i++) {
			try {
				$res = call_user_func_array([$this->redis, $name], $args);
				break;
			} catch (\RedisException $e) {
				if ($this->retryInterval) {
					is_array($this->retryInterval) ? sleep(rand($this->retryInterval[0], $this->retryInterval[1])) : sleep($this->retryInterval);
				}
				//reconnect
				$this->connect();
				continue;
			}
		}
		if ($res === null && $this->enable_log) {
			$this->log("Redis connnect {$times} times all failed.", 'error');
		}
		
		return $res;
	}
	
	public function __destruct()
	{
		$this->redis->close();
	}
}

