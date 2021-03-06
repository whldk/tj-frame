<?php
/**
 * All rights reserved
 */
namespace vendor\sdk;

use vendor\sdk\RequestCore;
use vendor\sdk\SdkUtil;

class IlabClient
{
	const USER_AGENT = 'ilab-sdk-v-1.0';
	
	const URL_PREFIX = 'http://';
	
	/**
	 * @var string sever host.
	 */
	protected static $serverHost = 'ilab-x.com';
	
	/**
	 * @param string $server
	 */
	public static function setServerHost($serverHost)
	{
		$pos = strpos($serverHost, "://");
		if ($pos !== false) {
			$pos += 3;
			$serverHost = substr($serverHost, $pos);
		}
		$pos = strpos($serverHost, "/");
		if ($pos !== false) {
			$serverHost = substr($serverHost, 0, $pos);
		}
		
		self::$serverHost = $serverHost;
	}
	
	/**
	 * GMT format time string, 
	 * use strtotime($str) can get timestamp integer from this string
	 * @return string
	 */
	protected static function getGMT()
	{
		date_default_timezone_set('Asia/Shanghai');
		return gmdate('D, d M Y H:i:s') . ' GMT';
	}
	
	/**
	 * @return array
	 * @throws \Exception
	 */
	public static function sendRequest($method, $path, $params, $headers, $body)
	{
		$headers['Host'] = self::$serverHost;
		$headers['Date'] = self::getGMT();
		
		if ($body) {
			$headers['Content-Md5'] = SdkUtil::md5($body);
			$headers['Content-Length'] = strlen($body);
			isset($headers['Content-Type']) === true ?: $headers['Content-Type'] = 'application/json; charset=UTF-8';
		} else {
			$headers['Content-Length'] = 0;
			$headers['Content-Type'] = 'application/json; charset=UTF-8';
		}
	
		$url = self::URL_PREFIX . self::$serverHost . '/' . $path;
		
		if ($params) {
			$url .= SdkUtil::buildQuery($params);
		}
		
		$result = self::request($method, $url, $body, $headers);
		
		return $result[0] != 200 ? false : $result[2];
	}
	

	/**
	 * @return array
	 */
	protected static function request($method, $url, $body, $headers)
	{
		$request = new RequestCore($url);
	
		$request->set_method($method);
		$request->set_useragent(self::USER_AGENT);
	
		foreach ($headers as $key => $value) {
			$request->add_header($key, $value);
		}
	
		if ($request->method === "POST" || $request->method === "PUT") {
			$request->set_body($body);
		}
	
		$request->send_request();
	
		$response = [];
		$response[] = (int)$request->get_response_code();
		$response[] = $request->get_response_header();
		$response[] = $request->get_response_body();
		
		return $response;
	}
}