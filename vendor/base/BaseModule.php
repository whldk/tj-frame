<?php
namespace vendor\base;

use vendor\exceptions\HttpException;
use vendor\exceptions\AuthException;

/**
 * @property \vendor\base\Response $response
 * @property \vendor\base\IdentityInterface $user
 */
abstract class BaseModule extends ServiceContainer
{
	protected $access = [];
	/**
	 * @var BaseModule
	 */
	protected $parentModule = null;
	
	/**
	 * @var Controller
	 */
	protected $runningController = null;
	
	protected $runningControllerName = null;	//lowercase开头的控制器类名，不含有Controller部分，驼峰模式
	protected $runningActionName = null;		//lowercase开头的action名，不含有action部分，驼峰模式
	
	protected $params = [];
	
	protected function __construct($config = [])
	{
		if (isset($config['access'])) {
			$this->access = (array)$config['access'];
		}
		
		if (isset($config['params'])) {
			$this->params = (array)$config['params'];
		}
		
		$this->init();
		
		return parent::__construct($config);
	
	}

	protected function init() {}
	
	public function proxyTo()
	{
	    return null;
	}
	
	public function getParam($name)
	{
		return isset($this->params[$name]) ? $this->params[$name] : $this->parentModule->getParam($name);
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
		} elseif ($this->parentModule) {
			return $this->parentModule->__get($name);
		}
		throw new HttpException(500, [], static::class . "::{$name} : property does not exist");
	}
	
	public function setParentModule(BaseModule $parent)
	{
		//不能是自身同类
		if (!$parent instanceof static) {
			$this->parentModule = $parent;
		}
	}
	
	public function getNamespace()
	{
	    if ($proxyTo = $this->proxyTo()) {
	        return static::getModuleNamespace($proxyTo);
	    } else {
	        $reflectionClass = new \ReflectionClass($this);
	        return $reflectionClass->getNamespaceName();
	    }
	}
	
	/**
	 * @param \vendor\base\IdentityInterface $identity
	 * @param string $controller shortName of controller
	 * @throws AuthException
	 * @return boolean
	 */
	public function access($identity, $controllerName)
	{
		$access = $this->access;
		$access = isset($access[$controllerName]) ? $access[$controllerName] : (isset($access['*']) ? $access['*'] : null);
	
		if (!$access || in_array('*', $access)) {
			return true;
		}
		
		$role = $identity->getRole();
		
		if ($role == '?') {
			if (in_array($role, $access)) {
				return true;
			}
		} else {
			if (in_array('@', $access) || in_array($role, $access)) {
				return true;
			}
		}
		
		throw new AuthException($identity);
	}
	
	/**
	 * @throws \ReflectionException
	 * @throws HttpException
	 * @throws \Exception
	 * @return Response
	 */
	public function run($controller, $action)
	{
		$response = $this->response;
		
		$controller = $this->getRunningController($controller, $action);

		$ob_start_level = 0;
		if (defined('ENV') && ENV === 'prod') {
			ob_start();
			$ob_start_level = ob_get_level();
		}
		
		$response->data = $controller->run();
		
		if (defined('ENV') && ENV === 'prod') {
			$ob_end_level = ob_get_level();
			if ($ob_end_level === $ob_start_level) {
				$response->clearOutputBuffers();
			}
		}

		return $response;
	}
	
	/**
	 * @param string $controller xxx-xxx 控制器路由，中划线模式
	 * @param string $action	xxx-xxx action路由，中划线模式
	 * @throws \ReflectionException
	 * @throws \Exception
	 * @return Controller
	 */
	public function getRunningController($controller = null, $action = null, $checkAccess = true)
	{
		if ($controller === null && $this->runningController) {
			return $this->runningController;
		}
		
		$controller = str_replace(' ', '', ucwords(str_replace('-', ' ', $controller)));
		$action = str_replace(' ', '', ucwords(str_replace('-', ' ', $action)));
		
		$moduleNamespace = $this->getNamespace();
		!$moduleNamespace ?: $moduleNamespace = '\\' . $moduleNamespace;
		$controllerNamespace = "{$moduleNamespace}\\controllers";
		$controllerClass = "{$controllerNamespace}\\{$controller}Controller";
		$actionMethod = 'action' . $action;
		
		try {
			$controllerReflection = new \ReflectionClass($controllerClass);
			if (!$controllerReflection->isSubclassOf(Controller::class)) {
				throw new \ReflectionException();
			}
			$actionReflection = $controllerReflection->getMethod($actionMethod);
			if (!$actionReflection->isPublic()) {
				throw new \ReflectionException();
			}
			$controllerName = $controllerReflection->getShortName();
			$actionName = $actionReflection->getShortName();
		} catch (\ReflectionException $e) {
			throw $e;
		} catch (\Exception $e) {
			throw $e;
		}
		
		$this->runningControllerName = lcfirst(substr($controllerName, 0, -strlen('Controller')));	//lowercase first
		$this->runningActionName = lcfirst(substr($actionName, strlen('action')));	//lowercase first
		
		if ($checkAccess && $this->access($this->user, $this->runningControllerName)) {
			$this->runningController = new $controllerClass($actionName);
		} else {
			$this->runningController = null;
		}
		
		return $this->runningController;
	}

	/**
	 * 继承单例模式
	 * @param string $name
	 * @return \vendor\base\BaseModule | NULL
	 */
	public static function getModuleInstance($name = null, $config = null)
	{
		if (!$name) {
			$class = static::class;
		} else {
			$class = '\\' . static::getModuleNamespace($name) . '\\Module';
			
			try {
				$reflection = new \ReflectionClass($class);
				if (!$reflection->isSubclassOf(self::class)) {
					throw new \ReflectionException();
				}
			} catch (\ReflectionException $e) {
				return null;
			}
		}
		
		$config !== null ?: $config = static::getModuleConfig($name);
		/* @var $class \vendor\base\BaseModule */
		$instance = $class::getInstance($config);
		
		return $instance;
	}
	
	/**
	 * @param string $name
	 * @return array
	 */
	protected static function getModuleConfig($name)
	{
		$dir = static::getModuleDir($name);
		!$dir ?: $dir = $dir . '/';
		$configFile = DIR_APP . "/{$dir}config/config.php";
		if (is_file($configFile)) {
			return require $configFile;
		} else {
			return [];
		}
	}
	
	/**
	 * 模块路由->模块名称
	 * 模块名称必须是全局唯一的，目前暂时按照命名空间/文件相对路径规则获取
	 */
	public static function getModuleName($moduleRoute = null)
	{
		return '';
	}
	
	public function getRunningControllerName()
	{
		return $this->runningControllerName;
	}
	
	public function getRunningActionName()
	{
		return $this->runningActionName;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	protected static function getModuleNamespace($name)
	{
		return $name ?: '';
	}

	/**
	 * @param string $name
	 * @return string
	 */
	protected static function getModuleDir($name)
	{
		return $name ?: '';
	}
	
	/**
	 * 暂时仅支持controllers和models反向查找Module名称
	 */
	public static function getModuleNameByNamespace($namespace)
	{
		static $cache = [];
		
		if (!key_exists($namespace, $cache)) {
			if ($pos = strrpos($namespace, '\\controllers', -1) ?: strrpos($namespace, '\\models', -1) ?: strrpos($namespace, '\\components', -1)) {
				$cache[$namespace] = trim(substr($namespace, 0, $pos), '\\');
			} else {
				$cache[$namespace] = null;
			}
		}
		
		return $cache[$namespace];
	}
}