<?php
namespace vendor\api;

use vendor\base\Logger;

class SpeechRecognitionApi
{
	public static $logFile = DIR_LOG . DIRECTORY_SEPARATOR . 'api.log';
	public static $enableLog = true;
	protected static $log;
	
	protected static $api = [
		'baidu' => [
			'appId' => '10104140',
			'apiKey' => 'G7QtobU2GCMBaeOxg0Dmb6nH',
			'secretKey' => 'bf61004ce6a06981819c30b070702865',
			'error' => [
				'3300' => '参数不正确',
				'3301' => '音频质量过差',
				'3302' => '鉴权失败',
				'3303' => 'api服务器出错',
				'3304' => '请求频率过高',
				'3305' => '日请求数超限',
				'3307' => 'api服务器识别出错',
				'3308' => '音频过长',
				'3309' => '音频数据不合法',
				'3310' => '音频文件过大',
				'3311' => '采样率参数错误',
				'3312' => '音频格式参数错误',
			],
			'userError' => [
				'3301' => '请检查音频录入是否正常',	//音频质量过差
				'3308' => '请将语音控制在60s以内',	//音频过长
			],
		],
	];
	
	protected $error;
	
	protected static function log($message, $type = 'info', $errno = null, $error = '')
	{
		if (!static::$enableLog) {
			return;
		}
		
		if (static::$log === null) {
			static::$log = new Logger([
					'logFile' => static::$logFile,
			]);
		}
		
		static::$log->log($message, $type, $errno, $error);
	}
	
	public function baidu($speech, $rate = 8000)
	{
		include_once 'vendor/aip_speech/AipSpeech.php';
		
		extract(self::$api['baidu']);
		$aipSpeech = new \AipSpeech($appId, $apiKey, $secretKey);
		$aipSpeech->setConnectionTimeoutInMillis(5000);
		$aipSpeech->setSocketTimeoutInMillis(5000);
		
		$res = $aipSpeech->asr(@file_get_contents($speech), 'pcm', $rate, ['lan' => 'zh']);
		
		if (!isset($res['err_no']) || isset($res['error_code'])) {
			return null;
		} if ($res['err_no']) {
			$this->error = ['err_no' => $res['err_no'], 'err_msg' => $error[$res['err_no']]];
			self::log(__METHOD__ . ':' . $error[$res['err_no']], 'api_error', $res['err_no'], $res['err_msg']);
			return null;
		} else {
			return (array)$res['result'];
		}
	}
	
	public function error()
	{
		return $this->error;
	}
}