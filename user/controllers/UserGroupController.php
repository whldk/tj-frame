<?php

namespace user\controllers;

use esp_admin\models\ReportModel;
use esp_admin\models\ReportStudyModel;
use user\models\SchoolClassModel;
use user\models\StudentModel;
use user\models\UserGroupListModel;
use user\models\UserGroupModel;
use user\models\UserGroupShareModel;
use user\models\UserModel;
use vendor\base\Controller;
use vendor\base\Helpers;

class UserGroupController extends Controller
{
    protected $access = [
        '*' => ['school_admin', 'teacher'],
        'create' => ['teacher', 'school_admin'],
        'update' => ['school_admin', 'teacher'],
        'delete' => ['school_admin', 'teacher'],
        'listUser' => ['school_admin', 'teacher', 'student'],
        'addUser' => ['school_admin', 'teacher', 'student'],
        'delUser' => ['school_admin', 'teacher', 'student'],
        'list' => ['@'],
        'shareGroup' => ['school_admin', 'teacher'],
        'addGroup' => ['school_admin', 'teacher'],
        'delGroup' => ['school_admin', 'teacher'],
        'setCount' => ['school_admin', 'teacher'],
        'groupShare' => ['school_admin', 'teacher'],
        'addShare' => ['school_admin', 'teacher'],
        'delShare' => ['school_admin', 'teacher'],
        'syncGroup' => ['school_admin', 'teacher'],
        'autoGroup' => ['school_admin', 'teacher'],
        'deleteGroup' => ['school_admin', 'teacher']
    ];

    protected $filter = [
        '*' => [
            '*' => [
                'require' => ['id']
            ]
        ],
        'create' => [],
        'list' => [
            '*' => [
                'default' => [
                    'search' => [
                        'group' => null,
                    ],
                    'test_id' => null,
                    'class_id' => null,
                    'my_group' => 0
                ]
            ]
        ],
        'setCount' => [
            '*' => [
                'require' => ['id', 'max_count']
            ]
        ],
        'listUser' => [
            '*' => [
                'require' => ['group_id']
            ]
        ],
        'addUser' => [
            '*' => [
                'require' => ['group_id', 'user_id']
            ]
        ],
        'delUser' => [
            '*' => [
                'require' => ['group_id', 'user_id']
            ]
        ],
        'shareGroup' => [
            '*' => ['require' => ['group_id']]
        ],
        'groupShare' => [
            '*' => ['require' => ['group_id']]
        ],
        'addShare' => [
            '*' => ['require' => ['share_group_id', 'group_id']]
        ],
        'delShare' => [
            '*' => ['require' => ['share_group_id', 'group_id']]
        ],
        'autoGroup' => [
            '*' => [
                'require' => ['test_id', 'class_id'],
                'default' => ['number' => 5, 'auto' => 0]
            ]
        ],
        'syncGroup' => [
            '*' => [
                'require' => ['test_id', 'class_id'],
                'default' => ['auto' => 0]
            ]
        ],
        'deleteGroup' => [
            '*' => [
                'require' => ['test_id', 'class_id']
            ]
        ]
    ];

    public function actionList()
    {
        $identity = $this->user->getIdentity();
        $this->params['school_id'] = $identity['school_id'] ?: null;
        if ($identity->getRole() == 'student') {
            $class_info = $identity->getMainClassInfo();
            $this->params['class_id'] = $class_info['class_id'];
        }
        $model = new UserGroupModel();
        if ($this->params['my_group'] == 1) {
            $res = $model::my_group($this->params['test_id'], $this->params['school_id'], $this->params['class_id']);
        } else {
            parent::page();
            $res = $model::_list(
                $this->params['search'],
                $this->params['test_id'],
                $this->params['school_id'],
                $this->params['class_id'],
                $this->pagesize,
                $this->page);
        }

        if (isset($res['_list'])) {
            $names = Helpers::array_index(ReportModel::_hasMore(array_column($res['_list'], 'test_id'), ['id', 'name as test_name', 'img', 'note']), 'id');
            Helpers::array_set_col($res['_list'], $names, 'test_id', 'id');
            $class_names = Helpers::array_index(SchoolClassModel::_get(array_column($res['_list'], 'class_id'), ['id', 'name as class_name']), 'id');
            Helpers::array_set_col($res['_list'], $class_names, 'class_id', 'id');
            //如果是学生，验证自己是否在分组内 ,获取所有的分组信息
            if ($identity->getRole() == 'student') {
                $user_id = $identity['id'];
                if ($this->params['my_group'] == 1 && $this->params['test_id']) {
                    foreach ($res['_list'] as $group) {
                        if ($group['test_id'] !== $this->params['test_id']) {
                            continue;
                        }
                        $exist = UserGroupListModel::inGroup($group['id'], $user_id);
                        if ($exist == 1) {
                            $menbers = UserGroupListModel::getGroupAll($group['id']);
                            array_walk($menbers, function (&$v) use ($user_id) {
                                $v['is_me'] = ($v['user_id'] === $user_id) ? 1 : 0;
                            });
                            $group['menbers'] = $menbers;
                            return $this->response($group, $model, 200);
                        }
                    }
                    //如果没有返回 则返回下面的
                    $hasOne = ReportModel::_hasOne(['id' => $this->params['test_id'], 'status' => ReportModel::STATUS_ON], ['id', 'name', 'img', 'note']);
                    if (!$hasOne) {
                        return $this->response(['error' =>'该实验课程不存在'], $model, 400);
                    } else {
                        $res = [
                            'id' => $hasOne['id'],
                            'test_name' => $hasOne['name'],
                            'class_name' => $class_info['class_name'],
                            'class_id' => $class_info['class_id'],
                            'img' => $hasOne['img'],
                            'note' => $hasOne['note']
                        ];
                        return $this->response($res, $model, 200);
                    }

                } else {
                    array_walk($res['_list'], function (&$v) use ($user_id) {
                        $v['in_group']  = UserGroupListModel::inGroup($v['id'], $user_id);
                    });
                }

            }

            //每个组的成员都显示出来
            $user_id = $identity['id'];
            foreach ($res['_list'] as &$group) {
                $menbers = UserGroupListModel::getGroupAll($group['id']);
                array_walk($menbers, function (&$v) use ($user_id) {
                    $v['is_me'] = ($v['user_id'] === $user_id) ? 1 : 0;
                });
                $group['menbers'] = $menbers;
            }

        }
        return $this->response($res, $model, 200);
    }

    //A班级实验分组完后创建之后，老师支持 默认同步到所有实验下
    public function actionSyncGroup()
    {
        $identity = $this->user->getIdentity();
        $model = new UserGroupModel();
        $res = $model->_sync_test_group(
            $identity['school_id'],
            $this->params['test_id'],
            $this->params['class_id'],
            $this->params['auto']);

        //返回实验下已存在重复的同步记录
        if (isset($res['already'])) {
            //返回已重复的实验列表
            $names = ReportModel::_hasMore($res['already'], ['id', 'name as test_name']);
            return $this->response(['already' =>$names], $model, 201);
        }

        return $this->response($res, $model, 201);
    }

    //班级自动分组
    public function actionAutoGroup()
    {
        $identity = $this->user->getIdentity();
        $model = new UserGroupModel();
        $res = $model->_auto_group(
            $identity['school_id'],
            $this->params['test_id'],
            $this->params['class_id'],
            $this->params['number'],
            $this->params['auto']
        );
        if (isset($res['error'])) {
            return $this->response($res, $model, 400);
        }
        return $this->response($res, $model, 201);
    }


    //分组一键删除 （包括删除分组的共享）
    public function actionDeleteGroup()
    {
        $identity = $this->user->getIdentity();
        $model = new UserGroupModel();
        $res = $model->_deleteGroup(
            $identity['school_id'],
            $this->params['test_id'],
            $this->params['class_id']
        );
        return $this->response($res, $model, 200);
    }

    public function actionCreate()
    {
        $identity = $this->user->getIdentity();
        $this->params['school_id'] = $identity['school_id'];
        $model = new UserGroupModel();
        $res = $model->_set(null, $this->params);
        return $this->response($res, $model, 201);
    }

    public function actionView()
    {
        $model = new UserGroupModel();
        $res = $model->_get($this->params['id']);
        return $this->response($res, $model, 200);
    }

    public function actionUpdate()
    {
        $model = new UserGroupModel();
        $res = $model->_set($this->params['id'], $this->params);
        return $this->response($res, $model, 201);
    }

    public function actionDelete()
    {
        //验证分组数据是否删除
        $check = self::isCanDel();
        if ($check !== true) {
            return $this->response($check, null, 400);
        }
        $model = new UserGroupModel();
        $res = $model->_set($this->params['id'], null);
        return $this->response($res, $model, 201);
    }

    public function actionSetCount()
    {
        $model = new UserGroupModel();
        $res = $model->_max_count($this->params['id'], $this->params['max_count']);
        return $this->response($res, $model, 201);
    }

    public function actionListUser()
    {
        $model = new UserGroupListModel();
        $res = $model->_list_group($this->params['group_id']);
        if (isset($res['_list'])) {
            $users = Helpers::array_index(UserModel::_get(array_column($res['_list'], 'user_id'), ['id', 'username', 'realname']), 'id');
            Helpers::array_set_col($res['_list'], $users, 'user_id', 'id');
            $class = StudentModel::_getClassId(array_column($res['_list'],'user_id'));
            $class_ids = Helpers::array_index($class, 'user_id');
            Helpers::array_set_col($res['_list'], $class_ids, 'user_id',null, ['class_id' => null, 'class_name' => null]);
        }
        return $this->response($res, $model, 201);
    }

    public function actionAddUser()
    {
        $model = new UserGroupListModel();
        $res = $model->_add($this->params['user_id'], $this->params['group_id']);
        return $this->response($res, $model, 201);
    }

    public function actionDelUser()
    {
        $model = new UserGroupListModel();
        $res = $model->_del($this->params['user_id'], $this->params['group_id']);
        return $this->response($res, $model, 201);
    }

    /**
     * 查看当前组的被分享情况
     * @return array|null
     * @throws \vendor\exceptions\InvalidConfigException
     */
    public function actionShareGroup()
    {
        $model = new UserGroupShareModel();
        $res = $model->_list($this->params['group_id']);
        if ($res) {
            $names = Helpers::array_index(ReportModel::_hasMore(array_column($res, 'test_id'), ['id', 'name as test_name', 'eid']), 'id');
            Helpers::array_set_col($res, $names, 'test_id', 'id');
        }
        return $this->response($res, $model, 201);
    }

    /**
     * 查看当前组分享的情况
     * @return array|null
     * @throws \vendor\exceptions\InvalidConfigException
     */
    public function actionGroupShare()
    {
        $model = new UserGroupShareModel();
        $res = $model->_list_group($this->params['group_id']);
        if ($res) {
            $names = Helpers::array_index(ReportModel::_hasMore(array_column($res, 'test_id'), ['id', 'name as test_name', 'eid']), 'id');
            Helpers::array_set_col($res, $names, 'test_id', 'id');
        }
        return $this->response($res, $model, 201);
    }

    public function actionAddShare()
    {
        if ($this->params['share_group_id'] == $this->params['group_id']) {
            return $this->response(['error' => '不允许共享给自己组'], null, 400);
        }
        $model = new UserGroupShareModel();
        $res = $model->_add($this->params['share_group_id'], $this->params['group_id']);
        return $this->response($res, $model, 201);
    }

    public function actionDelShare()
    {
        $model = new UserGroupShareModel();
        $res = $model->_del($this->params['share_group_id'], $this->params['group_id']);
        return $this->response($res, $model, 201);
    }

    protected  function isCanDel()
    {
        $res = UserGroupModel::_get($this->params['id']);
        $res = $res ? $res[0] : [];
        if (!$res) {
            return ['error' => '实验分组不存在'];
        }
        if ($res['is_upload'] == 1 && $res['path'] != null) {
            return ['error' => '实验分组数据已经存在,无法删除'];
        }
        return true;
    }

}