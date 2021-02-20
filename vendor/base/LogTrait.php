<?php
namespace vendor\base;

use vendor\base\Logger;

trait LogTrait
{
	/**
	 * @var Logger
	 */
	protected $logger;
	
	protected $log_config;

	protected $enable_log = false;
	
	/**
	 * as __construct()
	 */
	public function logTrait($config = [])
	{
		if (isset($config['enable_log'])) {
			$this->enable_log = (bool)$config['enable_log'];
		}
		$this->log_config = $config;
	}
	
	public function setLogFile($file)
	{
		if ($this->logger === null) {
			$this->log_config['logFile'] = $file;
		} else {
			$this->logger->logFile = $file;
		}
	}
	
	/**
	 * log
	 * @param string $message
	 * @param string $type 'info', 'sql', 'error', 'notice'等等
	 * @param string $errno
	 * @param string $error
	 */
	protected function log($message, $type = 'info', $errno = null, $error = '')
	{
		if (!$this->enable_log) {
			return;
		}
	
		if ($this->logger === null) {
			$this->logger = new Logger($this->log_config);
		}
	
		$this->logger->log($message, $type, $errno, $error);
	}
}