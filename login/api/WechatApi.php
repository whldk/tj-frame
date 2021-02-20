<?php
namespace login\api;

use vendor\sdk\RequestCore;

/**
 * @todo 填充
 */
class WechatApi
{
	protected static $appid = 'wx43eaab2c564ee715';
	protected static $appserect = 'ae9445466a035d76d485949561c8b95c';

	public function __construct($config)
    {
        if (isset($config['appid']) && isset($config['appserect'])){
            self::$appid = $config['appid'];
            self::$appserect = $config['appserect'];
        }
    }
    /**
	 * 
	 * @param string $code
	 */
	public static function verifyToken($code)
	{
		$url = sprintf(
					"https://api.weixin.qq.com/sns/jscode2session?appid=%s&secret=%s&js_code=%s&grant_type=authorization_code",
					self::$appid, self::$appserect, $code
				);
		
		$request = new RequestCore($url);
		$request->ssl_verification = false;
		$request->set_method('GET');
		$request->send_request();
		
		$status = (int)$request->get_response_code();
		$body = $request->get_response_body();
		
		if ($status != 200 || !$body) {
			return [];
		}
		$body = json_decode($body, true);
		if (isset($body['errcode'])) {
			return [];
		}
		return $body;
	}
	
	/**
	 * 用session_key验证用户信息
	 * @param array $userInfo 小程序端获取，此处验证
	 */
	public static function verifyUserInfo($userInfo, $rawData, $signature, $encryptedData, $iv)
	{

	}
	
}