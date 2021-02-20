<?php
namespace doctoru;

use vendor\base\BaseModule;

final class Module extends BaseModule
{
	public static function getModuleName($moduleRoute = null)
	{
		return __NAMESPACE__;
	}
}