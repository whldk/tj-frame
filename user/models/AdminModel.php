<?php
namespace user\models;

use vendor\base\ValidateModel;
use vendor\base\Upload;

class AdminModel extends ValidateModel
{
	const NAME = 'admin';
	
	const STATUS_DELETED = 0;
	const STATUS_ACTIVE = 1;
	
	const GROUP_ADMIN = 0;
	
	public static $statuses = [self::STATUS_ACTIVE, self::STATUS_DELETED];
	
	protected static $fields = [
			'id' => null,
			'group' => self::GROUP_ADMIN,
			'username' => null,
			'password' => null,
			'realname' => null,
			'status' => self::STATUS_ACTIVE,
			'created_at' => null,
			'updated_at' => null
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
				'ignore' => [['group'], 'when' => []],
				//'map' => [['password'], 'callback' => ['user\models\AdminModel', 'openssl_private_decrypt']],
				'ignore' => ['avatar'],
			],
			'after' => [
				'map' => [['password'], 'callback' => ['user\models\AdminModel', 'generate_password_hash']]
			]
	];
	
	protected static $validates = [];
	
	public static function validates()
	{
		if (!self::$validates) {
			self::$validates = [
					'require' => ['username', 'password', 'realname'],
					'readonly' => ['username'],
					'regular' => [
							'username' => '/^[a-zA-Z0-9_]+$/',
							'password' => '/^[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~\-:\.@()\[\]";,]+$/'
					],
					'string' => [
							'username' => ['min' => 2, 'max' => 255, 'truncate' => false],
							'password' => ['min' => 6, 'max' => 255, 'truncate' => false],
							'realname' => ['min' => 1, 'max' => 255, 'truncate' => false]
					],
					'repeat' => ['username'],
					'range' => [
							'status' => self::$statuses,
					]
			];
			self::orderValidates(self::$validates);
		}
		return self::$validates;
	}
	
	public function _get($id)
	{
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
		
		$res = $db->select(['id', 'username', 'realname', 'status'])
		->from(self::NAME)->where(['id' => $id])->result();
		
		return $res;
	}
	
	public function _list($status = null, $search = [], $size = null, $page = 0)
	{
		$res = [];
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
		
		$this->search($search, ['username' => 'username', 'realname' => 'realname']);
		$query = $db->select(['id', 'username', 'realname', 'status'])
			->from(self::NAME)
			->and_filter_where(['status' => $status])
			->and_filter_where($search);
        $offset = static::page($query, $page, $size, $res);
        if (!isset($res['_list'])) {
        	$query->limit($offset, $size);
        	$res['_list'] = $query->orderby([['_id', 'desc']])->result();
        }
		
        return $res;
	}
	
	public function _set($id, $vals = [])
	{
		$res = $this->internal_set(['id' => $id], $vals);
		return $res;
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
		$imgFile = Upload::UrlToFile($avatar['avatar']);
	
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
		return password_hash($password, PASSWORD_DEFAULT);
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