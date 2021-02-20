<?php
namespace user\models;

use esp_admin\models\ExamineScoreModel;
use esp_admin\models\ExperimentDetailModel;
use esp_admin\models\ExperimentRecordModel;
use esp_admin\models\ExperimentStatModel;
use exam\models\ExamModel;
use exam\models\ExamRecordModel;
use exam\models\ExamUserStatsModel;
use exam\models\PaperCardModel;
use experiment\models\MessageModel;
use vendor\base\ValidateModel;

class StudentModel extends ValidateModel
{
    const NAME = 'student';

    protected static $sets = [
        'id' => ['school_id', 'class_id', 'user_id'],    //one user => multiple class
    ];

    protected static $fields = [
        'school_id' => null,
        'class_id' => null,
        'user_id' => null,
        'is_main' => 0,
    ];

    protected static $filters = [
        'before' => [
            'b' => ['is_main'],
            's' => ['school_id', 'class_id', 'user_id'],
        ],
    ];

    protected static $validates = [];

    public static function validates()
    {
        if (!self::$validates) {
            self::$validates = [
                'exist' => [
                    'user_id' => [
                        'table' => TBL_USER,
                        'target_fields' => [
                            'user_id' => 'id',
                            'school_id' => 'school_id'
                        ],
                        'condition' => [
                            'group' => UserModel::GROUP_STUDENT
                        ]
                    ],
                    'class_id' => [
                        'table' => TBL_SCHOOL_CLASS,
                        'target_fields' => [
                            'class_id' => 'id',
                            'school_id' => 'school_id'
                        ],
                    ]
                ],
                'range' => [
                    'is_main' => [1],    //只能设置主班级，不能取消设置
                ],
            ];
            self::orderValidates(self::$validates);
        }
        return self::$validates;
    }

    public function _list($school_id, $class_id, $status = self::STATUS_ACTIVE, $search = [], $size = self::PAGESIZE, $page = 0)
    {
        $res = [];
        $db = static::getDb();

        self::search($search, ['username' => 'username', 'realname' => 'realname', 'grade' => TBL_SCHOOL_CLASS . '.grade%']);

        $fields = [
            TBL_USER . '.id AS user_id', 'username', 'realname', 'gender',
            TBL_USER . '.status', TBL_USER . '.created_at', TBL_USER . '.updated_at',
            TBL_STUDENT . '.class_id',
            TBL_STUDENT . '.is_main',
            TBL_SCHOOL_CLASS . '.grade',
            TBL_SCHOOL_CLASS . '.name AS class_name',
        ];

        $query = $db->select($fields)
            ->from(TBL_USER)
            ->join(TBL_STUDENT, [TBL_USER . '.id' => TBL_STUDENT . '.user_id'])
            ->join(TBL_SCHOOL_CLASS, [TBL_STUDENT . '.class_id' => TBL_SCHOOL_CLASS . '.id'])
            ->where($class_id === null ? [TBL_USER . '.school_id' => $school_id, 'group' => UserModel::GROUP_STUDENT]
                : [TBL_USER . '.school_id' => $school_id, 'group' => UserModel::GROUP_STUDENT, 'class_id' => $class_id])
            ->and_filter_where([TBL_USER . '.status' => $status])
            ->and_filter_where($search);

        $offset = static::page($query, $page, $size, $res);
        $query->limit($offset, $size);

        if (!isset($res['_list'])) {
            $res['_list'] = $query->orderby([[TBL_USER . '._id', 'desc']])->result();
        }

        return $res;
    }

    public static function _class_list($school_id, $class_id)
    {
        $db = static::getDb();

        $fields = [
            TBL_USER . '.id AS user_id',
        ];

        $res = $db->select($fields)
            ->from(TBL_USER)
            ->join(TBL_STUDENT, [TBL_USER . '.id' => TBL_STUDENT . '.user_id'])
            ->join(TBL_SCHOOL_CLASS, [TBL_STUDENT . '.class_id' => TBL_SCHOOL_CLASS . '.id'])
            ->where([
                TBL_USER.'.school_id' => $school_id,
               'group' => UserModel::GROUP_STUDENT,
               'class_id' => $class_id,
                TBL_STUDENT.'.is_main' => 1,
                TBL_USER . '.status' => UserModel::STATUS_ACTIVE
            ])
            ->orderby([TBL_USER . '._id'])
            ->result();

        return $res;
    }

	public function _get($school_id, $user_id)
	{
		/* @var $db \vendor\db\Db */
		$db = static::getDb();

	    $res = UserModel::_select(
                 ['school_id' => $school_id, 'id' => $user_id],
				['id AS user_id', 'school_id', 'group', 'username', 'gender', 'realname', 'avatar']
			);
		if (!$res) {
			return [];
		}
	
		$class = $db->select([self::NAME . '.is_main', TBL_SCHOOL_CLASS . '.name AS class_name', TBL_SCHOOL_CLASS . '.id AS class_id'])
			->from(self::NAME)
			->join(TBL_SCHOOL_CLASS, [TBL_SCHOOL_CLASS . '.id' => self::NAME . '.class_id'])
			->where([self::NAME . '.user_id' => $res[0]['user_id']])->result();
		!$class ?: $res[0]['class'] = $class;
		
		return $res;
	}

	public function _getClassId($user_id)
    {
        $db = static::getDb();

        $res = $db->select([self::NAME . '.is_main',self::NAME . '.user_id', TBL_SCHOOL_CLASS . '.name AS class_name', TBL_SCHOOL_CLASS . '.id AS class_id'])
            ->from(self::NAME)
            ->join(TBL_SCHOOL_CLASS, [TBL_SCHOOL_CLASS . '.id' => self::NAME . '.class_id'])
            ->where([self::NAME . '.user_id' => $user_id, self::NAME . '.is_main' => 1])->result();

        return $res;
    }

	public function _set($school_id, $user_id, $class_id, $vals = [])
	{
		$res = $this->callInTransaction(function () use ($school_id, $user_id, $class_id, $vals) {
            $user_res = 0;
		    if ($vals !== null) {
				$user_res = $this->set_user($school_id, $user_id, $vals);
			}

			$pack = compact(self::$sets['id']);

			$stu_res = $this->internal_set($pack, $vals);
			
			if ($stu_res === null) {
				throw new \vendor\exceptions\UnknownException();
			}
			
			if (isset($user_res)) {
				return $user_res;
			} else {
				return $user_res | $stu_res;
			}
		});
		
		return $res;
	}

    public function _del($school_id, $user_id, $class_id, $vals = [])
    {
        $res = $this->callInTransaction(function () use ($school_id, $user_id, $class_id, $vals) {
            $pack = compact(self::$sets['id']);

            $school_id = $pack['school_id'];
            $user_id = $pack['user_id'];

            //删除学生所在班级信息
            $stu = $this->internal_delete($pack, $vals);

            //删除对应班级
            $ucl = UserClassModel::_delete(['school_id' => $school_id, 'user_id' => $user_id, 'class_id' => $class_id]);

            //删除学生用户
            $usr = UserModel::_delete(['school_id' => $school_id, 'id' => $user_id]);

            //删除学生ilab用户
            $ilab = TempUserModel::_delete(['user_id' => $user_id]);

            //删除学生分组的时候, 需要统计当前人数
            $groups = UserGroupListModel::getAllGroup($user_id);
            //循环减少每个分组的统计次数
            $userGroupListModel = new UserGroupListModel();

            foreach ($groups as $group) {
                //循环删除分组用户、并且去掉累计增加的计数
                $userGroupListModel->_del($group['user_id'], $group['group_id']);
            }

            //todo 删除所有考试记录、并且重新进行统计成
            $erds = ExamRecordModel::getMoreInfo(['user_id' => $user_id]);
            $exam  = 1;
            //循环删除多次考试记录
            foreach ($erds as $erd) {

                //先删除统计记录
                $eus = ExamUserStatsModel::_delete([
                    'record_id' => $erd['id'],
                    'exam_id' =>  $erd['exam_id'],
                    'user_id' =>  $erd['user_id'],
                    'class_id' => $erd['class_id'],
                    'school_id' => $erd['school_id']
                ]);

                //删除参加的考试记录
                $erm = ExamRecordModel::_delete(['id' => $erd['id']]);

                //删除答题记录
                $pcm = PaperCardModel::_delete(['record_id' => $erd['id']]);

                //exam stats 重新统计
                $model = new ExamModel();

                $stat = $model->release_score($erd['exam_id'], $erd['school_id']);

                $exam +=  $eus | $erm | $pcm | $stat;
            }

            //todo 删除留言板信息
            $emg = MessageModel::_delete(['user_id' => $user_id]);

            //todo 删除学生的课堂成绩
            $exrs = ExperimentRecordModel::getMoreInfo(['user_id' => $user_id]);
            foreach ($exrs as $exr) {
                //删除所有详情
                ExperimentDetailModel::_delete(['record_id' => $exr['id']]);
                ExamineScoreModel::_delete(['record_id' => $exr['id']]);
            }

            $exrd = ExperimentRecordModel::_delete(['user_id' => $user_id]);

            $exps = ExperimentStatModel::_delete(['user_id' => $user_id]);

            //todo 删除转发地址绑定 (待写)
            //todo 删除用户所有的上传资源文件、 后面单独写脚本删除吧(待写)
            return $stu | $usr | $ucl | $ilab | $exam | $emg | $exrd | $exps;
        });

        return $res;
    }

	protected function set_user($school_id, &$user_id, $vals)
	{
		$userFields = UserModel::fields();
		unset($userFields['id'], $userFields['created_at'], $userFields['updated_at']);
		if ($user_id === null) {
			$vals['school_id'] = $school_id;
		} else {
			$validates = UserModel::validates();
			if (isset($validates['readonly'])) {
				$userFields = array_diff_key($userFields, array_fill_keys($validates['readonly'], null));
			}
		}
		$user_vals = array_intersect_key($vals, $userFields);
		if ($user_vals) {
			$userModel = new UserModel();
			$res = $userModel->_set($user_id, $user_vals);
			if ($res === null) {
				$this->errors = $userModel->errors();
				throw new \vendor\exceptions\UnknownException();
			}
			if (isset($res['id'])) {
				$user_id = $res['id'];
			}
		} else {
			$res = 0;
		}
		
		return $res;
	}
	
	protected function internal_insert($fields)
	{
		$res = $this->callInTransaction(function () use ($fields) {
			$res = parent::internal_insert($fields);
			if ($res) {
				$db = static::getDb();
				$classes = $db->select('class_id')
					->from(self::NAME)
					->where(['user_id' => $fields['user_id']])
					->limit(2)
					->result();
				if (count($classes) == 1) {
					//如果当前只有这一条记录，则设置为主班级
					if ($fields['is_main'] == 0) {
						$this->_update([
								'user_id' => $fields['user_id'], 
								'class_id' => $fields['class_id']
							], ['is_main' => 1]);
					}
				} elseif ($fields['is_main'] == 1) {
					//如果不止一条记录，且当前记录设置为主班级
					$this->_update([
							'user_id' => $fields['user_id'], 
							'is_main' => 1,
							['<>', 'class_id', $fields['class_id']]
						], ['is_main' => 0]);
				}
			}
			return $res;
		});
		
		return $res;
	}
	
	protected function internal_update($pack, $snapshot, $vals)
	{
		$res = $this->callInTransaction(function ($pack, $snapshot, $vals) {
			$res = parent::internal_update($pack, $snapshot, $vals);
			if ($res && isset($vals['is_main']) && $vals['is_main'] == 1) {
				//当前记录更新为主班级，则其他设为非主班级
				$this->_update([
						'user_id' => $pack['user_id'],
						'is_main' => 1,
						['<>', 'class_id', $pack['class_id']]
				], ['is_main' => 0]);
			}
			return $res;
		}, [$pack, $snapshot, $vals]);
		
		return $res;
	}
	
	protected function before_delete($fields)
	{
		if ($fields['is_main'] == 1) {
			return $this->addError('is_main 非法');
		}
		return parent::before_delete($fields);
	}
	
	public function set_main_class($school_id, $user_id, $class_id)
	{
		$class_id = (string)$class_id;
		
		$res = $this->callInTransaction(function () use ($school_id, $user_id, $class_id) {
			$db = static::getDb();
			
			$pack = ['school_id' => $school_id, 'user_id' => $user_id, 'class_id' => $class_id];

			//将原主班级取消，并设置当前班级为主班级
			$this->_update(
					['school_id' => $school_id, 'user_id' => $user_id, 'is_main' => 1], 
					['is_main' => 0]
				);
			$res = $this->_insert($pack + ['is_main' => 1]);
			
			return $res;
		});
		
		return $res;
	}
	
	/**
	 * 旧方法，不使用
	 * @deprecated
	 */
	public function _set_class($school_id, $class_id, $user_ids)
	{
		$db = static::getDb();
		
		$filter_user_ids = $db->select(['user_id'])
			->from(self::NAME)
			->where([
					'class_id' => $class_id,
					'user_id' => $user_ids
			])->result();
		
		if ($filter_user_ids === null) {
			return $this->addError(SERVER_ERR_DB . ERR_SERVER);
		}
			
		$user_ids = array_diff($user_ids, $filter_user_ids);
			
		 $res = $db->update(self::NAME)
		 	->set(['class_id' => $class_id])
			->where([
					'user_id' => $user_ids, 
					'school_id' => $school_id,
			])->result();	

		 return $res;
	}
	
	public static function filterStudents($student_ids)
	{
		$db = static::getDb();
	
		$res = UserModel::_select([
				'id' => $student_ids,
				'group' => UserModel::GROUP_STUDENT,
				'status' => UserModel::STATUS_ACTIVE
			], ['id']);
	
		return $res ? array_column($res, 'id') : [];
	}
}