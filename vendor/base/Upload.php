<?php
namespace vendor\base;

class Upload 
{

	protected static $baseDir = DIR_APP;
	protected static $uploadDir = 'upload';
	
	protected static $_files = [];
	protected static $_urls = [];
	
	protected static $errors = [];

	const ERR_UPLOAD = 'err_upload';
	const ERR_SAVE = 'err_save';
	const ERR_MIME = 'err_mime';
	const ERR_SIZE = 'err_size';
	const ERR = 1;
	
	public function __construct($config = [])
	{
		foreach ($config as $k => $v) {
			switch ($k) {
				case 'uploadDir' : 
				case 'baseDir' : 
					self::$$k = str_replace('\\', DIRECTORY_SEPARATOR, trim($v, '/\\'));
			}
		}
	}
	
	public static function urlToFile($url)
	{
		return self::$baseDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, trim($url, '/\\'));
	}
	
	public static function realDir($dir = '', &$uploadUrl = null)
	{
		$uploadDir = self::$uploadDir;
		if ($dir) {
			$uploadDir .= DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, trim($dir, '/\\'));
		}
		
		$uploadUrl === null ?: $uploadUrl = str_replace('\\', '/', $uploadDir);
		$realDir = self::$baseDir . DIRECTORY_SEPARATOR . $uploadDir;
		
		return $realDir;
	}
	
	public function save($dir = null, $reset = true)
	{
		if (self::$_files) {
			$uploadUrl = '';
			$dir = self::realDir($dir, $uploadUrl);
			
			if (!is_dir($dir) && mkdir($dir, DIR_MODE, true) === false) {
				$error = error_get_last();
				self::$errors[self::ERR_SAVE]['dir'] = $error === null ? null : $error['type'];
				return false;
			}
			
			foreach (self::$_files as $key => $file) {
				if (!isset($file['savename'])) {
					$name = self::getUniqueName($file['name']) . '.'. self::getExtension($file['name']);
				} else {
					$name = $file['savename'] . '.' . self::getExtension($file['name']);
					//@todo 删除已存在的文件，谨慎处理！
					if (file_exists($dir . DIRECTORY_SEPARATOR . $name) && is_file($dir . DIRECTORY_SEPARATOR . $name)) {
						unlink($dir . DIRECTORY_SEPARATOR . $name);
					}
				}

				if (isset(self::$_files[$key]['md5'])) {
					self::$_files[$key]['md5'] = md5_file($file['tempName']);
					$name = md5_file($file['tempName']).'.'. self::getExtension($file['name']);
				}

				$res = move_uploaded_file($file['tempName'], $dir . DIRECTORY_SEPARATOR . $name);
				if ($res === false) {
					$error = error_get_last();
					self::$errors[self::ERR_SAVE][$key] = $error === null ? null : $error['type'];
					continue;
				}
                chmod($dir . DIRECTORY_SEPARATOR . $name, FILE_MODE);
				self::$_urls[$key] = $uploadUrl . '/' . $name;
                self::$_urls['name']  = $file['name'];
                self::$_urls['size']  = $file['size'];
			}
		}
		
		if ($reset === true) {
			self::reset();
		}
		
		if (self::$errors) {
			return false;
		}
		
		return true;
	}
	
	protected function getUniqueName($file)
	{
		return md5(uniqid('',true) . mt_rand(0, 10000));
	}
	
	public static function getFiles($key = null)
	{
		if ($key !== null) {
			return isset(self::$_files[$key]) ? self::$_files[$key] : null;
		}
		return self::$_files;
	}
	
	public function getUrls($key = null)
	{
		if ($key !== null) {
			return  isset(self::$_urls[$key]) ? self::$_urls[$key] : null;
		}
		
		return self::$_urls;
	}
	
	public function setSaveNames($names)
	{
		foreach ((array)$names as $key => $name) {
			!isset(self::$_files[$key]) ?: self::$_files[$key]['savename'] = $name;
		}
	}
	
	public function setSizeLimits($sizes)
	{
		foreach ((array)$sizes as $key => $size) {
			self::$_files[$key]['size_limit'] = $size;
		}
	}
	
	public function setMd5Keys($keys)
	{
		foreach ((array)$keys as $key) {
			!isset(self::$_files[$key]) ?: self::$_files[$key]['md5'] = '';
		}
	}
	
	/**
	 * @return string file extension
	 */
	public static function getExtension($name)
	{
		return strtolower(pathinfo($name, PATHINFO_EXTENSION));
	}
	
	public static function errors()
	{
		return self::$errors;
	}
	
	public static function loadFiles($keys = null, $mime = null, $reset = true)
	{
		if ($reset === true) {
			self::reset();
		}
		if (isset($_FILES) && is_array($_FILES)) {
			$files = $keys === null ? $_FILES : array_intersect_key($_FILES, $keys);
			if ($files) {
				foreach ($files as $key => $info) {
					self::loadFilesRecursive(
							$key, $info['name'], $info['tmp_name'], $info['type'], $info['size'], $info['error'], 
							$mime, isset(self::$_files[$key]['size_limit']) ? self::$_files[$key]['size_limit'] : null);
				}
			}
		}
	}
	
	private static function loadFilesRecursive($key, $names, $tempNames, $types, $sizes, $errors, $mime = null, $size = null)
	{
		if (is_array($names)) {
			foreach ($names as $i => $name) {
				self::loadFilesRecursive($key . '[' . $i . ']', $name, $tempNames[$i], $types[$i], $sizes[$i], $errors[$i], $mime, $size);
			}
		} else {
			if ((int)$errors === UPLOAD_ERR_OK) {
				if ($mime !== null) {
					$mime = self::validateMime($mime, $tempNames);
					if ($mime === false) {
						self::$errors[self::ERR_MIME][$key] = self::ERR;
						return;
					}
				}
				if ($size !== null && $sizes > $size) {
					self::$errors[self::ERR_SIZE][$key] = self::ERR;
					return;
				}
				self::$_files[$key] = [
						'name' => $names,
						'tempName' => $tempNames,
						'type' => $mime,
						'size' => $sizes,
						'error' => $errors,
				];
			} else {
				self::$errors[self::ERR_UPLOAD][$key] = $errors;
			}
		}
	}
	
	protected static function validateMime($mime, $file)
	{
		static $finfo = null;
		
		if (!($finfo instanceof \finfo)) {
			$finfo = new \finfo(FILEINFO_MIME_TYPE);
		}
		
		$info = $finfo->file($file);
		
		if ($info !== false) {
			if (is_array($mime)) {
				return in_array($info, $mime, true) ? $info : false;
			}
			if (is_string($mime)) {
				if (false === $pos = strpos($mime, '/')) {
					return strpos($info, $mime . '/') === 0 ? $info : false;
				} else {
					return $info === $mime ? $info : false;
				}
			}
		}
		return false;
	}
	
	public static function reset()
	{
		self::$_files = [];
		self::$errors = [];
	}
}