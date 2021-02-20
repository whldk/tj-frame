<?php
namespace login\models;

/**
 * 第三方用户登录接口定义
 */
Interface ThirdpartyUserInterface
{
	/**
	 * 用token获取第三方用户信息,token一般是临时的
	 */
	public function getUserByToken($token, $config);
	
	/**
	 * 将第三方用户信息存储到本地，并返回是否是新用户信息
	 */
	public function setUserToLocal($userInfo);
	
	/**
	 * 用本地用户唯一标志，获取第三方用户信息
	 */
	public static function getUserInfoByUserId($userId);
	
	/**
	 * 用第三方用户唯一标志，获取本地用户信息
	 */
	public static function getUserInfoByOpenId($openId);
	
	/**
	 * 两端用户绑定
	 */
	public static function bindUser($openId, $userId);
}