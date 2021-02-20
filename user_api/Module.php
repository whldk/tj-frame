<?php
namespace user_api;

use vendor\base\ProxyModuleTrait;

final class Module extends \user\Module
{
    use ProxyModuleTrait;

    public static function getModuleName($moduleRoute = null)
    {
        return __NAMESPACE__;
    }
}