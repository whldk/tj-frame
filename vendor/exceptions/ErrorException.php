<?php
namespace vendor\exceptions;

abstract class ErrorException extends \Exception
{
	protected $errors = [];
	
	public function __construct($errors, $message = null, $code = null, $previous = null)
	{
		$this->errors = $errors;
		parent::__construct($message, $code, $previous);
	}
	
	public function getErrors()
	{
		return $this->errors;
	}
}