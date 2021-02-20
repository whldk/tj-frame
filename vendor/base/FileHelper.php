<?php
namespace vendor\base;

class FileHelper
{
	public static $mimeMagicFile = 'mimeTypes.php';
	
	public static function getMimeType($file, $magicFile = null, $checkExtension = true)
	{
		if (!extension_loaded('fileinfo')) {
			if ($checkExtension) {
				return static::getMimeTypeByExtension($file, $magicFile);
			} else {
				throw new \Exception('The fileinfo PHP extension is not installed.');
			}
		}
		$info = finfo_open(FILEINFO_MIME_TYPE, $magicFile);
	
		if ($info) {
			$result = finfo_file($info, $file);
			finfo_close($info);
	
			if ($result !== false) {
				return $result;
			}
		}
	
		return $checkExtension ? static::getMimeTypeByExtension($file, $magicFile) : null;
	}
	
	/**
	 * Determines the MIME type based on the extension name of the specified file.
	 * This method will use a local map between extension names and MIME types.
	 * @param string $file the file name.
	 * @param string $magicFile the path
	 * @return string the MIME type. Null is returned if the MIME type cannot be determined.
	 */
	public static function getMimeTypeByExtension($file, $magicFile = null)
	{
		$mimeTypes = static::loadMimeTypes($magicFile);
	
		if (($ext = pathinfo($file, PATHINFO_EXTENSION)) !== '') {
			$ext = strtolower($ext);
			if (isset($mimeTypes[$ext])) {
				return $mimeTypes[$ext];
			}
		}
	
		return null;
	}
	
	private static $_mimeTypes = [];
	
	/**
	 * Loads MIME types from the specified file.
	 * @param string $magicFile the path (or alias) of the file that contains all available MIME type information.
	 * If this is not set, the file specified by [[mimeMagicFile]] will be used.
	 * @return array the mapping from file extensions to MIME types
	*/
	protected static function loadMimeTypes($magicFile)
	{
		if ($magicFile === null) {
			$magicFile = static::$mimeMagicFile;
		}
		if (!isset(self::$_mimeTypes[$magicFile])) {
			self::$_mimeTypes[$magicFile] = require($magicFile);
		}
		return self::$_mimeTypes[$magicFile];
	}
	
	public static function filename($path, $extension = false)
	{
		$slashPos = mb_strrpos($path, '/');
		
		if ($slashPos !== false) {
			$path = mb_substr($path, $slashPos + 1);
		}
		
		$dotPos = mb_strrpos($path, '.');
		if ($dotPos === false) {
			return $path;
		} else {
			return $extension ? $path : mb_substr($path, 0, $dotPos);
		}
	}
	
	public static function symlink($target, $link)
	{
		if (file_exists($link) || is_link($link)) {
			unlink($link);
		}
		return symlink($target, $link);
	}
}