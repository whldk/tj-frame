<?php

function autoload($class)
{
	static $vendors = ['chillerlan'];
	
	$class = trim($class, '/\\');
	$classPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $class) . '.php';
	
	$path = __DIR__ . DIRECTORY_SEPARATOR . $classPath;
	if (is_file($path)) {
		return require_once $path;
	}
	
	if ($slashPos = strpos($classPath, DIRECTORY_SEPARATOR, 1)) {
		//需要进一步映射
		$vendor = substr($classPath, 0, $slashPos);
		if (in_array($vendor, $vendors)) {
			$path = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $classPath;
			if (is_file($path)) {
				return require_once $path;
			}
		}
	}
	
	return 0;
}

spl_autoload_register('autoload');

//third party
require_once __DIR__ . '/vendor/html_purifier/HTMLPurifier.auto.php';
require_once __DIR__ . '/vendor/excel/PHPExcel/Autoloader.php';
require_once __DIR__ . '/vendor/alibaba/autoload.php';

//读取ppt内容
require_once __DIR__ . '/vendor/ppt/autoload.php';

//处理pdf 水印
require_once __DIR__ . '/vendor/fpdf/chinese.php';
require_once __DIR__ . '/vendor/fpdi/src/autoload.php';