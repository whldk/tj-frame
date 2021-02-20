<?php
ini_set('default_charset', 'UTF-8'); //default_charset

date_default_timezone_set('Asia/Shanghai'); //timezone

//app name
define('APP_NAME', 'mengoo-esp');
define('URL_APP_SHORT', 'mengoo-esp');
define('URL_APP', @$_SERVER['REQUEST_SCHEME'] . '://' . @$_SERVER['HTTP_HOST'] . '/' . URL_APP_SHORT);

//env
define('ENV', 'dev');	//define('ENV', 'prod');

//env
define('ENV_SIDE', 'backend');	//define('ENV_SIDE', 'frontend');

//debug
define('DEBUG', true);
define('UN_PWD', false);

//strace frames limit
define('STRACE_LIMIT', 3);

define('DIR_MODE', 0755);
define('FILE_MODE', 0644);

//app dir
define('DIR_APP', __DIR__);
define('DIR_LOG', __DIR__ . DIRECTORY_SEPARATOR . 'log');
define('DIR_STATIC', __DIR__ . DIRECTORY_SEPARATOR . 'static');
define('DIR_UPLOAD', __DIR__ . DIRECTORY_SEPARATOR . 'upload');
define('DIR_GROUP', __DIR__ . DIRECTORY_SEPARATOR . 'group');

//配置系统常量
define('CONF_HOST', '172.19.204.246'); //172.19.204.246  127.0.0.1
define('CONF_DB_NAME', 'mengoo_common');	// 选择连接的数据库
define('CONF_USERNAME', 'root');		// 登录数据库的用户名
define('CONF_PASSWORD', 'Mengoo@2020');      //Mengoo@2020  123456
define('CONF_ENABLE_LOG', true);    	// 开启日志记录、开始就会写入文件
define('CONF_SESSION_NAME', 'mengoo-esp');
define('CONF_SESSION_TIMEOUT', 7200);  // 2个小时存活时间
define('CONF_IDENT_COOKIE', 'imengoo-esp');

//log file size
define('LOG_FILE_SIZE', 5242880); //5M

//error handling
if (defined('DEBUG') && DEBUG === true) {
	error_reporting(E_ALL & ~E_DEPRECATED);
} else {
	error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & E_STRICT);
}

if (defined('AGENT') && AGENT === 'http' && defined('ENV') && ENV === 'dev') {
	ini_set('display_errors', '1');
} else {
	if (!defined('AGENT')) {
		define('AGENT', 'app');
	}
	ini_set('display_errors', 0);
	ini_set('log_errors', 1);
	ini_set('ignore_repeated_errors', 1);
	$log_dir = defined('DIR_LOG') ? DIR_LOG : __DIR__ . DIRECTORY_SEPARATOR . 'log';
	if (!is_dir($log_dir) && mkdir($log_dir, DIR_MODE, true) === false) {
		error_log('Can\'t create log dir');
	} else {
		$log_file = $log_dir . DIRECTORY_SEPARATOR . AGENT . '_error.log';
		if (!is_file($log_file)) {
			touch($log_file);
// 		} elseif (filesize($log_file) > LOG_FILE_SIZE) {
// 			file_put_contents($log_file, '');
		}
		ini_set('error_log', $log_file);
	}
}