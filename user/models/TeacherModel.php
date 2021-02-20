<?php
namespace user\models;

use vendor\base\Model;
class TeacherModel extends Model
{
    public function _category($school_id)
    {
        /* @var $db \vendor\db\Db */
        $db = static::getDb();
        $res = $db->select(['id AS user_id', 'username', 'realname', 'gender'])->from(TBL_USER)
            ->where([
                'school_id' => $school_id,
                'group' => UserModel::GROUP_TEACHER,
                'status' => UserModel::STATUS_ACTIVE
            ])
            ->orderby([[TBL_USER . '._id', 'desc']])
            ->result();
        return $res;
    }

	public function _list($school_id, $status = self::STATUS_ACTIVE,  $search = [], $size = self::PAGESIZE, $page = 0)
	{
		$res = [];
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
		
		$this->search($search, ['username' => 'username', 'realname' => 'realname']);
		$query = $db->select([
				'id AS user_id', 'username', 'realname', 'gender',
				TBL_USER . '.status', 'created_at', 'updated_at',
		])->from(TBL_USER)
		->where(['school_id' => $school_id, 'group' => UserModel::GROUP_TEACHER])
		->and_filter_where($search)
		->and_filter_where(['status' => $status]);
		
		$offset = static::page($query, $page, $size, $res);
		$query->limit($offset, $size);
			
		if (!isset($res['_list'])) {
			$res['_list'] = $query->orderby([[TBL_USER . '._id', 'desc']])->result();
		}
		
		return $res;
	}
	
	public function _get($school_id, $user_id)
	{
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
	
		$res = $db->select([TBL_USER . '.id AS user_id', 'school_id', 'group', 'username', 'gender', 'realname', 'avatar', TBL_SCHOOL . '.name AS school_name'])
		->from(TBL_USER)
		->join(TBL_SCHOOL, [TBL_USER . '.school_id' => TBL_SCHOOL . '.id'])
		->where([TBL_USER . '.id' => $user_id])
		->and_filter_where(['school_id' => $school_id])
		->result();
	
		return $res;
	}
	
	public static function filterTeachers($teacher_ids)
	{
		$db = static::getDb();
		
		$res = $db->select(['id'])
		->from(TBL_USER)
		->where([
				'id' => $teacher_ids,
				'group' => UserModel::GROUP_TEACHER,
				'status' => UserModel::STATUS_ACTIVE
		])->result();
		
		return $res ? array_column($res, 'id') : [];
	}
	
}