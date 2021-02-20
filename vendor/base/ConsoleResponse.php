<?php

namespace vendor\base;

class ConsoleResponse extends BaseResponse
{
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
		
		if ($this->format == self::FORMAT_JSON) {
			$this->content = json_encode($this->data, $this->encodeOptions);
		} else {
			$this->content = '';
		}
	}
	
	protected function sendContent()
	{
		echo $this->content . "\n";
	}
	
	public function clear()
	{
		$this->_statusCode = 200;
		$this->statusText = 'OK';
		$this->data = null;
		$this->content = null;
	}

    public function clearOutputBuffers()
    {
        // the following manual level counting is to deal with zlib.output_compression set to On
        for ($level = ob_get_level(); $level > 0; --$level) {
            if (!@ob_end_clean()) {
                ob_clean();
            }
        }
    }
}

