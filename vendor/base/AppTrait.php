<?php
namespace vendor\base;

use vendor\exceptions\HttpException;

/**
 * 主要是为了访问全局组件，全局组件是对象
 * 解析缓存
 */
trait AppTrait
{
	/**
	 * @param string $name
	 */
	public function __get($name)
	{
		static $cache = [];
		
		$class = static::class;
		
		if (isset($cache[$class])) {
			$module = $cache[$class];
		} else {
			//解析模块名称，不一定对应有效的模块
			$moduleName = \App::getModuleNameByNamespace($class);
			$module = $cache[$class] = \App::getModuleInstance($moduleName);
		}
		
		return $module->__get($name);
	}
	
	public static function __callStatic($name, $arguments) 
    {
		static $cache = [];
		
		$class = static::class;
		
		if (strpos($name, 'get') === 0 && strlen($name) > 3) {
			$name = lcfirst(substr($name, 3));
			
			if (isset($cache[$class])) {
				$module = $cache[$class];
			} else {
				//解析模块名称，不一定对应有效的模块
				$moduleName = \App::getModuleNameByNamespace($class);
				$module = $cache[$class] = \App::getModuleInstance($moduleName);
			}
			
			return $module->__get($name);
		}
    	
		throw new HttpException(500, [], $class . "::{$name} : static method does not exist");
	}
	
}