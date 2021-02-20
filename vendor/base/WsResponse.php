<?php
namespace vendor\base;

class WsResponse extends BaseResponse
{
	protected $_id;
	
	public function __construct($config = [])
	{
		$this->_id = isset($config['_id']) ? $config['_id'] : null;
	}
	
	public function send()
	{
		$this->prepare();
		$this->sendContent();		
	}

	protected function prepare()
	{
		$this->data = ['data' => $this->data];
		$this->data['status'] = $this->_statusCode;
		$this->data['text'] = $this->statusText;
		$this->data['_id'] = $this->_id;
		
		if ($this->format == self::FORMAT_JSON) {
			$this->content = json_encode($this->data, $this->encodeOptions);
		} else {
			$this->content = '';
		}
	}
	
	protected function sendContent()
	{
		echo $this->content;
	}
	
	public function clear()
	{
		$this->_statusCode = 200;
		$this->statusText = 'OK';
		$this->data = null;
		$this->content = null;
	}
}