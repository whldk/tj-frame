<?php
namespace user\models;

use vendor\base\ValidateModel;

class SchoolModel extends ValidateModel
{
	const NAME = 'school';
	
	const STATUS_DELETED = 0;
	const STATUS_ACTIVE = 1;
	
	protected static $fields = [
			'id' => null,
            'pinyin' => null,
			'name' => null,
			'alias' => null,
			'site_name' => '',
			'login_logo' => null,
			'logo' => null,
			'inside_logo' => null,
			'bg' => null,
			'banner' => null,
			'server' => null,
			'declaration_doc' => null,
			'declaration_video' => null,
			'show_declaration' => 0,
			'site_introduction' => '',
			'project_name' => null,
			'status' => self::STATUS_ACTIVE,
			'contact_person' => null,
			'contact_phone' => null,
			'contact_addr' => null,
			'contact_email' => null,
			'login_num' => 0
	];
	
	protected static $sets = [
			'auto_inc' => '_id',
			'hash_id' => 'id',
			'id' => ['id'],
	];
	
	protected static $filters = [
			'before' => [
					'i' => ['status', 'login_num'],
					's' => ['name', 'alias', 'site_name', 'server', 'contact_person', 'contact_phone', 'contact_addr', 'contact_email'],
					'html' => ['site_introduction','project_name'],
					'img' => ['login_logo', 'logo', 'inside_logo', 'bg', 'banner'],
					'b' => ['show_declaration'],
			]
	];
	
	protected static $validates = [
			'require' => ['name', 'alias', 'login_num'],
			'repeat' => [['alias'], ['name']],
			'string' => [
					'name' => ['min' => 0, 'max' => 255, 'truncate' => false],
					'alias' => ['min' => 0, 'max' => 255, 'truncate' => false],
					'site_name' => ['min' => 0, 'max' => 255, 'truncate' => false],
					'server' => ['min' => 0, 'max' => 255, 'truncate' => false],
					'site_introduction' => ['min' => 0, 'max' => 600, 'truncate' => false],
					'project_name' => ['min' => 0, 'max' => 300, 'truncate' => false],
					'contact_person' => ['min' => 0, 'max' => 255, 'truncate' => false],
					'contact_phone' => ['min' => 0, 'max' => 255, 'truncate' => false],
					'contact_addr' => ['min' => 0, 'max' => 255, 'truncate' => false],
					'contact_email' => ['min' => 0, 'max' => 255, 'truncate' => false],
			]
	];
	
	protected static $constraints = [
			['id' => ['table' => TBL_USER, 'target_fields' => ['id' => 'school_id']]],
			['id' => ['table' => TBL_SCHOOL_CLASS, 'target_fields' => ['id' => 'school_id']]]
	];

    public static function _category()
    {
        $res = self::_select(['status' => self::STATUS_ACTIVE], ['id', 'name', 'alias']);
        return $res;
    }

	public static function _list($search, $order, $size = null, $page = 0, $fields = [])
	{
		$res = [];
		self::search($search, ['name' => 'name', 'alias' => 'alias', 'pinyin' => 'pinyin']);

        $db = static::getDb();

        $query = $db->select($fields ? $fields : array_keys(self::$fields))
            ->from(self::NAME)->where(['status' => self::STATUS_ACTIVE])->and_filter_where($search);

        self::order($order, [
            'created_at' => '_id'
        ]);

        if ($order === []) {
            $order = [['pinyin', 'asc']];
        }

		if ($size !== null) {
			$offset = static::page($query, $page, $size, $res);
			$query->orderby($order)->limit($offset, $size);
		}

		if (!isset($res['_list'])) {
			$res['_list'] = $query->orderby($order)->result();
		}
		
		return $res;
	}
	
	public static function _get($id, $fields = null)
	{
		$fields ?: $fields = self::getFields();
		$res = static::getDb()->select($fields)->from(self::NAME)->where(['id' => $id])->result();
		if ($res === null) {
			self::throwDbException();
		}
		return $res;
	}
	
	public static function one($id, $fields = null)
	{
		$fields ?: $fields = self::getFields();
		$res = self::_select(['id' => $id], $fields, 1);
		return $res;
	}
	
	public static function getByAlias($alias)
	{
		$res = static::getDb()->select(array_keys(self::$fields))->from(self::NAME)->where(['alias' => (string)$alias])->result();
		return $res;
	}
	
	public function _set($id, $vals = [])
	{
		$pack = ['id' => $id];
		return $this->internal_set($pack, $vals);
	}
	
}