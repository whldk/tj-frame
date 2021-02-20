<?php

use vendor\base\BaseModule;
use vendor\exceptions\HttpException;
use vendor\exceptions\ErrorException;
use vendor\exceptions\UserErrorException;
use vendor\exceptions\NotFoundException;
use vendor\base\Response;

/**
 * @property \vendor\base\Route $route
 */
final class App extends BaseModule
{
	protected static $modules = [];		//全部注册模块
	/**
	 * @var BaseModule
	 */
	protected static $runningModule = null;	//当前访问的模块
	
	protected function __construct($config = [])
	{
		parent::__construct($config);
		//register shutdown function
		register_shutdown_function([self::class, 'shutdownHandler']);
	}

	protected function init()
	{
		$constantsFile = DIR_APP . "/config/constants.php";
		if (is_file($constantsFile)) {
			require_once $constantsFile;
		}
		return parent::init();
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
		} elseif ($dotPos = strpos($name, '.')) {
			$module = substr($name, 0, $dotPos);
			$name = substr($name, $dotPos + 1);
			$moduleInstance = self::getModuleInstance($module);
			return $moduleInstance->__get($name);
		}
		throw new HttpException(500, [], static::class . "::{$name} : property does not exist");
	}
	
	public function __set($name, $value)
	{
		if ($value === null) {
			return;
		}
		if ($dotPos = strpos($name, '.')) {
			$module = substr($name, 0, $dotPos);
			$name = substr($name, $dotPos + 1);
			$moduleInstance = self::getModuleInstance($module);
			$moduleInstance->$name = $value;
		} else {
			$this->components[$name] = $value;
		}
	}
	
	public static function getModuleName($moduleRoute = null)
	{
		if ($moduleRoute === null) {
			$name = __NAMESPACE__;
		} else {
			$name = str_replace(' ', '', ucwords(str_replace('-', ' ', $moduleRoute)));
			$name = lcfirst($name);
		}
		return $name;
	}
	
	/**
	 * 返回模块实例
	 * 跟父类不同的两点：
	 * 1、处理了null的情况
	 * 2、进行了模块注册
	 * @return \vendor\base\BaseModule
	 */
	public static function getModuleInstance($name = null, $config = null)
	{
		if (!key_exists($name, self::$modules)) {
			self::$modules[$name] = parent::getModuleInstance($name, $config);
			if (self::$modules[$name]) {
				//成功获取模块
				if (!self::$modules[$name] instanceof static) {
					//不能是自身同类
					self::$modules[$name]->setParentModule(static::getInstance());
				}
			} else {
				//将对应名称指向App
				self::$modules[$name] = static::getInstance();
			}
		}
		return self::$modules[$name];
	}
	
	public function bootstrap()
	{
		try {
			$request = self::__get('request');
			list($module, $controller, $action) = $this->route->resolve($request);
            //获取module实例
			if ($module) {
				$name = self::getModuleName($module);
				self::$runningModule = self::getModuleInstance($name);
				if (!self::$runningModule) {
					throw new NotFoundException();
				}
				if (self::$runningModule->request !== $request) {
					$request = $this->request = self::$runningModule->request;
					list(, $controller, $action) = $this->route->resolve($request);
				}
				if (self::$runningModule->session !== $this->session) {
					$this->session = self::$runningModule->session;
				}
				if ($proxyTo = self::$runningModule->proxyTo()) {
				    self::$modules[$proxyTo] = self::$runningModule;
				}
			} else {
				self::$runningModule = $this;
			}
			$response = self::$runningModule->run($controller, $action);
		} catch (\ReflectionException $e) {
			$this->response->setStatusCode(404);
		} catch (HttpException $e) {
			$statusCode = $e->getStatusCode();
			$this->response->setStatusCode($statusCode);
			$result = [];
			if ($statusCode >= 400 && $statusCode < 500) {
				$result = ['error' => $e->getErrors()];
			}
			if (defined('DEBUG') && DEBUG === true) {
				$result += ['code' => $e->getCode(), 'msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
			}
			!$result ?: $this->response->data = $result;
		} catch (UserErrorException $e) {
			$this->response->setStatusCode(400);
			$result = ['error' => $e->getErrors()];
			if (defined('DEBUG') && DEBUG === true) {
				$result += ['code' => $e->getCode(), 'msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
			}
			$this->response->data = $result;
		} catch (ErrorException $e) {
			$this->response->setStatusCode(500);
			if (defined('DEBUG') && DEBUG === true) {
				$result = ['error' => $e->getErrors(), 'code' => $e->getCode(), 'msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
				$this->response->data = $result;
			}
		} catch (\Exception $e) {
			$this->response->setStatusCode(500);
			if (defined('DEBUG') && DEBUG === true) {
				$result = ['code' => $e->getCode(), 'msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
				$this->response->data = $result;
			}
		} finally {
			if (!isset($response)) {
				$response = $this->response;
			}
			if (is_array($response->data)) {
				$response->format = Response::FORMAT_JSON;
			}
		}
		
		$exitStatus = $response->exitStatus;
		
		$response->send();
		$response->clear();
		
		return $exitStatus;
	}

	public static function getRunningModule()
	{
		return self::$runningModule;
	}
	
	public static function shutdownHandler()
	{
		$last_error = error_get_last();
		if ($last_error && $last_error['type'] === E_ERROR) {
			http_response_code(500);
			if (defined('ENV') && ENV === 'dev' && defined('DEBUG') && DEBUG === true) {
				echo json_encode($last_error);
			}
		}
	}
}