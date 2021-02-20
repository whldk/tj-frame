<?php
namespace vendor\base;

trait ErrorTrait
{
	//全部唯一
	protected static $last_error;
	private static $last_error_msg;
	
	protected $errors = null;
	
	protected static function setLastError($error, string $msg = '')
	{
		self::$last_error = $error;
		self::$last_error_msg = $msg;
	}

	public static function lastError()
	{
		return self::$last_error;
	}
	
	protected function addErrors($fields, $error, string $msg = '')
	{
		self::$last_error = [];

		$fields = (array)$fields;
		foreach ($fields as $field) {
			$this->errors[$field] = $error;
		}

		self::$last_error[$field] = $error;
		self::$last_error_msg = $msg;

		return null;
	}

    protected function addError($error)
    {
//        if ($msg) {
//            return $this->addErrors($error, $msg);
//        }
       // self::$last_error = [];
        $this->errors = $error;
       // self::$last_error_msg = $error;
        return null;
    }

	public function clearErrors()
	{
		self::$last_error = [];
		self::$last_error_msg = '';
		$this->errors = [];
	}
	
	public function errors()
	{
		//@todo testing
		return $this->errors ?: self::$last_error;
	}

	public function mergeLastError()
	{
		$this->errors += self::$last_error;
	}
	
	public function lastErrorMsg()
	{
		return self::$last_error_msg;
	}
}