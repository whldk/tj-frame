<?php
namespace vendor\base;

class View
{
	protected $dir = '';
	
	public function __construct($config = [])
	{
		if (isset($config['dir'])) {
			$this->dir = $config['dir'];
		}
	}
	
	/**
	 * @param array $data
	 * @param string $view
	 * @param string $dir
	 * @return string
	 */
	public function render($data, $view)
	{
		ob_start();
		ob_implicit_flush(false);
		
		extract($data, EXTR_OVERWRITE);
		
		include_once $this->dir . '/' . $view;
		
		$output = ob_get_clean();
		
		return $output;
	}
}