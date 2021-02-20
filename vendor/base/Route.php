<?php
namespace vendor\base;

class Route
{
	public $module = '';	//默认模块
	public $controller = 'site'; //默认控制器
	public $action = 'index'; //默认操作
	
	public function __construct($config = [])
	{
		foreach ($config as $k => $v) {
			switch ($k) {
				case 'module' : 
				case 'controller' :
				case 'action' :
					$v = strval($v);
					$this->$k = trim(strtolower($v));
					break;
			}
		}
	}
	
	/**
	 * @param BaseRequest $request
	 */
	public function resolve($request)
	{
		$path = $request->getPathinfo();
		if (!$path) {
			$route = [];
		} else {
			$route = explode('/', trim(strtolower($path), '/'));
		}
		
		switch (count($route)) {
			case 0 : 
				break;
			case 1 :
				$this->controller = $route[0];
				break;
			case 2 : 
				list($this->controller, $this->action) = $route;
				break;
			default : 
				list($this->module, $this->controller, $this->action) = $route;
				break;
		}
		$this->module = strtolower($this->module);
		$this->controller = strtolower($this->controller);
		$this->action = strtolower($this->action);
		
		return [$this->module, $this->controller, $this->action];
	}
}