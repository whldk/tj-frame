<?php
namespace user\models;

use vendor\base\Model;

class LoginModel extends Model
{
	const WINDOW_SIZE = 80;

	public function login($school_alias, $username, $password, $remember = 0)
	{
		$username = (string)$username;
		$password = (string)$password;
		$remember = $remember ? true : false;
	
		if ($school_alias) {
			$school = SchoolModel::getByAlias($school_alias);
			if (!$school) {
				return $this->addError('school_alias 学校别称不存在');
			}
			$school_id = $school[0]['id'];
		} else {
			$school_id = null;
		}
	
		$identity = IdentityModel::findByName($school_id, $username);

		if (!$identity) {
			return $this->addError('username 用户不存在');
		}
		if (!$this->verify_password($password, $identity['password'])) {
			return $this->addError('password 密码错误');
		}
		
		return $this->user->login($identity, $remember);
	}
	
	public function loginByUserId($user_id, $group, $remember = 0)
	{
		$identity = IdentityModel::findById([$user_id, $group]);
	
		if (!$identity) {
			return $this->addError('user_id 用户不存在');
		}
	
		return $this->user->login($identity, $remember);
	}
	
	public static function verify_password($password, $hash)
	{
	    if (UN_PWD) {
            return password_verify($password, $hash);
        } else if (self::decrypt_password($password)) {
			return password_verify($password, $hash);
		}
		return false;
	}
	
	public static function decrypt_password(&$password)
	{
		$prKey = file_get_contents(RSA_PR_KEY);
		if (!openssl_private_decrypt(base64_decode(str_replace(' ', '+', $password)), $password, $prKey)) {
			return false;
		}
		
		$password = json_decode($password, true);
		if (count($password) === 3 && isset($password[0], $password[1], $password[2])) {
			$window = abs(time() - $password[0]);
			if ($window > self::WINDOW_SIZE) {
				return false;
			}
			
			$session = static::getSession();
			$ts = $session->get('ts');
			$nonce = $session->get('nonce');
			if ($ts != $password[0] || $nonce != $password[1]) {
				return false;
			}
			$password = $password[2];
			return true;
		}  
		
		return false;
	}
}