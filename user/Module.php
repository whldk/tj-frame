<?php
namespace user;

use vendor\base\BaseModule;

class Module extends BaseModule
{
	public static function getModuleName($moduleRoute = null)
	{
		return __NAMESPACE__;
	}
}