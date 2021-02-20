<?php
namespace user\models;

use vendor\base\ValidateModel;

class LoginLogModel extends ValidateModel
{
    const NAME = 'login_log';

    const SOURCE_WEB = 1;       //1、web入口
    const SOURCE_ESP = 2;       //2、esp入口
    const SOURCE_WX = 3;        //3、小程序入口
    const SOURCE_TEMP = 4;      //4、ilab临时用户入口
    const SOURCE_TOKEN = 5;     //5、esp-token入口
    const SOURCE_NURSE = 6;     //6、nurse 登录入口

    protected static $sets = [
        'auto_inc' => '_id',
        'hash_id' => 'id',
        'id' => ['id'],
    ];

    protected static $fields = [
        'id' => null,
        'school_id' => '',
        'user_id' => '',
        'class_id' => '',
        'username' => null,
        'realname' => null,
        'class_name' => null,
        'school_name' => null,
        'group' => null,
        'login_time' => null,
        'source' => null,           //登录来源 ： 1、web入口   , 2esp入口 , 3小程序入口  4 临时用户入口
    ];

    public function LoginLog($vals, $source = 0)
    {
        $time = date("Y-m-d H:i:s",time());
        $fields['login_time'] = $time;
        $fields['user_id'] = $vals['id'];
        $fields['school_name'] = $vals['school_name'] ?? null;
        $fields['school_id'] = $vals['school_id'] ?? null;
        $fields['class_id'] = $vals['class_id'] ?? null;
        $fields['group'] = $vals['group'];
        $fields['username'] = $vals['username'];
        $fields['realname'] = $vals['realname'];
        $fields['class_name'] = $vals['class_name'] ?? null;
        $fields['source'] = $source;
        $res = $this->internal_set(['id' => null], $fields);
        return $res ? ['login_time' => $time] : ['login_time' => 'error'];
    }


    public static function SourceCount($school_id, $mode)
    {
        $filter_where = ['school_id' => $school_id];
        switch ($mode) {
            case self::SOURCE_WEB:
                $filter_where += ['source' => self::SOURCE_WEB];
                break;
            case self::SOURCE_ESP:
                $filter_where += ['source' => self::SOURCE_ESP];
                break;
            case self::SOURCE_WX:
                $filter_where += ['source' => self::SOURCE_WX];
                break;
            case self::SOURCE_NURSE:
                $filter_where += ['source' => [self::SOURCE_NURSE, self::SOURCE_WEB]];
                break;
            default:
                $filter_where += ['source' => [self::SOURCE_ESP, self::SOURCE_WX]];
                break;
        }

        $res = static::getDb()->count('id')->from(self::NAME)
                ->and_filter_where($filter_where)
                ->result();

        return $res ? $res[0] : false;
    }

    public function _list($school_id, $user_id, $group, $source, $order, $size, $page)
    {
        $res = [];

        $fields = self::listFields();

        self::order($order, [
            'login_time' => self::NAME . '.login_time',
        ]);

        $query = static::getDb()->select($fields)->from(self::NAME)
            ->and_filter_where([self::NAME .'.user_id' => $user_id, 'school_id' => $school_id, 'group' => $group, 'source' => $source])
            ->orderby($order ?: [
                [self::NAME . '.login_time', 'desc'],
            ]);

        if ($size !== null) {
            $offset = static::page($query, $page, $size, $res);
            $query->limit($offset, $size);
        }

        if (!isset($res['_list'])) {
            $res['_list'] = $query->result();
        }

        return $res;
    }

}