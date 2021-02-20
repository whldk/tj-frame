<?php
namespace user\models;

use vendor\base\IdentityInterface;
use vendor\base\AppTrait;

class IdentityModel implements IdentityInterface, \ArrayAccess
{
	use AppTrait;
	
	const AUTHKEY_LEN = 32;
	
	const STATUS_DELETED = 0;
	const STATUS_ACTIVE = 1;
	
	const TYPE_ADMIN = 0;
	const TYPE_USER = 1;
	
	const COOKIE_LIFETIME = 2592000;
	
	const GROUP_ADMIN = 0;
	const GROUP_SCHOOL_ADMIN = 1;
	const GROUP_TEACHER = 2;
	const GROUP_STUDENT = 3;
	
	protected static $groups = [
			self::GROUP_ADMIN,
			self::GROUP_SCHOOL_ADMIN,
			self::GROUP_TEACHER,
			self::GROUP_STUDENT
	];
	
	protected static $roles = [
			self::GROUP_ADMIN => 'admin',
			self::GROUP_SCHOOL_ADMIN => 'school_admin',
			self::GROUP_TEACHER => 'teacher',
			self::GROUP_STUDENT => 'student'
	];
	
	protected static $models = [
			self::TYPE_ADMIN => [
					'table' => TBL_ADMIN,
					'fields' => [
							'id',
							'group',
							'username',
							'password',
							'authkey',
							'realname',
					]
			],
			self::TYPE_USER => [
					'table' => TBL_USER,
					'fields' => [
							'id',
							'school_id',
							'group',
							'username',
							'password',
							'authkey',
							'realname',
							'gender',
							'avatar',
							'online_time',
							'last_ip',
                            'is_temp',
					]
			]
	];
	
	protected static $instance;
	
	protected $identity;
	protected $profile;
	protected $role;
	protected $id;
	protected $authkey;
	
	public static function getInstance()
	{
		return self::$instance;
	}
	
	protected function __construct($identity)
	{
		if (!$identity) {
			throw new \Exception('identity must be set');
		}
		
		$this->identity = $identity;
		
		$this->id = [$identity['id'], $identity['group']];
		
		$this->authkey = $identity['authkey'];
		
		unset($this->identity['authkey']);
	}
	
	public function profile()
	{
		if ($this->profile !== null) {
			return $this->profile;
		}
		
		if (!$this->identity) {
			return null;
		}
		$profile = $this->identity;
		unset($profile['authkey'], $profile['password']);
		
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
		switch ($this->identity['group']) {
			case self::GROUP_STUDENT :
				$class = $db->select([TBL_SCHOOL_CLASS . '.name AS class_name', TBL_SCHOOL_CLASS . '.id AS class_id'])
				->from(TBL_SCHOOL_CLASS)
				->join(TBL_STUDENT, [TBL_SCHOOL_CLASS . '.id' => TBL_STUDENT . '.class_id'])
				->where([TBL_STUDENT . '.user_id' => $profile['id']])->result();
				$profile += $class ? $class[0] : ['class_name' => '', 'class_id' => ''];
			case self::GROUP_SCHOOL_ADMIN :
			case self::GROUP_TEACHER :
				$school = $db->select(['logo', 'inside_logo', 'alias AS school_alias', 'name AS school_name'])
				->from(TBL_SCHOOL)->where(['id' => $profile['school_id']])->result();
				!$school ?: $profile += $school[0];
		}
		
		return $this->profile = $profile;
	}
	
	/**
	 * @param array $id ['xxxx', '1'], the first element is user_id, second one is group
	 * @return IdentityModel|null
	 */
	public static function findById($id)
	{
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
		
		list($user_id, $group) = $id;
		
		$type = self::getModelType($group);
		
		$identity = $db->select(self::$models[$type]['fields'])
		->from(self::$models[$type]['table'])
		->where(['id' => $user_id, 'group' => $group, 'status' => self::STATUS_ACTIVE])
		->result();
		
		if ($identity) {
			$identityClass = self::class;
			self::$instance = new $identityClass($identity[0]);
		} else {
			self::$instance = null;
		}
		
		return self::$instance;
	}
	
	public static function findByName($school_id, $username)
	{
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
		
		$identity = $school_id === null ? $db->select(self::$models[self::TYPE_ADMIN]['fields'])
		->from(self::$models[self::TYPE_ADMIN]['table'])
		->where(['username' => (string)$username, 'status' => self::STATUS_ACTIVE])->result()
			: $db->select(self::$models[self::TYPE_USER]['fields'])
		->from(self::$models[self::TYPE_USER]['table'])
		->where(['school_id' => (string)$school_id, 'username' => (string)$username, 'status' => self::STATUS_ACTIVE])->result();
		
		if ($identity) {
			$identityClass = self::class;
			self::$instance = new $identityClass($identity[0]);
		} else {
			self::$instance = null;
		}
		
		return self::$instance;
	}
	
	public function getId()
	{
		return $this->id;
	}
	
	public function getAuthkey()
	{
		/* @var $security \vendor\base\security */
		$security = \App::getInstance()->security;
		$this->authkey = $security->generateRandomString(self::AUTHKEY_LEN);
		
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
		$role = $this->getRole();
		if ($role === 'admin') {
			$db->update(TBL_ADMIN)->set(['authkey' => $this->authkey])->where(['id' => $this->id])->result();
		} else {
			$db->update(TBL_USER)->set(['authkey' => $this->authkey])->where(['id' => $this->id])->result();
		}
		
		return $this->authkey;
	}
	
	public function validateAuthKey($authKey)
	{
		if ($this->authkey === $authKey) {
			return true;
		}
	}
	
	public function getRole()
	{
		if (!$this->role) {
			if (!$this->identity) {
				$this->role = '?';
			} else {
				switch ($this->identity['group']) {
					case self::GROUP_ADMIN :
						$this->role = 'admin';
						break;
					case self::GROUP_SCHOOL_ADMIN :
						$this->role = 'school_admin';
						break;
					case self::GROUP_TEACHER :
						$this->role = 'teacher';
						break;
					case self::GROUP_STUDENT :
						$this->role = 'student';
						break;
				}
			}
		}
		
		return $this->role;
	}
	
	public function setExtraCookies()
	{
		if ($this->identity['group'] == self::GROUP_ADMIN) {
			return;
		}
		
		$profile = $this->profile();
		$cookies = \App::getInstance()->response->getCookies();
		$cookies->set([
				'name' => 'school_alias',
    			'expire' => time() + self::COOKIE_LIFETIME,
    			'value' => @$profile['school_alias'],
				'httpOnly' => false
		]);
		$cookies->set([
				'name' => 'username',
    			'expire' => time() + self::COOKIE_LIFETIME,
    			'value' => @$profile['username'],
				'httpOnly' => false
		]);
	}
	
	public function renewExtraCookies()
	{
		if ($this->identity['group'] == self::GROUP_ADMIN) {
			return;
		}
		
		$cookies = \App::getInstance()->request->getCookies();
		$school_alias = $cookies->get('school_alias');
		$username = $cookies->get('username');
		$school_alias['expire'] = $username['expire'] = time() + self::COOKIE_LIFETIME;
		$school_alias['httpOnly'] = $username['httpOnly'] = false;
		
		$cookies = \App::getInstance()->response->getCookies();
		$cookies->set($school_alias);
		$cookies->set($username);
	}
	
	public function delExtraCookies()
	{
		if ($this->identity['group'] == self::GROUP_ADMIN) {
			return;
		}
	
		$cookies = \App::getInstance()->response->getCookies();
		$cookies->del('school_alias');
		$cookies->del('username');
	}
	
	public function getClassId()
	{
		if (!$this->identity) {
			return [];
		}
		if (key_exists('class_id', $this->identity)) {
			return $this->identity['class_id'];
		}
	
		$this->identity['class_id'] = null;
	
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
	
		if ($this->identity['group'] == self::GROUP_TEACHER) {
			$class_id = $db->select(['class_id'])
			->from(TBL_USER_CLASS)
			->where(['user_id' => $this->identity['id']])
			->result();
			$this->identity['class_id'] = $class_id ? array_column($class_id, 'class_id') : [];
		} elseif ($this->identity['group'] == self::GROUP_STUDENT) {
			$class_id = $db->select(['class_id', 'is_main'])
			->from(TBL_STUDENT)
			->where(['user_id' => $this->identity['id']])
			->result();
			$this->identity['class_id'] = [];
			foreach ((array)$class_id as $v) {
				$this->identity['class_id'][] = $v['class_id'];
				if ($v['is_main'] == 1) {
					$this->identity['main_class'] = $v['class_id'];
				}
			}
		}
	
		return $this->identity['class_id'];
	}
	
	public function getMainClass()
	{
        if (!$this->identity) {
            return null;
        }
        if (key_exists('main_class', $this->identity)) {
            return $this->identity['main_class'];
		}
		
		$this->identity['main_class'] = null;

		/* @var $db \vendor\db\Db */
		$db = static::getDb();
		
		$class_id = $db->select('class_id')
		->from(TBL_STUDENT)
		->where(['user_id' => $this->identity['id'], 'is_main' => 1])
		->result();
		$this->identity['main_class'] = $class_id ? $class_id[0]['class_id'] : null;
		
		return $this->identity['main_class'];
	}

	public function getMainClassInfo()
    {
        if (!$this->identity) {
            return null;
        }
        if (key_exists('main_class', $this->identity)) {
            return $this->identity['main_class'];
        }

        $this->identity['main_class'] = null;

        /* @var $db \vendor\db\Db */
        $db = static::getDb();

        $class_id = $db->select(['class_id', 'name as class_name'])
            ->from(TBL_STUDENT)
            ->join(TBL_SCHOOL_CLASS, [TBL_STUDENT. '.class_id' => TBL_SCHOOL_CLASS. '.id'])
            ->where(['user_id' => $this->identity['id'], 'is_main' => 1])
            ->result();

        $this->identity['class_id'] = $class_id ? $class_id[0]['class_id'] : null;
        $this->identity['class_name'] = $class_id ? $class_id[0]['class_name'] : null;
        return ['class_id' => $this->identity['class_id'], 'class_name' => $this->identity['class_name']];
    }
	
	public function getGrade($class_id = null)
	{
		if (!$this->identity) {
			return $class_id === null ? [] : null;
		}
		
		if (key_exists('grade', $this->identity)) {
			if ($class_id === null) {
				return $this->identity['grade'];
			} else {
				return isset($this->identity['grade'][$class_id]) ? $this->identity['grade'][$class_id] : null;
			}
		}
		
		$this->identity['grade'] = null;
		
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
		
		if ($this->identity['group'] == self::GROUP_STUDENT) {
			$class_ids = $this->getClassId();
			if ($class_ids) {
				$classInfo = $db->select(['grade', 'id'])->from(TBL_SCHOOL_CLASS)
				->where(['id' => $class_ids])->result();
				$this->identity['grade'] = array_column((array)$classInfo, 'grade', 'id');
			}
		}
		
		if ($class_id === null) {
			return $this->identity['grade'];
		} else {
			return isset($this->identity['grade'][$class_id]) ? $this->identity['grade'][$class_id] : null;
		}
	}
	
	public function canAccessUsers($user_ids, $group = null)
	{
		$user_ids = array_unique((array)$user_ids);
		$group === null ?: $group = (int)$group;
		
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
		
		$role = $this->getRole();
		switch ($role) {
			case '?' : 
				return false;
			case 'admin' : 
				return true;
			case 'school_admin' : 
				$res = $db->select('id')->from(TBL_USER)
					->where([
						'school_id' => $this->identity['school_id'], 
						'id' => $user_ids
					])->and_filter_where(['group' => $group])
					->result();
				return count((array)$res) == count($user_ids);
			case 'teacher' : 
				$res = $db->select('id')->from(TBL_USER)
					->join(TBL_STUDENT, [TBL_STUDENT . '.user_id' => TBL_USER . '.id'])
					->join(TBL_USER_CLASS, [TBL_USER_CLASS . '.class_id' => TBL_STUDENT . '.class_id'])
					->where([
						TBL_USER . '.school_id' => $this->identity['school_id'],
						TBL_USER_CLASS . '.user_id' => $this->identity['id'],
						TBL_USER . '.id' => $user_ids
					])->result();
				return count((array)$res) == count($user_ids);
			case 'student' : 
				return count($user_ids) == 1 && $this->identity['id'] = $user_ids[0];
		}
	}
	
	public function canAccessClasses($class_ids)
	{
		$class_ids = array_values(array_unique((array)$class_ids));
		
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
		
		$role = $this->getRole();
		switch ($role) {
			case '?' :
				return false;
			case 'admin' :
				return true;
			case 'school_admin' :
				$res = $db->select('id')->from(TBL_SCHOOL_CLASS)
					->where([
						'school_id' => $this->identity['school_id'],
						'id' => $class_ids
					])->result();
				return count($res) === count($class_ids);
			case 'teacher' :
				$res = $db->select('class_id')->from(TBL_USER_CLASS)
					->where([
							'school_id' => $this->identity['school_id'],
							'user_id' => $this->identity['id'],
							'class_id' => $class_ids
					])->result();
				return count($res) === count($class_ids);
			case 'student' :
				return array_diff($class_ids, $this->getClassId()) === [];
		}
	}	
	
	protected static function getModelType($group)
	{
		return $group == self::GROUP_ADMIN ? self::TYPE_ADMIN : self::TYPE_USER;
	}
	
	public function offsetExists($offset) {
		return isset($this->identity[$offset]);
	}
	
	public function offsetGet($offset) {
		return isset($this->identity[$offset]) ? $this->identity[$offset] : null;
	}
	
	public function offsetSet($offset, $value) {}
	
	public function offsetUnset($offset) {}
	
	public function errors() {}
}