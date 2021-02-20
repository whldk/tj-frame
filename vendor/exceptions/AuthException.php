<?php
namespace vendor\exceptions;

class AuthException extends HttpException
{
	public function __construct($identity = false, $message = null, $code = null, $previous = null)
	{
		$statusCode = $identity === false || $identity ? 403 : 401;
		if ($statusCode === 403) {
		    $err_msg = '权限不足,无法访问';
        } else {
		    $err_msg = '未登录,请先登录账号';
        }
		parent::__construct($statusCode, $err_msg, $message, $code, $previous);
	}
}