<?php
namespace vendor\base; 

class Logger
{
	const MODE_CLEAR = 1;
	const MODE_RENAME = 2;
	
	public $logFile;
	public $maxFileSize = LOG_FILE_SIZE;
	public $maxLogTimes = 100000;
	protected $logTimes = 0;
	protected $mode = self::MODE_CLEAR;
	
	public function __construct($config = [])
	{
		foreach ($config as $k => $v) {
			switch ($k) {
				case 'logFile' : 
					$this->$k = strval($v);
					break;
				case 'maxFileSize' : 
				case 'maxLogTimes' : 
					$this->$k = intval($v);
					break;
				case 'mode' :
					$this->$k = intval($v);
					if ($this->$k !== self::MODE_CLEAR && $this->$k !== self::MODE_RENAME || defined(ENV) && ENV == 'prod') {
						$this->$k = self::MODE_CLEAR;
					}
					break;
			}
		}
		if (!$this->logFile) {
			$this->logFile = DIR_LOG . DIRECTORY_SEPARATOR . APP_NAME . '.log';
		}
	}
	
	public function log($message, $type = 'info', $errno = null, $error = '', $file = null)
	{
		$file !== null ?: $file = $this->logFile;
		
		if ($this->logTimes > 1000) {
			clearstatcache();
			$this->logTimes = 0;
		} else {
			$this->logTimes++;
		}
		
		$fd = null;
		if (is_file($file)) {
			if (filesize($file) > $this->maxFileSize) {
				if ($this->mode === self::MODE_RENAME) {
					$dot_pos = strrpos($file, '.');
					if ($dot_pos === false) {
						$new_file = $file . date('_YmdHis');
					} else {
						$new_file = substr_replace($file, date('_YmdHis'), $dot_pos, 0);
					}
					rename($file, $new_file);
				}
				$fd = fopen($file, 'w');
			} else {
				$fd = fopen($file, 'a+');
			}
		} else {
			$dir = dirname($file);
			if (is_dir($dir) === true || mkdir($dir, DIR_MODE, true) === true) {
				$fd = fopen($file, 'w');
			}
		}
		
		//文件错误，直接忽略
		if (!$fd) {
			error_log(__METHOD__ . ': failed to open ' . $file);
			return;
		}
		
		$info = date('Y-m-d H:i:s', time()) . " [{$type}]:\n";
		is_null($errno) ?: $info .= 'errno : ' . $errno . "\n";
		empty($error) ?: $info .= 'error : ' . $error . "\n";
		empty($message) ?: $info .= 'message : ' . $message . "\n";
		if ($type === 'error') {
			ob_start();
			debug_print_backtrace(0, STRACE_LIMIT);
			$info .= 'strace : ' . ob_get_clean() . "\n";
		}
		$info .= "\n";
		
		fwrite($fd, $info);
		fclose($fd);
	}
}