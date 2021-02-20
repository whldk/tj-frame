<?php
namespace login\models;

use vendor\base\ValidateModel;
use vendor\hashids\Hashids;
use vendor\exceptions\ServerErrorException;

/**
 * 用于生成和管理esp客户端凭证
 */
class TokenModel extends ValidateModel
{
	const NAME = 'token';
	
	const EXPIRE_SECONDS = 120;

	protected static $sets = [
			'auto_inc' => '_id',//自增id
			'id' => ['code'],	//主键
	];
	
	protected static $fields = [
			'code' => '',		//token码
			'created_at' => 0,	//生成时间
			'expired_at' => 0,	//过期时间
			
			'user_id' => '',	//获得授权的同时，写入用户信息，以便后面esp请求时用此信息登录
			'authed_at' => 0,	//授权时间
	];
	
	protected static $filters = [
			'before' => [
					'ts' => ['ct' => 'created_at'],
					'ignore' => ['expired_at', 'user_id', 'authed_at'],
					
			],
			'after' => [
					'map' => [
							[
									['expired_at'],
									'callback' => [self::class, 'initExpiredAt'],
									'args' => ['created_at'],
							],
					],
			],
	];
	
	public static function initExpiredAt($created_at)
	{
		return $created_at + self::EXPIRE_SECONDS;
	}
	
	protected static function generateCode(int $auto_inc)
	{
		$hashids = new Hashids(md5(static::NAME), 4);
		return $hashids->encode($auto_inc);
	}
	
	protected function updates_of_insert($fields, $pack, $_id = null)
	{
		$_id = static::getDb()->get_last_insert_id();
		if (!$_id) {
			throw new ServerErrorException();
		}
		$code = self::generateCode($_id);
		return ['code' => $code];
	}
	
	protected function return_of_insert($fields, $pack, $_id = null)
	{
		return ['code' => $fields['code']];
	}
	
	public function _set()
	{
		return $this->internal_set([], []);
	}
	
	/**
	 * 授权登录
	 * @param string $user_id
	 * @param string $code
	 * @return NULL|number
	 */
	public function authorize(string $user_id, string $code)
	{
		$token = self::one($code);
		if (!$token) {
			return $this->addError('code', self::ERR_VALID, 'token不存在');
		}
		if ($token['expired_at'] < time()) {
			return $this->addError('code', self::ERR_VALID, 'token已失效');
		}
		if ($token['user_id']) {
			return $this->addError('code', self::ERR_VALID, 'token无效');
		}
		$res = self::_update(['code' => $code], ['user_id' => $user_id, 'authed_at' => time()]);
		return $res;
	}
	
	public static function one(string $code)
	{
		$token = self::_select(['code' => $code], self::getFields(), 1);
		return $token;
	}
}