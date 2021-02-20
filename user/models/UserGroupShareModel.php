<?php
namespace user\models;

use vendor\base\ValidateModel;

class UserGroupShareModel extends ValidateModel
{
    const NAME = 'user_group_share';

    protected static $sets = [
        'id' => [
            'group_id',          //被分享的组
            'share_group_id',    //设置共享组
        ]
    ];

    /**
     * 查看分享到指定的某些组名
     * @param $share_group_id
     * @return array|int|null
     */
    public function _list($share_group_id)
    {
        $db = static::getDb();
        $fields = ['share_group_id', 'group_id', 'test_id', 'group'];
        $result = $db->select($fields)->from(self::NAME)
            ->join(UserGroupModel::NAME, [UserGroupModel::NAME. '.id' => self::NAME. '.group_id'])
            ->where(['share_group_id' => $share_group_id])
            ->result();
        return $result;
    }

    /**
     * 用户根据自身组，获取其他共享组的信息
     * @param $group_id
     * @return array|int|null
     * @throws \Exception
     */
    public static function _list_group($group_id)
    {
        $db = static::getDb();
        $fields = ['school_id', 'test_id', 'share_group_id', 'class_id', 'group', 'is_upload', 'path'];
        $res = $db->select($fields)->from(self::NAME)
            ->join(UserGroupModel::NAME, [UserGroupModel::NAME. '.id' => self::NAME. '.share_group_id'])
            ->where(['group_id' => $group_id])
            ->result();
        return $res;
    }

    /**
     * 添加共享组
     * @param $share_group_id
     * @param $group_id
     * @return mixed|null
     */
    public function _add($share_group_id, $group_id)
    {
        if (is_array($group_id) || is_array($share_group_id)) {
            return $this->addError( '不支持批量添加');
        }

        $db = static::getDb();

        //必须是相同的实验才能共享
        $share = UserGroupModel::_get($share_group_id, ['id', 'test_id', 'school_id']);
        $group = UserGroupModel::_get($group_id, ['id', 'test_id', 'school_id']);

        if (!$share || !$group) {
            return $this->addError( '要分享的组不存在');
        }

        if ($share[0]['school_id'].$share[0]['test_id'] != $group[0]['school_id'].$group[0]['test_id']) {
            return $this->addError( '必须是相同的实验才能共享');
        }

        //不要重复生成
        $exist = $db->select('*')
            ->from(self::NAME)
            ->where(['group_id' => $group_id, 'share_group_id' => $share_group_id])
            ->limit(1)
            ->result();

        if ($exist) {
            return $this->addError( '不可重复共享分组');
        }

        $inserts = [
            'group_id' => $group_id,
            'share_group_id' => $share_group_id
        ];

        $res = $this->internal_insert($inserts);

        if ($res == null) {
            return $this->addError( '共享分组失败');
        }

        return $res;
    }

    /**
     * 删除共享组
     * @param share_group_id
     * @param $group_id
     * @return array|int|null
     */
    public function _del($share_group_id, $group_id)
    {
        if (is_array($group_id) || is_array($share_group_id)) {
            return $this->addError( '不支持批量删除');
        }

        $db = static::getDb();

        //查看是否存在
        $exist = $db->select('*')
            ->from(self::NAME)
            ->where(['group_id' => $group_id, 'share_group_id' => $share_group_id])
            ->limit(1)
            ->result();

        if (!$exist) {
            return $this->addError( '该分组的没有被共享');
        }

        $inserts = [
            'group_id' => $group_id,
            'share_group_id' => $share_group_id
        ];

        $res = $this->_delete($inserts);

        if ($res === null) {
            return $this->addError( '删除被共享分组错误');
        }

        return $res;
    }
}