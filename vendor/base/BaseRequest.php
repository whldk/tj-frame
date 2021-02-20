<?php
namespace vendor\base;

abstract class BaseRequest
{
	protected $_pathinfo;
	
	protected $_bodyParams;
	
	public function __construct($config = []){}
	
	abstract public function getPathinfo();
	
	abstract public function getBodyParams();
}