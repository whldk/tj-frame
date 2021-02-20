<?php
namespace vendor\sdk;

use vendor\base\Logger;

class IlabJwt
{
	const TYPE_RESERVED = 0;
	const TYPE_JSON = 1;
	const TYPE_SYS = 2;
	
	public static $enableLog = true;
	protected static $logger = null;
	
	protected static $appName = ILAB_APP_NAME;
	
	protected static $issuerId = ILAB_APP_ISSUER_ID;
	protected static $secretKey = ILAB_APP_SECRET_KEY;
	protected static $aesKey = ILAB_APP_AES_KEY;
	
	public static function getJwt($body)
	{
		$body = json_encode($body, JSON_UNESCAPED_UNICODE);	//JSON_UNESCAPED_UNICODE 必须
		
		$header = self::packHeader();
		$body = self::encrypt($body);
		
		$base64Header = base64_encode($header);
		$base64Payload = base64_encode($body);
		$base64Signature = base64_encode(self::sign($base64Header, $base64Payload));
		
		return "{$base64Header}.{$base64Payload}.{$base64Signature}";
	}
	
	protected static function packHeader()
	{
		$header = '';
		
		$expiry = round((microtime(true) + 900) * 1000);	//900秒过期时间
		$header .= pack('J', $expiry);
		
		$type = pack('n', self::TYPE_SYS);
		$header .= $type[1];
		
		$header .= pack('J', self::$issuerId);
		
		return $header;
	}
	
	protected static function encrypt($body)
	{
        $payload = '';

        //前接8字节随机整数
        $randLong = pack('J', rand(0, PHP_INT_MAX));
        $payload .= $randLong;

        $payload .= $body;

        //补齐为64字节的整数倍
        $tempLen = strlen($payload) + 1;
        $paddingLen = (16 - $tempLen % 16) % 16;
        $padding = str_pad('', $paddingLen + 1, pack('c', $paddingLen));
        $payload .= $padding;

        //aes加密
        $aesKey = base64_decode(self::$aesKey);
        $iv = substr($aesKey, 0, 16);

        $payload = openssl_encrypt($payload, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);

        return $payload;

    }
	
	protected static function sign($base64Header, $base64Payload)
	{
		$signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, self::$secretKey, true);
		return $signature;
	}
	
	public static function getBody($jwt)
	{
		@list($base64Header, $base64Payload, $base64Signature) = explode('.', $jwt);
		
		$header = base64_decode($base64Header);
		$payload = base64_decode($base64Payload);
		$signature = base64_decode($base64Signature);
		
		if (!self::validateSignature($signature, $base64Header, $base64Payload)) {
			self::log('Signature is invalid.');
			return null;
		}
		
		//header:expiry
		$expiry = substr($header, 0, 8);
		if (self::isExpired($expiry)) {
			self::log('Data is expired.');
			return null;
		}
		
		//header:type
		$type = substr($header, 8, 1);
		if (!self::isValidType($type)) {
			self::log('Type is invalid.');
			return null;
		}
		
		//header:issuerid
		$issuerId = substr($header, 9);
		if (!self::isValidIssuer($issuerId)) {
			self::log('Issuer is invalid.');
			return null;
		}
		
		//payload
		$body = self::decrypt($payload);
		if (!$body) {
			return null;
		}
		
		//['id' => xxx, 'un' => 'xxx', 'dis' => 'xxx']
		return json_decode($body, true);
		
	}
	
	protected static function isExpired($expiry)
	{
		$expiry = unpack('J', $expiry);
		
		$expiry = $expiry[1] / 1000;
		
		self::log('Expiry : ' . $expiry);
		
		return $expiry + 1800 < time();
	}
	
	protected static function isValidType($type)
	{
		$type = unpack('n', "\0" . $type);
		
		$type = $type[1];
		
		self::log('Type : ' . $type);
		
		return $type === self::TYPE_JSON;
	}
	
	protected static function isValidIssuer($issuerId)
	{
		$issuerId = unpack('J', $issuerId);
		
		$issuerId = $issuerId[1];
		
		self::log('IssuerId : ' . $issuerId);
		
		return $issuerId === self::$issuerId;
	}
	
	protected static function decrypt($payload)
	{
        $aesKey = base64_decode(self::$aesKey);
        $iv = substr($aesKey, 0, 16);

        $data = openssl_decrypt($payload, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);

        $dataLen = strlen($data);
        $paddingLen = unpack('n', "\0" . $data[$dataLen - 1]);
        $paddingLen = $paddingLen[1];

        $body = substr($data, 8, - $paddingLen - 1);

        self::log('Body : ' . $body);

        return $body;
	}
	
	protected static function validateSignature($signature, $base64Header, $base64Payload)
	{
		$caculatedSignature = self::sign($base64Header, $base64Payload);
		
		self::log('Caculated signature (base64 code) : ' . base64_encode($caculatedSignature)
				. ', received signature (base64 code) : ' . base64_encode($signature));
		
		return $caculatedSignature === $signature;
	}
	
	public static function log($message, $type = 'info', $errno = null, $error = '', $file = null)
	{
		if (!self::$enableLog) {
			return;
		}
		
		if (!self::$logger) {
			self::$logger = new Logger(['logFile' => DIR_LOG . '/ilab_sdk.log']);
		}
		
		return self::$logger->log($message, $type, $errno, $error, $file);
	}
}