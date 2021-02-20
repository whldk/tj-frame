<?php
namespace vendor\exceptions;

class DbErrorException extends ErrorException
{
	public function __construct($message = null, $code = null, $previous = null)
	{
		parent::__construct([SERVER_ERR_DB => ERR_SERVER], SERVER_ERR_DB . ':' . $message, $code, $previous);
	}
}