<?php
namespace vendor\base;

use vendor\exceptions\InvalidConfigException;

/**
 * 一种container类一个运行实例
 */
abstract class ServiceContainer
{
	protected static $instances;
	
	public $componentsNamespace = '\vendor\base';
	protected $components = [];
	
	protected function __construct($config = [])
	{
		if (!isset($config['components'])) {
			return ;
		}
		$components = $config['components'];
		foreach ($components as $component) {
			if (isset($config[$component]) && is_object($config[$component])) {
				$this->components[$component] = $config[$component];
			} else {
				isset($config[$component]['class']) ?: $config[$component]['class'] = $this->componentsNamespace . '\\' . ucfirst($component);
				if (class_exists($config[$component]['class'])) {
					$this->components[$component] = $config[$component];
				} else {
					throw new InvalidConfigException('app config error');
				}
			}
		}
	}
	
	/**
	 * 如果实例已存在，不会重新创建
	 */
	public static function getInstance($config = [])
	{
		$staticClass = static::class;
		if (!isset(static::$instances[$staticClass])) {
			static::$instances[$staticClass] = new $staticClass($config);
		}
		return static::$instances[$staticClass];
	}
	
	public function __get($name)
	{
		if (isset($this->components[$name])) {
			if (!is_object($this->components[$name])) {
				$component = $this->components[$name];
				$class = $component['class'];
				unset($component['class']);
				$this->components[$name] = new $class($component);
			}
			return $this->components[$name];
		}
		return null;
	}
	
	public function __set($name, $value)
	{
		$this->components[$name] = $value;
	}
	
}