<?php
namespace vendor\exceptions;

/**
 * 类方法参数传入错误
 */
class InvalidParamException extends \Exception
{
	public function __construct($method = null, $param = null, $message = null, $code = 0, $previous = null)
	{
		$message ?: $message = '参数错误';
		$message = "$method PARAM: \${$param} : " . $message;
		return parent::__construct($message, $code, $previous);
	}
}