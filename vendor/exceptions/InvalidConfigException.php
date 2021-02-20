<?php
namespace vendor\exceptions;

/**
 * InvalidConfigException
 */
class InvalidConfigException extends ServerErrorException
{
	public function __construct($message = null, $code = null, $previous = null)
	{
		$message ?: $message = 'invalid config';
	
		parent::__construct($message, $code, $previous);
	}
}