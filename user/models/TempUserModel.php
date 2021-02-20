<?php
namespace user\models;

use vendor\base\Model;

/**
 * 仅针对湘雅
 */
class TempUserModel extends Model
{
	const TEMP_RANDOM = 1;
	const TEMP_ILAB = 2;
	
	const TBL_ILAB_USER = 'user_ilab';

	const NAME = 'user_ilab';
	/**
	 * 仅学生
	 */
	public static function generateTempUser($user, $temp = self::TEMP_RANDOM, $ilabUserFromToken = null)
	{
		$temp = (int)$temp;
		
		if ($temp !== self::TEMP_RANDOM && $temp !== self::TEMP_ILAB) {
			return null;
		}
		
		$model = new UserModel();

		$res = $model->_set(null, [
				'school_id' => $user['school_id'],
				'group' => UserModel::GROUP_STUDENT,
				'username' => $user['username'],
				'password' => $user['password'],
				'realname' => $user['realname'],
		]);

		if ($res) {
			$tbl = TBL_USER;
			$sql = "UPDATE `{$tbl}` SET `is_temp`={$temp} WHERE `id`='{$res['id']}'";
            static::getDb()->execute($sql);
			
			if ($temp === self::TEMP_ILAB && $ilabUserFromToken) {
				self::setIlabUser($ilabUserFromToken, $res['id']);
			}
		}

		return $res;
	}
	
	/**
	 * 设置ilab用户
	 */
	protected static function setIlabUser($ilabUserFromToken, $user_id)
	{
		if (self::isIlabUser($user_id, $ilabUserFromToken['school_id'])) {
			return 0;
		}
		
		$db = static::getDb();

		$vals = [
				'user_id' => $user_id,
                'school_id' => $ilabUserFromToken['school_id'],
                'ilab_username' => $ilabUserFromToken['username'] ?? '',
				'ilab_id' => $ilabUserFromToken['id'],
				'ilab_un' => $ilabUserFromToken['un'],
				'ilab_dis' => $ilabUserFromToken['dis'],
				'created_at' => time()
		];

		$res = $db->insert($vals)->table(self::TBL_ILAB_USER)->result();
		
		return $res;
	}
	
	public static function isIlabUser($user_id, $school_id)
	{
		$exist = static::getDb()->select('user_id')->from(self::TBL_ILAB_USER)
		->where(['user_id' => $user_id, 'school_id' => $school_id])->result();
		
		return $exist ? true : false;
	}
	
	public static function getIlabUserByUserId($user_id, $school_id)
	{
		$res = static::getDb()->select()->from(self::TBL_ILAB_USER)
		->where(['user_id' => $user_id, 'school_id' => $school_id])->result();
		
		return $res ? $res[0] : null;
	}
	
	public static function getIlabUserByIlabId($ilab_id)
	{
		$res = static::getDb()->select()->from(self::TBL_ILAB_USER)
		->where(['ilab_id' => $ilab_id])->result();
		
		return $res ? $res[0] : null;
	}

    public static function getIlabUserByIlabUsername($ilab_username, $school_id)
    {
        $res = static::getDb()->select()->from(self::TBL_ILAB_USER)
            ->where(['ilab_username' => $ilab_username, 'school_id' => $school_id])->result();

        return $res ? $res[0] : null;
    }

    public static function clearWechat($user_id)
    {
        return static::getDb()->delete('user_wechat')->where(['user_id' => $user_id])->result();
    }

    public static function clearExam($record_id, $exam_id, $user_id)
    {
        $db = static::getDb();

        $record = $db->delete(TBL_EXAM_RECORD)->where(['exam_id' => $exam_id, 'user_id' => $user_id])->result();

        $paper = $db->delete('exam_paper_card')->where(['exam_id' => $exam_id, 'record_id' => $record_id])->result();

        $user_stats = $db->delete(TBL_EXAM_USER_STATS)->where(['exam_id' => $exam_id, 'user_id' => $user_id])->result();

        $res = [
            'record' => $record,
            'user_stats' => $user_stats,
            'paper' => $paper
        ];

        return  $res;
    }
}