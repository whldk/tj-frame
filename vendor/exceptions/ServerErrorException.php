<?php
namespace vendor\exceptions;

class ServerErrorException extends ErrorException
{
	public function __construct($message = null, $code = null, $previous = null)
	{
		parent::__construct([SERVER_ERR => ERR_SERVER], SERVER_ERR . ':' . $message, $code, $previous);
	}
}