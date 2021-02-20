<?php
namespace user\models;

use vendor\base\ValidateModel;

class UserClassModel extends ValidateModel
{
	const NAME = 'user_class';
	
	protected static $fields = [
		'school_id' => null,
		'user_id' => null,
		'class_id' => null,
		'created_at' => null
	];
	
	protected static $filters = [
			'before' => [
					'ts' =>  ['ct' => 'created_at'],
			]
	];
	
	protected static $validates = [
		'exist' => [
				'user_id' => [
						'table' => TBL_USER,
						'target_fields' => ['user_id' => 'id', 'school_id' => 'school_id'],
						'condition' => ['group' => UserModel::GROUP_TEACHER]
				],
				'class_id' => [
						'table' => TBL_SCHOOL_CLASS,
						'target_fields' => ['class_id' => 'id', 'school_id' => 'school_id']
				]
		]
	];
	
	protected static $sets = [
			'id' => ['school_id', 'class_id', 'user_id']
	];
	
	public function _set($school_id, $class_id, $user_id, $vals = [])
	{
		$pack = compact(self::$sets['id']);
		return $this->internal_set($pack, $vals);
	}
	
	public function _get($school_id, $user_id)
	{
		$res = static::getDb()->select(['class_id', 'name'])->from(self::NAME)
			->join(TBL_SCHOOL_CLASS, [self::NAME . '.class_id' => TBL_SCHOOL_CLASS. '.id'], 'RIGHT JOIN')
			->where([self::NAME . '.school_id' => $school_id, 'user_id' => $user_id])
			->result();
		return $res;
	}
	
	public static function access_class($school_id, $user_id)
	{
		$res = static::getDb()->select('class_id')->from(self::NAME)
			->where(['school_id' => $school_id, 'user_id' => $user_id])->result(); 
		return $res ? array_column($res, 'class_id') : [];
	} 
}