<?php
namespace vendor\exceptions;

class NotFoundException extends HttpException
{
	public function __construct($errors = [], $message = null, $code = null, $previous = null)
	{
		parent::__construct(404, $errors, $message, $code, $previous);
	}
}