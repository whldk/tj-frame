<?php
namespace user\models;

use vendor\base\ValidateModel;
use vendor\base\Upload;

class UserModel extends ValidateModel
{
	const NAME = 'user';
	
	const INIT_PASSWD = '123456';
	
	const FILE_PRIVATE_KEY = 'rsa_private_key.pem';
	
	const GROUP_SCHOOL_ADMIN = 1;
	const GROUP_TEACHER = 2;
	const GROUP_STUDENT = 3;
	
	const STATUS_DELETED = 0;
	const STATUS_ACTIVE = 1;
	
	const G_FEMALE = 0;
	const G_MALE = 1;
	const G_UNKNOWN = 2;
	
	public static $groups = [
			self::GROUP_SCHOOL_ADMIN,
			self::GROUP_TEACHER,
			self::GROUP_STUDENT,
	];
	
	public static $statuses = [
			self::STATUS_ACTIVE, 
			self::STATUS_DELETED
	];
	
	protected static $fields = [
			'id' => null,
			'school_id' => null,
			'group' => null,
			'username' => null,
			'iusername' => null,
			'password' => null,
			'realname' => null,
			'gender' => self::G_UNKNOWN,
			'avatar' => null,
			'status' => self::STATUS_ACTIVE,
			'created_at' => null,
			'updated_at' => null,
			
			'online_time' => 0,
			'last_ip' => '',
	];
	
	protected static $extraFields = [
			'authkey' => null,
	];
	
	protected static $sets = [
			'auto_inc' => '_id',
			'hash_id' => 'id',
			'id' => ['id'],
	];
	
	protected static $filters = [
			'before' => [
				's' => ['username', 'real_name'],
				'ts' =>  ['ct' => 'created_at', 'mt' => 'updated_at'],
//				'map' => [[['password'], 'callback' => ['user\models\UserModel', 'openssl_private_decrypt']]],
				'ignore' => ['avatar', 'online_time', 'last_ip'],
			],
			'after' => [
				'map' => [
						[
								['password'], 
								'callback' => ['user\models\UserModel', 'generate_password_hash']
						],
						[
								['iusername'], 
								'callback' => ['user\models\UserModel', 'generate_iusername'],
								'args' => ['username'],
						],
				],
			]
	];
	
	protected static $constraints = [
			['id' => ['table' => TBL_STUDENT, 'target_fields' => ['id' => 'user_id']]],
			['id' => ['table' => TBL_USER_CLASS, 'target_fields' => ['id' => 'user_id']]]
	];
	
	protected static $cascades = [];

	protected static $validates = [];
	
	public static function validates()
	{
// 		if (!self::$validates) {
// 			self::$validates = [
		return [
					'require' => ['school_id', 'username', 'password', 'realname', 'group'],
					'readonly' => ['school_id', 'username', 'group'],
					'range' => [
							'status' => self::$statuses,
							'group' => self::$groups,
							'gender' => [self::G_FEMALE, self::G_MALE, self::G_UNKNOWN],
					],
					'regular' => [
							'username' => '/^[a-zA-Z0-9_]+$/',
							'password' => '/^[a-zA-Z0-9!#$%&\'*+\\\\\/=?^_`{|}~\-:\.@()\[\]";,]+$/'
					],
					'string' => [
							'username' => ['min' => 2, 'max' => 255, 'truncate' => false],
							'password' => ['min' => 6, 'max' => 255, 'truncate' => false],
							'realname' => ['min' => 1, 'max' => 255, 'truncate' => false]
					],
					'exist' => [
							'school_id' => ['table' => TBL_SCHOOL, 'target_fields' => ['school_id' => 'id']],
					],
					'repeat' => [['username', 'school_id']],
		];
// 			];
// 		}
// 		self::orderValidates(self::$validates);
	}
	
	public static function batchInsertValidates()
	{
		return [
					'require' => ['school_id', 'username', 'password', 'realname', 'group'],
					'regular' => [
							'username' => '/^[a-zA-Z0-9_]+$/',
							'password' => '/^[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~\-:\.@()\[\]";,]+$/'
					],
					'string' => [
							'username' => ['min' => 2, 'max' => 255, 'truncate' => false],
							'password' => ['min' => 6, 'max' => 255, 'truncate' => false],
							'realname' => ['min' => 1, 'max' => 255, 'truncate' => false]
					],
                    'repeat' => [['username', 'school_id']],
			];
	}
	
	public function _list($school_id, $group = null, $status = self::STATUS_ACTIVE, $search = [], $size = self::PAGESIZE, $page = 0)
	{
		$res = [];
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
		
		if ($status == self::STATUS_ACTIVE) {
			$where_status = [
					self::NAME . '.status' => $status,
					TBL_SCHOOL . '.status' => $status,
			];
		} elseif ($status == self::STATUS_DELETED) {
			$where_status = [
					'or',
					self::NAME . '.status' => $status,
					TBL_SCHOOL . '.status' => $status,
			];
		} else {
			$where_status = [];
		}
	
		$this->search($search, ['username' => 'username', 'realname' => 'realname', 'school_name' => TBL_SCHOOL . '.name']);
		$fields = [self::NAME . '.id', 'username', 'realname', 'group', TBL_SCHOOL . '.name AS school_name'];
		$query = $db->select($fields)
		->from(TBL_USER)
		->join(TBL_SCHOOL, [TBL_USER . '.school_id' => TBL_SCHOOL . '.id'])
		->and_filter_where(['school_id' => $school_id])
		->and_filter_where(['group' => $group])
		->and_filter_where($where_status)
		->and_filter_where($search);
	
		$offset = static::page($query, $page, $size, $res);
		$query->limit($offset, $size);
			
		if (!isset($res['_list'])) {
			$res['_list'] = $query->orderby([[self::NAME .'._id', 'desc']])->result();
		}
	
		return $res;
	}
	
	public static function _get($id, $fields = null, $noSensitiveFields = true)
	{
		$fields ?: $fields = self::getFields();
		$res = static::getDb()->select($fields)
			->from(self::NAME)->where(['id' => $id])->result();
		if ($res === null) {
			self::throwDbException();
		}
		if ($res && $noSensitiveFields) {
			unset($res[0]['password'], $res[0]['authkey']);
		} 
		return $res;
	}

	protected $inBatchContext = false;

	public function set_batch_context()
	{
		if (!$this->inBatchContext) {
			//切换主key
			self::$sets['id'] = ['school_id', 'username', 'group'];
			//不解密password
			self::$filters['before']['map'] = [];
			$this->inBatchContext = true;
		}
	
		return $this->inBatchContext;
	}
	
	public function reset_batch_context()
	{
		if ($this->inBatchContext) {
			//恢复主key
			self::$sets['id'] = ['id'];
			//恢复before filers的password部分
			self::$filters['before']['map'][] = [['password'], 'callback' => ['user\models\UserModel', 'openssl_private_decrypt']];
			$this->inBatchContext = false;
		}
	
		return !$this->inBatchContext;
	}
	
	public function validate($exec, $fields, &$vals)
	{
		//拦截验证
		$validates = $this->inBatchContext ? self::batchInsertValidates() : self::validates();
		return $this->internal_validate($validates, $exec, $fields, $vals);
	}
	
	public function _set($id, $vals = [])
	{
		//设置上下文
		$this->reset_batch_context();
	
		$pack = compact(self::$sets['id']);
		$res = $this->internal_set($pack, $vals);
	
		return $res;
	}
	
	public function batch_set($school_id, $username, $group, $vals)
	{
		//设置上下文
		$this->set_batch_context();
		
		$pack = compact(self::$sets['id']);
		$res = $this->internal_set($pack, $vals);
		
		return $res;
	}
	
	protected function internal_update($pack, $snapshot, $vals)
	{
		$res = parent::internal_update($pack, $snapshot, $vals);
		//主key主要是会根据是单个还是批量的情况变化，更新的时候批量上下文需返回id
		return $this->inBatchContext && $res !== null ? ['id' => $snapshot['id']] : $res;
	}
	
	public function set_avatar($id, $zoom, $x, $y, $w, $h)
	{
		$db = static::getDb();
	
		//先上传头像
		$avatar = ['avatar' => null];
		if ($this->upload($avatar, ['image/jpeg', 'image/png'], [], [], [], true, true)) {
			$fileInfo = Upload::getFiles('avatar');
			if ($fileInfo['type'] === 'image/jpeg') {
				$type = 'jpeg';
				$imgcreateFunc = 'imagecreatefromjpeg';
				$imgoutputFunc = 'imagejpeg';
				$quality = 90;
			} else {
				$type = 'png';
				$imgcreateFunc = 'imagecreatefrompng';
				$imgoutputFunc = 'imagepng';
				$quality = 8;
			}
		} else {
			return null;
		}
		$imgFile = Upload::urlToFile($avatar['avatar']);
	
		try {
			//获取图片信息并打开图片
			if (!($imgInfo = getimagesize($imgFile)) || !($imgHandler = $imgcreateFunc($imgFile))) {
				$this->addError('avatar 非法');
				throw new \vendor\exceptions\UnknownException();
			}
				
			//处理缩放
			list($weight, $height) = $imgInfo;
			if ($zoom != 1) {
				$zoomedWidth = $weight * $zoom;
				$zoomedHeight = $height * $zoom;
				$zoomedImgHandler = imagecreatetruecolor($zoomedWidth, $zoomedHeight);
				if (!$zoomedImgHandler || !imagecopyresampled($zoomedImgHandler, $imgHandler, 0, 0, 0, 0, $zoomedWidth, $zoomedHeight, $weight, $height)) {
					throw new \vendor\exceptions\UnknownException();
				}
				imagedestroy($imgHandler);
				$imgHandler = $zoomedImgHandler;
			}
				
			//裁剪150x150,100x100,50x50
			$imgHandler150 = imagecreatetruecolor(150, 150);
			$imgHandler100 = imagecreatetruecolor(100, 100);
			$imgHandler50 = imagecreatetruecolor(50, 50);
			if (!$imgHandler150 || !$imgHandler100 || !$imgHandler50) {
				throw new \vendor\exceptions\UnknownException();
			}
			if (!imagecopyresampled($imgHandler150, $imgHandler, 0, 0, $x, $y, 150, 150, $w, $h)
					|| !imagecopyresampled($imgHandler100, $imgHandler150, 0, 0, 0, 0, 100, 100, 150, 150)
					|| !imagecopyresampled($imgHandler50, $imgHandler100, 0, 0, 0, 0, 50, 50, 100, 100)) {
				throw new \vendor\exceptions\UnknownException();
			}
				
			//输出到文件
			$dotPos = strrpos($imgFile, '.');
			$part1 = substr($imgFile, 0, $dotPos);
			$part2 = substr($imgFile, $dotPos);
			$imgFile150 =  $part1 . '150' . $part2;
			$imgFile100 =  $part1 . '100' . $part2;
			$imgFile50 =  $part1 . '50' . $part2;
			$imgoutputFunc($imgHandler150, $imgFile150, $quality);
			$imgoutputFunc($imgHandler100, $imgFile100, $quality);
			$imgoutputFunc($imgHandler50, $imgFile50, $quality);
				
			//处理旧头像，并更新
			$old_avatar = self::_get($id, ['avatar']);
			$res = static::getDb()->update(self::NAME)
			->set($avatar)
			->where(['id' => $id])
			->result();
				
			//更新成功之后删除无用的图片文件
			if ($res && $old_avatar && $old_avatar[0]['avatar']) {
				$oldImageFile = Upload::urlToFile($old_avatar[0]['avatar']);
				$dotPos = strrpos($oldImageFile, '.');
				$part1 = substr($oldImageFile, 0, $dotPos);
				$part2 = substr($oldImageFile, $dotPos);
				unlink($part1 . '150' . $part2);
				unlink($part1 . '100' . $part2);
				unlink($part1 . '50' . $part2);
			}
				
			//删除原始上传图片
			unlink($imgFile);
		} catch (\Exception $e) {
			//删除原始上传图片
			unlink($imgFile);
			return null;
		}
	
		return $avatar['avatar'];
	}
	
	protected function get_exec($pack, $vals)
	{
		list($exec, $fields) = parent::get_exec($pack, $vals);
		if (!isset($vals['password'])) {
			unset($fields['password']);
		} else {
			$fields['password'] = null;
		}
		
		return [$exec, $fields];
	}
	
	public function deactive($ids)
	{
		$res = static::getDb()->update(self::NAME)
		->set(['status' => self::STATUS_DELETED])->where(['id' => $ids])->result();
		return $res;
	}
	
	public static function generate_password_hash($password)
	{
// 		return password_hash($password, PASSWORD_DEFAULT, ['cost' => 13]);
		return password_hash($password, PASSWORD_DEFAULT);
	}
	
	public static function generate_iusername($username)
	{
		return strtolower($username);
	}
	
	public static function openssl_private_decrypt($password)
	{
		$prKey = file_get_contents(RSA_PR_KEY);
		//ignore the openssl_private_decrypt result, when result is false, $password will be the same, it makes sense here
		if (!openssl_private_decrypt(base64_decode(str_replace(' ', '+', $password)), $password, $prKey)) {
			return false;
		}
		
		$password = json_decode($password, true);
		if (count($password) === 3 && isset($password[0], $password[1], $password[2])) {
			$window = abs(time() - $password[0]);
			if ($window > 120) {
				return false;
			}
				
			$session = static::getSession();
			$ts = $session->get('ts');
			$nonce = $session->get('nonce');
			if ($ts != $password[0] || $nonce != $password[1]) {
				return false;
			}
			$password = $password[2];
			return $password;
		}
		
		return false;
	}
}