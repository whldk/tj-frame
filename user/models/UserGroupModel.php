<?php
namespace user\models;

use esp_admin\models\ReportModel;
use vendor\base\ValidateModel;
use vendor\exceptions\UserErrorException;

class UserGroupModel extends ValidateModel
{
    const NAME = 'user_group';

    const MAX_COUNT = 10;  //分组最大人数
    const NOT_SHARED = 0; //取消分享

    const UPLOAD_ON = 1;  //已经上传过实验数据、 和 已分享 状态共享
    const UPLOAD_OFF = 0;

    protected static $fields = [
        'id' => null,
        'school_id' => null,
        'class_id' => null,
        'group' => null,        //分组名称
        'count' => 0,
        'test_id' => null,      //实验课程id report
        'max_count' => self::MAX_COUNT,   //默认分组里面最大10个人
        'is_upload' => self::UPLOAD_OFF,
        'path' => null,         //检测上传的路径类型
    ];

    protected static $sets = [
        'auto_inc' => '_id',
        'hash_id' => 'id',
        'id' => ['id'],
    ];

    protected static $filters = [
        'before' => [
            'i' => ['count', 'max_count'],
            's' => ['school_id', 'group', 'class_id'],
            'ts' =>  ['ct' => 'created_at'],
            'ignore' => ['count', 'is_upload'],
        ]
    ];

    protected static $validates = [
        'require' => ['school_id', 'test_id', 'class_id','group'],
        'repeat' =>  [['school_id', 'test_id', 'class_id', 'group']],
        'readonly' => ['count'],
        'exist' => [
            'test_id' => ['table' => ReportModel::NAME, 'target_fields' => ['test_id' => 'id']],
            'class_id' => ['table' => SchoolClassModel::NAME, 'target_fields' => ['class_id' => 'id']]
        ],
        'string' => [
            'group' => ['min' => 1, 'max' => 255, 'truncate' => false]
        ],
        'number' => [
            'max_count' => ['min' => 1, 'max' => 10, 'fix' => false],
        ]
    ];

    protected static $constraints = [
        ['id' => ['table' => TBL_USER_GROUP_LIST, 'target_fields' => ['id' => 'group_id']]],
        ['id' => ['table' => TBL_USER_GROUP_SHARE, 'target_fields' => ['id' => 'group_id']]],
    ];

    public static function _list($search, $test_id, $school_id, $class_id, $size = null, $page = 0)
    {
        $res = [];

        self::search($search, ['group' => 'group']);

        $db = static::getDb();

        $query = $db->select(array_keys(self::$fields))
                ->from(self::NAME)
                ->where(['school_id' => $school_id, 'test_id' => $test_id])
                ->and_filter_where(['class_id' => $class_id])
                ->and_filter_where($search);

        if ($size !== null) {
            $offset = static::page($query, $page, $size, $res);
            $query->limit($offset, $size);
        }

        if (!isset($res['_list'])) {
            $res['_list'] = $query->result();
        }

        return $res;
    }

    public static function my_group($test_id, $school_id, $class_id)
    {
        $res = [];
        $db = static::getDb();
        $query = $db->select(array_keys(self::$fields))
            ->from(self::NAME)
            ->where(['school_id' => $school_id, 'test_id' => $test_id])
            ->and_filter_where(['class_id' => $class_id]);
        if (!isset($res['_list'])) {
            $res['_list'] = $query->result();
        }
        return $res;
    }

    public static function hasOne($where, $fields = '*')
    {
        $res = self::_select($where, $fields, 1);
        return $res ? $res[0] : false;
    }

    //把当前班级的实验分组，同步到其他实验中去
    public function _sync_test_group($school_id, $test_id, $class_id)
    {
        $test_class_groups = self::_select([
            'school_id' => $school_id,
            'test_id' => $test_id,
            'class_id' => $class_id
        ], ['group', 'max_count']);

        $test_class_groups_count = count($test_class_groups);

        if ($test_class_groups_count == 0) {
            return ['error' => '当前实验下班级没有分组,请检查后在同步'];
        }

        $db = static::getDb();

        //排除当前的实验课程,获取
        $reports = $db->select(['id'])->from(ReportModel::NAME)
            ->where([['<>', 'id', $test_id], ['school_id' => $school_id]])
            ->result();

        if (empty($reports)) {
            return ['error' => '暂无其他实验课程'];
        }

        //开始同步步骤
        //1、分割学生，按步骤插入数据库
        $student = StudentModel::_class_list($school_id, $class_id);
        //获取默认max_count设置数量最多的数组
        $count = array_column($test_class_groups,'max_count');
        $count = array_count_values($count);
        $flip = array_flip($count);
        $max_count = $flip[array_shift($count)];
        //进行分组
        $student_chuck = array_chunk($student, $max_count);
        $hasReports = []; //返回已被同步过的实验
        $res = $this->callInTransaction(function() use(
            $test_class_groups, $school_id, $class_id, $student_chuck, $reports, &$hasReports){
            foreach ($reports as $report) {
                //验证实验班级是否存在
                $hasOne = self::_select(['school_id' => $school_id, 'test_id' => $report['id'], 'class_id' => $class_id], ['id'], 1);
                if ($hasOne) {
                    $hasReports[] = $report['id']; //返回已经有的班级分组的实验
                    continue;
                } else {
                    //循环分组数量
                    $new_test_id = $report['id'];
                    foreach ($test_class_groups as $i => $groups) {
                        $group = self::internal_insert([
                            'school_id' => $school_id,
                            'class_id' => $class_id,
                            'test_id' => $new_test_id,
                            'group' => $groups['group'],
                            'max_count' => $groups['max_count'],
                            'created_at' => time()
                        ]);
                        if (!$group) { //删除之前的数据
                            throw  new UserErrorException('插入分组数据失败');
                        }
                        //插入分组对应的用户学生信息
                        $model = new UserGroupListModel();
                        foreach ($student_chuck[$i] as $stu) {
                            $res = $model->_add($stu['user_id'], $group['id']);  //单个添加,自动累计最大人数
                            if (!$res) {
                                throw new UserErrorException('插入学生分组数据失败');
                            }
                        }
                    }
                }
            }
            return true;
        });
        return $hasReports ? ['already' => $hasReports] : $res;
    }

    /**
     * 自动实验班级分组
     */
    public function _auto_group($school_id, $test_id, $class_id, $number = 5, $auto = 0)
    {
        $class = SchoolClassModel::_select(['id' => $class_id, 'school_id' => $school_id], ['id', 'name']);
        if (empty($class)) {
            return ['error' => '班级不存在'];
        }
        $class_name = $class[0]['name'];
        $test = ReportModel::_select(['id' => $test_id], 'id');
        if (empty($test)) {
            return ['error' => '实验课程不存在'];
        }
        if ((int)$number <= 0) {
            return ['error' => '请设置正确的分组人数'];
        }
        $student = StudentModel::_class_list($school_id, $class_id);
        $total = count($student);
        $group_count = ceil($total / $number);
        if ($auto == '1') {
            //分割学生，进行映射分组
            $student_chuck = array_chunk($student, $number);
            $res = $this->callInTransaction(function() use (
                $school_id, $class_id, $test_id,
                $number, $student_chuck, $group_count, $class_name) {
                //循环创建对应的分组
                for ($i = 0; $i < $group_count; $i++) {
                    $group = self::internal_insert([
                        'school_id' => $school_id,
                        'class_id' => $class_id,
                        'test_id' => $test_id,
                        'group' => $class_name . '《' . ($i+1) .'》组',
                        'max_count' => $number,
                        'created_at' => time()
                    ]);

                    if (!$group) { //删除之前的数据
                        //throw  new UserErrorException('插入分组数据失败');
                        return $this->addError('插入分组数据失败');
                    }
                    //插入分组对应的用户学生信息
                    $model = new UserGroupListModel();
                    foreach ($student_chuck[$i] as $stu) {
                        $res = $model->_add($stu['user_id'], $group['id']);  //单个添加,自动累计最大人数
                        if (!$res) {
                            //throw new UserErrorException('插入学生分组数据失败');
                            return $this->addError('插入学生分组数据失败');
                        }
                    }
                }
                return true;
            });
            return $res;
        } else {
            $end_count = floor($total % $number);
            return [
                'group_count' => '一共可以分成:'.$group_count.'组',
                'total' => $class_name.'总人数: '. $total. '人',
                'end_group' => $end_count == 0 ? '无余下人数' : '最后一组余下: '. $end_count . '人'
            ];
        }
    }

    /**
     * 删除班级内所以分组的成员、包括共享的数据也删除（前提是没有上传数据）
     */
    public function _deleteGroup($school_id, $test_id, $class_id)
    {
        $res = $this->callInTransaction(function() use ($school_id, $test_id, $class_id) {
                $db = static::getDb();
                //1、检测班级分组内部 、是否有小组上传数据
                $hasUpload = $db->select(['id'])->from(self::NAME)->where([
                        'school_id' => $school_id,
                        'test_id' => $test_id,
                        'class_id' => $class_id,
                        'is_upload' => 1
                    ])->limit(1)->result();

                if ($hasUpload) {
                    return $this->addError('已上传过数据');
                }

                //2、查看所有分组
                $groups = $db->select(['id'])->from(self::NAME)->where([
                    'school_id' => $school_id,
                    'test_id' => $test_id,
                    'class_id' => $class_id
                ])->result();

                if (!$groups) {
                    return $this->addError('该班级没有分组');
                }

                foreach ($groups as $group) {
                    //1、删除分组之间的数据共享
                    $delShare = UserGroupShareModel::_delete(['group_id' => $group['id']]);

                    $delShareGroup = UserGroupShareModel::_delete(['share_group_id' => $group['id']]);

                    if ($delShare === null || $delShareGroup === null) {
                        return $this->addError('删除分组的共享数据失败');
                    }

                    //2、删除分组内部的成员
                    $delGroupList = UserGroupListModel::_delete(['group_id' => $group['id']]);

                    if ($delGroupList === null) {
                        return $this->addError('删除分组内的成员失败');
                    }

                    //3、删除分组
                     $delGroup = UserGroupModel::_delete(['id' => $group['id']]);
                     if ($delGroup === null) {
                         return $this->addError('删除分组失败');
                     }
                }

                return true;
        });

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

    public function _set($id, $vals = [])
    {
        $pack = ['id' => $id];
        return $this->internal_set($pack, $vals);
    }

    public function _max_count($id, $max_count)
    {
        $db = static::getDb();
        $res = self::_get($id, ['count', 'max_count']);
        $res = $res ? $res[0] : [];
        if(!$res) {
            return $this->addError( '分组不存在');
        }

        $max_count = (int)$max_count;
        if ($max_count < (int)$res['count']) {
            return $this->addError( '不能低于当前分组人数：'. $res['count']);
        }

        if ($max_count >= 100) {
            return $this->addError( '设置分组人数不合理');
        }

        $tbl = UserGroupModel::NAME;
        $sql = "UPDATE `{$tbl}` SET `max_count` = {$max_count} WHERE `id` = '{$id}' ";
        $res = $db->execute($sql);
        $res !== false ?: $res = null;
        return $res;
    }

    /**
     * 更新是否已经上传
     * @param $id
     * @param $path
     * @return int
     */
    public static function _update_upload($id, $path)
    {
        $res = self::_get($id, ['id', 'is_upload', 'path']);
        $res = $res ? $res[0] : [];
        if($res) {
            $res = self::_update(['id' => $id], [
                'is_upload' => self::UPLOAD_ON,
                'path'  => $path
            ]);
            return $res;
        }
        return self::UPLOAD_ON;
    }
    /**
     * 更新是否已经上传图片数据
     * @param $id
     * @param $path
     * @return int
     */
    public static function _upload_img($id)
    {
        $res = self::_get($id, ['id', 'is_upload', 'path']);
        $res = $res ? $res[0] : [];
        if($res) {
            $res = self::_update(['id' => $id], [
                'is_upload' => self::UPLOAD_ON
            ]);
            return $res;
        }
        return self::UPLOAD_ON;
    }

    public static function group_report_list($school_id, $class_id)
    {
        $tests = self::_select(['school_id' => $school_id, 'class_id' => $class_id], 'DISTINCT test_id');
        return $tests ? $tests : [];
    }

}