<?php
namespace user\models;

use vendor\base\Model;

class SchoolAdminModel extends Model
{
	public function _list($school_id, $status = UserModel::STATUS_ACTIVE, $search = [], $size = self::PAGESIZE, $page = 0)
	{
		$res = [];
		/* @var $db \vendor\db\Db */
		$db = static::getDb();
	
		$this->search($search, ['username' => 'username', 'realname' => 'realname']);
		$query = $db->select([
				TBL_USER . '.id AS user_id', 'username', 'realname', 'gender', 
				TBL_USER . '.status', TBL_USER . '.created_at', TBL_USER . '.updated_at',
				TBL_SCHOOL . '.name AS school_name'])
		->from(TBL_USER)
		->join(TBL_SCHOOL, [TBL_USER . '.school_id' => TBL_SCHOOL . '.id'])
		->where(['group' => UserModel::GROUP_SCHOOL_ADMIN])
		->and_filter_where([TBL_USER . '.school_id' => $school_id, TBL_USER . '.status' => $status])
		->and_filter_where($search);
	
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
	
		$res = $db->select([TBL_USER . '.id AS user_id', 'school_id', 'group', 'username', 'realname', 'avatar', 'gender', TBL_SCHOOL . '.name AS school_name'])
		->from(TBL_USER)
		->join(TBL_SCHOOL, [TBL_USER . '.school_id' => TBL_SCHOOL . '.id'])
		->where([TBL_USER . '.id ' => $user_id])
		->and_filter_where(['school_id' => $school_id])
		->result();
	
		return $res;
	}
	
}