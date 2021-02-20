<?php
namespace vendor\exceptions;

class HttpException extends ErrorException
{
	protected $statusCode;
	
	public function __construct($statusCode, $errors, $message = null, $code = null, $previous = null)
	{
		$this->statusCode = $statusCode;
		parent::__construct($errors, $message, $code, $previous);
	}
	
	public function getStatusCode()
	{
		return $this->statusCode;
	}
}