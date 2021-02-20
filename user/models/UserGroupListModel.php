<?php
namespace user\models;

use esp_admin\models\ReportModel;
use vendor\base\Helpers;
use vendor\base\ValidateModel;

class UserGroupListModel extends ValidateModel
{
    const NAME = 'user_group_list';

    protected static $sets = [
        'id' => ['group_id', 'user_id']
    ];

    /**
     * 查看每个group的成员
     * @param $group_id
     * @return array|int|null
     */
    public function _list_group($group_id)
    {
        $res = [];
        $db = static::getDb();
        $fields = ['user_id'];
        $result = $db->select($fields)->from(self::NAME)
            ->join(UserGroupModel::NAME, [UserGroupModel::NAME. '.id' => self::NAME. '.group_id'])
            ->where(['group_id' => $group_id])
            ->result();
        if (!isset($res['_list'])) {
            $res['_list'] = $result;
        }
        return $res;
    }

    /**
     * 给组添加用户
     * @param $user_id
     * @param $group_id
     * @return mixed|null
     */
    public function _add($user_id, $group_id)
    {
        if (is_array($user_id) || is_array($group_id)) {
            return $this->addError('不支持批量添加');
        }

        $db = static::getDb();

        # 1 查看分组人数 是否满了
        $group = $db->select(['count', 'max_count', 'test_id', 'class_id'])
            ->from(UserGroupModel::NAME)
            ->where(['id' => $group_id])
            ->limit(1)
            ->result();

        if (empty($group)) {
            return $this->addError( '该分组不存在');
        }

        if ($group[0]['count'] == $group[0]['max_count']) {
            return $this->addError( '该分组人数已经满员了');
        }

        //取出试验下所有的分组
        $groups = $db->select(['id'])->from(UserGroupModel::NAME)
            ->where(['test_id' => $group[0]['test_id']])->result();

        $groups_ids = array_column($groups, 'id');

        //不要重复生成
        $exist = $db->select('*')
            ->from(self::NAME)
            ->where(['group_id' => $groups_ids, 'user_id' => $user_id])
            ->result();

        if ($exist) {
            return $this->addError( '分组已存在,不可重复进入');
        }

        $inserts = [
            'group_id' => $group_id,
            'user_id' => $user_id
        ];

        $res = $this->internal_insert($inserts);

        if ($res == null) {
            return $this->addError( '插入用户错误');
        }

        $count = $this->_setCount($group_id, '+');

        if ($count == null) {
            return $this->addError( '统计分组数量错误');
        }

        return $res;
    }

    /**
     * 给组删除用户
     * @param $user_id
     * @param $group_id
     * @return array|int|null
     */
    public function _del($user_id, $group_id)
    {
        if (is_array($user_id) || is_array($group_id)) {
            return $this->addError( '不支持批量添加');
        }

        $db = static::getDb();

        //查看是否存在
        $exist = $db->select('*')
            ->from(self::NAME)
            ->where(['group_id' => $group_id, 'user_id' => $user_id])
            ->limit(1)
            ->result();

        if (!$exist) {
            return $this->addError( '该分组下的成员不存在');
        }

        $inserts = [
            'group_id' => $group_id,
            'user_id' => $user_id
        ];

        $res = $this->_delete($inserts);

        if ($res === null) {
            return $this->addError( '删除分组用户错误');
        }

        $count = $this->_setCount($group_id, '-');

        if ($count == null) {
            return $this->addError( '统计分组数量错误');
        }

        return $res;
    }

    /**
     * 用于统计当前人数
     * @param $group_id
     * @param $opt
     * @return bool|false|int|null
     */
    public function _setCount($group_id, $opt)
    {
        if (in_array($opt, ['+', '-'])) {
            $db = static::getDb();
            $tbl = UserGroupModel::NAME;
            $sql = "UPDATE `{$tbl}` SET `count` = `count` {$opt} 1 WHERE `id` = '{$group_id}' ";
            $res = $db->execute($sql);
            $res !== false ?: $res = null;
            return $res;
        }
        return false;
    }

    /**
     * 查看用户是否存在某个实验的分组里
     * @param $test_id
     * @param $user_id
     * @param bool $show
     * @return array|bool
     * @throws \Exception
     */
    public static function getUserGroup($test_id, $user_id, $show = true)
    {
        $db = static::getDb();
        $fields = ['school_id', 'group_id', 'test_id', 'class_id', 'group', 'path'];
        $exist = $db->select($fields)
            ->from(self::NAME)
            ->join(UserGroupModel::NAME, [UserGroupModel::NAME . '.id' => self::NAME . '.group_id'])
            ->where(['test_id' => $test_id, 'user_id' => $user_id])
            ->limit(1)
            ->result();

        if ($exist) {
            $names = Helpers::array_index(ReportModel::_get(array_column($exist, 'test_id'), ['id', 'name as test_name']), 'id');
            Helpers::array_set_col($exist, $names, 'test_id', 'id');
        }

        if ($show) {
            return $exist ? true : false;
        } else {
             if (!$exist) {
                 return ['error' => '该用户不在实验分组内'];
             }
             return $exist ? $exist[0] : false;
        }
    }


    /**
     * 用户查询学生所有的实验分组信息
     * @param $user_id
     * @return array|int|null
     */
    public static function getAllGroup($user_id)
    {
        $db = static::getDb();
        $exist = $db->select('*')
            ->from(self::NAME)
            ->where(['user_id' => $user_id])
            ->result();
        return $exist;
    }

    /**
     * 获取小组内所有成员信息
     */
    public static function getGroupAll($group_id)
    {
        $db = static::getDb();
        $user_info = $db->select('user_id')
            ->from(self::NAME)
            ->where(['group_id' => $group_id])
            ->result();
        //获取所有的学生信息
        if ($user_info) {
            $users = Helpers::array_index(UserModel::_get(array_column($user_info, 'user_id'), ['id', 'username', 'realname']), 'id');
            Helpers::array_set_col($user_info, $users, 'user_id', 'id');
        }
        return $user_info;
    }

    public static function inGroup($group_id, $user_id)
    {
        $db = static::getDb();
        $exist = $db->select(['group_id', 'user_id'])
            ->from(self::NAME)
            ->where(['group_id' => $group_id, 'user_id' => $user_id])
            ->limit(1)
            ->result();
        return $exist ? 1 : 0;
    }

    public function experiment_list($school_id, $user_id)
    {
        $db = static::getDb();
        $list = $db->select(['test_id'])
            ->from(self::NAME)
            ->join(UserGroupModel::NAME, [UserGroupModel::NAME.'.id' => self::NAME.'.group_id'])
            ->where(['user_id' => $user_id, 'school_id' => $school_id])
            ->result();
        return $list;
    }
}