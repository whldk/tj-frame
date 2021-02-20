<?php

namespace vendor\base;

class Response extends BaseResponse
{
	const FORMAT_RAW = 'raw';
	const FORMAT_HTML = 'html';
	const FORMAT_JSON = 'json';
	
	public $format = self::FORMAT_RAW;
	
	public $data;
	
	public $content;
	
	public $stream;
	
	public $charset = 'UTF-8';
	
	public $statusText = 'OK';
	
	public $isSent = false;
	
	public $exitStatus = 0;
	
	public static $statuses = [
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',
			118 => 'Connection timed out',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			208 => 'Already Reported',
			210 => 'Content Different',
			226 => 'IM Used',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => 'Reserved',
			307 => 'Temporary Redirect',
			308 => 'Permanent Redirect',
			310 => 'Too many Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Time-out',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested range unsatisfiable',
			417 => 'Expectation failed',
			418 => 'I\'m a teapot',
			421 => 'Misdirected Request',
			422 => 'Unprocessable entity',
			423 => 'Locked',
			424 => 'Method failure',
			425 => 'Unordered Collection',
			426 => 'Upgrade Required',
			428 => 'Precondition Required',
			429 => 'Too Many Requests',
			431 => 'Request Header Fields Too Large',
			449 => 'Retry With',
			450 => 'Blocked by Windows Parental Controls',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway or Proxy Error',
			503 => 'Service Unavailable',
			504 => 'Gateway Time-out',
			505 => 'HTTP Version not supported',
			507 => 'Insufficient storage',
			508 => 'Loop Detected',
			509 => 'Bandwidth Limit Exceeded',
			510 => 'Not Extended',
			511 => 'Network Authentication Required',
	];
	
	protected $_statusCode = 200;
	
	private $_headers;
	
	public $version;
	
	private $_cookies;
	
	/**
	 * @var int the encoding options passed to [[Json::encode()]]. For more details please refer to
	 * <http://www.php.net/manual/en/function.json-encode.php>.
	 * Default is `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
	 * This property has no effect, when [[useJsonp]] is `true`.
	 * @since 2.0.7
	 */
	public $encodeOptions = 320;
	/**
	 * @var bool whether to format the output in a readable "pretty" format. This can be useful for debugging purpose.
	 * If this is true, `JSON_PRETTY_PRINT` will be added to [[encodeOptions]].
	 * Defaults to `false`.
	 * This property has no effect, when [[useJsonp]] is `true`.
	 * @since 2.0.7
	 */
	public $prettyPrint = false;
	
	public function __construct()
	{
		if ($this->version === null) {
			if (isset($_SERVER['SERVER_PROTOCOL']) && $_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.0') {
				$this->version = '1.0';
			} else {
				$this->version = '1.1';
			}
		}
		
		
	}
	
	public function getStatusCode()
	{
		return $this->_statusCode;
	}
	
	public function getIsInvalid()
	{
		return $this->getStatusCode() < 100 || $this->getStatusCode() >= 600;
	}
	
	public function redirect($url, $statusCode = 302)
	{
		$this->setHeader('Location', $url);
		$this->setStatusCode($statusCode);
		return $this;
	}
	
	public function setStatusCode($value, $text = null)
	{
		if ($value === null) {
			$value = 200;
		}
		$this->_statusCode = (int) $value;
		if ($this->getIsInvalid()) {
			throw new \Exception("The HTTP status code is invalid: $value");
		}
		if ($text === null) {
			$this->statusText = isset(static::$statuses[$this->_statusCode]) ? static::$statuses[$this->_statusCode] : '';
		} else {
			$this->statusText = $text;
		}
	}
	
	public function send()
	{
		if ($this->isSent) {
			return;
		}
		$this->prepare();
		$this->sendHeaders();	//sendcookies在sendheader之后立刻传输
		$this->sendContent();
		
		$this->isSent = true;
	}
	
	protected function prepare()
	{
		if ($this->stream !== null) {
			return;
		}
	
		if ($this->format == static::FORMAT_JSON) {
			$this->setHeader('Content-Type', 'application/json; charset=UTF-8');
			if ($this->data !== null) {
				$options = $this->encodeOptions;
				if ($this->prettyPrint) {
					$options |= JSON_PRETTY_PRINT;
				}
				$this->content = json_encode($this->data, $options);
			}
		} elseif ($this->format === self::FORMAT_RAW || $this->format == self::FORMAT_HTML) {
			if ($this->data !== null) {
				$this->content = strval($this->data);
			}
		} else {
			throw new \Exception("Unsupported response format: {$this->format}");
		}
	
		if (is_array($this->content)) {
			throw new \Exception('Response content must not be an array.');
		} elseif (is_object($this->content)) {
			if (method_exists($this->content, '__toString')) {
				$this->content = $this->content->__toString();
			} else {
				throw new \Exception('Response content must be a string or an object implementing __toString().');
			}
		}
	}
	
	protected function sendContent()
	{
		if ($this->stream === null) {
			echo $this->content;
	
			return;
		}
	
		set_time_limit(0); // Reset time limit for big files
		$chunkSize = 8 * 1024 * 1024; // 8MB per chunk
	
		if (is_array($this->stream)) {
			list ($handle, $begin, $end) = $this->stream;
			@fseek($handle, $begin);
			while (!feof($handle) && ($pos = ftell($handle)) <= $end) {
				if ($pos + $chunkSize > $end) {
					$chunkSize = $end - $pos + 1;
				}
				echo fread($handle, $chunkSize);
				flush(); // Free up memory. Otherwise large files will trigger PHP's memory limit.
			}
			fclose($handle);
		} else {
			while (!feof($this->stream)) {
				echo fread($this->stream, $chunkSize);
				flush();
			}
			fclose($this->stream);
		}
	}
	
	public function sendFile($filePath, $attachmentName = null, $options = [])
	{
		if (!isset($options['mimeType'])) {
			$options['mimeType'] = FileHelper::getMimeTypeByExtension($filePath);
		}
		if ($attachmentName === null) {
			$attachmentName = basename($filePath);
		}
		$handle = fopen($filePath, 'rb');
		$this->sendStreamAsFile($handle, $attachmentName, $options);
	
		return $this;
	}
	
	public function sendStreamAsFile($handle, $attachmentName, $options = [])
	{
		$headers = $this->_headers;
		if (isset($options['fileSize'])) {
			$fileSize = $options['fileSize'];
		} else {
			@fseek($handle, 0, SEEK_END);
			$fileSize = ftell($handle);
		}
	
		$range = $this->getHttpRange($fileSize);
		if ($range === false) {
			$this->setHeader('Content-Range', "bytes */$fileSize");
			$this->setStatusCode(416);
			return $this;
		}
	
		list($begin, $end) = $range;
		if ($begin != 0 || $end != $fileSize - 1) {
			$this->setStatusCode(206);
			$this->setHeader('Content-Range', "bytes $begin-$end/$fileSize");
		} else {
			$this->setStatusCode(200);
		}
	
		$mimeType = isset($options['mimeType']) ? $options['mimeType'] : 'application/octet-stream';
		$this->setDownloadHeaders($attachmentName, $mimeType, !empty($options['inline']), $end - $begin + 1);
	
		$this->format = self::FORMAT_RAW;
		$this->stream = [$handle, $begin, $end];
	
		return $this;
	}
	
	public function setDownloadHeaders($attachmentName, $mimeType = null, $inline = false, $contentLength = null)
	{
		$disposition = $inline ? 'inline' : 'attachment';
		$this->setDefaultHeader('Pragma', 'public')
		->setDefaultHeader('Accept-Ranges', 'bytes')
		->setDefaultHeader('Expires', '0')
		->setDefaultHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
		->setDefaultHeader('Content-Disposition', $this->getDispositionHeaderValue($disposition, $attachmentName));
	
		if ($mimeType !== null) {
			$this->setDefaultHeader('Content-Type', $mimeType);
		}
	
		if ($contentLength !== null) {
			$this->setDefaultHeader('Content-Length', $contentLength);
		}
	
		return $this;
	}
	
	/**
	 * Returns Content-Disposition header value that is safe to use with both old and new browsers
	 *
	 * Fallback name:
	 *
	 * - Causes issues if contains non-ASCII characters with codes less than 32 or more than 126.
	 * - Causes issues if contains urlencoded characters (starting with `%`) or `%` character. Some browsers interpret
	 *   `filename="X"` as urlencoded name, some don't.
	 * - Causes issues if contains path separator characters such as `\` or `/`.
	 * - Since value is wrapped with `"`, it should be escaped as `\"`.
	 * - Since input could contain non-ASCII characters, fallback is obtained by transliteration.
	 *
	 * UTF name:
	 *
	 * - Causes issues if contains path separator characters such as `\` or `/`.
	 * - Should be urlencoded since headers are ASCII-only.
	 * - Could be omitted if it exactly matches fallback name.
	 *
	 * @param string $disposition
	 * @param string $attachmentName
	 * @return string
	 *
	 * @since 2.0.10
	 */
	protected function getDispositionHeaderValue($disposition, $attachmentName)
	{
		$fallbackName = str_replace('"', '\\"', str_replace(['%', '/', '\\'], '_', $attachmentName));
		$utfName = rawurlencode(str_replace(['%', '/', '\\'], '', $attachmentName));
	
		$dispositionHeader = "{$disposition}; filename=\"{$fallbackName}\"";
		if ($utfName !== $fallbackName) {
			$dispositionHeader .= "; filename*=utf-8''{$utfName}";
		}
		return $dispositionHeader;
	}
	
	/**
	 * Determines the HTTP range given in the request.
	 * @param int $fileSize the size of the file that will be used to validate the requested HTTP range.
	 * @return array|bool the range (begin, end), or false if the range request is invalid.
	 */
	protected function getHttpRange($fileSize)
	{
		if (!isset($_SERVER['HTTP_RANGE']) || $_SERVER['HTTP_RANGE'] === '-') {
			return [0, $fileSize - 1];
		}
		if (!preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $matches)) {
			return false;
		}
		if ($matches[1] === '') {
			$start = $fileSize - $matches[2];
			$end = $fileSize - 1;
		} elseif ($matches[2] !== '') {
			$start = $matches[1];
			$end = $matches[2];
			if ($end >= $fileSize) {
				$end = $fileSize - 1;
			}
		} else {
			$start = $matches[1];
			$end = $fileSize - 1;
		}
		if ($start < 0 || $start > $end) {
			return false;
		} else {
			return [$start, $end];
		}
	}
	
	protected function sendHeaders()
	{
		if (headers_sent()) {
			return;
		}
		if ($this->_headers) {
			$headers = $this->_headers;
			foreach ($headers as $name => $values) {
				$name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
				// set replace for first occurrence of header but false afterwards to allow multiple
				$replace = true;
				foreach ($values as $value) {
					header("$name: $value", $replace);
					$replace = false;
				}
			}
		}
		$statusCode = $this->_statusCode;
		header("HTTP/{$this->version} {$statusCode} {$this->statusText}");
		$this->sendCookies();
	}
	
	//header start
	public function setHeader($name, $value = '')
    {
        $name = strtolower($name);
        $this->_headers[$name] = (array) $value;
        return $this;
    }
    
    public function setDefaultHeader($name, $value)
    {
    	$name = strtolower($name);
    	if (empty($this->_headers[$name])) {
    		$this->_headers[$name][] = $value;
    	}
    
    	return $this;
    }
    
    public function addHeader($name, $value)
    {
    	$name = strtolower($name);
    	$this->_headers[$name][] = $value;
    
    	return $this;
    }
    
    public function removeHeader($name)
    {
    	$name = strtolower($name);
    	if (isset($this->_headers[$name])) {
    		$value = $this->_headers[$name];
    		unset($this->_headers[$name]);
    		return $value;
    	} else {
    		return null;
    	}
    }
    
	protected function sendCookies()
	{
		if (!$this->_cookies instanceof Cookies) {
			return;
		}
		
		$request = \App::getInstance()->request;
		$this->_cookies->send($request->enableCookieValidation, $request->cookieValidationKey, $request->validateCookies);
	}
	
	public function getCookies()
	{
		if (!$this->_cookies instanceof Cookies) {
			$this->_cookies = new Cookies();
		}
		return $this->_cookies;
	}
	
	public function clear()
	{
		$this->_headers = null;
		$this->_cookies = null;
		$this->_statusCode = 200;
		$this->statusText = 'OK';
		$this->data = null;
		$this->stream = null;
		$this->content = null;
		$this->isSent = false;
		$this->exitStatus = 0;
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