<?php
namespace login\models;

use vendor\base\Model;
use login\api\WechatApi;

class WechatUserModel extends Model implements ThirdpartyUserInterface
{
	const NAME = 'user_wechat';
	
	protected static $fields = [
			'user_id' => null,
			'openid' => null,
			'session_key' => null,
			'updated_at' => null,
	];
	
	public function getUserByToken($token, $config = [])
	{
        $model = new WechatApi($config);
		$res = $model::verifyToken($token);
		if (!$res) {
			return [];
		}
		return ['openid' => $res['openid'], 'session_key' => $res['session_key']];
		//@todo test
// 		return ['openid' => time() . mt_rand(1, 10), 'session_key' => time() . mt_rand(1, 10)];
	}
	
	/**
	 * @return bool 返回用户是否已经绑定
	 */
	public function setUserToLocal($userInfo)
	{
		$userInfo = array_intersect_key($userInfo, ['openid' => null, 'session_key' => null]);
		
		return $this->callInTransaction(function ($userInfo) {
			$isBinded = false;

			$db = static::getDb();
			
			$exist = $db->select('user_id')
				->from(self::NAME)
				->where(['openid' => $userInfo['openid']])
				->result();
			
			if ($exist) {
				$isBinded = !!$exist[0]['user_id'];
				$db->update(self::NAME)	
					->set(['updated_at' => time()] + $userInfo)
					->where(['openid' => $userInfo['openid']])
					->result();
			} else {
				$db->insert(['updated_at' => time()] + $userInfo)
					->table(self::NAME)
					->result();
			}
			return $isBinded;
		}, [$userInfo]);
	}

	public static function deleteBind($user_id)
    {
        $db = static::getDb();
        $res = $db->delete(self::NAME)->where(['user_id' => $user_id])->result();
        return $res;
    }


	public static function getUserInfoByUserId($userId)
	{
		$res = static::getDb()->select()
			->from(self::NAME)
			->where(['user_id' => $userId])
			->result();
		if ($res === null) {
			self::throwDbException();
		}
		return $res ? $res[0] : [];
	}
	
	public static function getUserInfoByOpenId($openId)
	{
		$res = static::getDb()->select()
		->from(self::NAME)
		->where(['openid' => $openId])
		->result();
		if ($res === null) {
			self::throwDbException();
		}
		return $res ? $res[0] : [];
	}
	
	public static function bindUser($openId, $userId)
	{
		$openId = (string)$openId;
		$userId = (string)$userId;
		
		$res = static::getDb()->update(self::NAME)
		->set(['user_id' => $userId])
		->where(['openid' => $openId])
		->result();
		
		if ($res === null) {
			self::throwDbException();
		}
		
		return $res;
	}

	public static function unBindUser($userId)
    {
        $userId = (string)$userId;

        $res = static::getDb()->delete(self::NAME)->where(['user_id' => $userId])->result();

        if ($res === null) {
            self::throwDbException();
        }

        return $res;
    }

}