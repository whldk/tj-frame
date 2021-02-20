<?php
namespace vendor\base;

trait ViewTrait
{
	protected $viewPath = '';
	protected $view;
	
	protected function render($view, $data = [], $statusCode = 200)
	{
		$this->response->setStatusCode($statusCode);
		$this->response->format = Response::FORMAT_HTML;
	
		if (!$this->view) {
			$this->view = new View(['dir' => $this->viewPath]);
		}
	
		return $this->view->render($data, $view);
	}
}