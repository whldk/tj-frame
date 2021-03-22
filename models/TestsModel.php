<?php namespace models;

use vendor\base\ValidateModel;

class TestsModel extends ValidateModel
{
    /**
     * 设置表名
     */
    const NAME = 'tests';

    /**
     * 示例：定义状态常量
     */
    const OPEN = 1;
    const CLOSE = 2;
    const DELETE = 3;

    /**
     * 实例：自定义状态值数组
     * @var array
     */
    public static $status = [
        self::OPEN,
        self::CLOSE,
        self::DELETE
    ];

    /**
     * 设置主键
     * @var array
     */
    protected static $sets = [
        'auto_inc' => '_id',        //自增长的id
        'hash_id' => 'id',          //设置hash_id
        'id' => ['id']              //设置的索引主键
    ];

    /**
     * 设置数据库对应的字段
     * @var array
     */
    protected static $fields = [
        'id' => null,           //会自动生产hash_id
        'name' => null,
        'school_id' => null,
        'status' => null,
        'score' => null,
        'content' => null,
        'password' => null,
        'upload' => null,
        'img' => null,
        'is_bool' => null,
        'json_data' => null,
        'created_at' => null,
        'updated_at' => null,
    ];

    /**
     * 基于模型-参数过滤器
     * @var array
     */
    protected static $filters = [
        'before' => [         //在数据验证之前、 格式化数据
            'b' => ['is_bool'],       //转换成  bool 类型 返回 1 or 0
            'i' => ['status'],        //转换成  int 类型 返回 i整数
            'f' => ['score'],         //转换成 float 类型 返回 小数
            's' => ['name', 'password'],    //转换成 字符串类型
            'html' => ['content'],      //调用html 组件, 自动转移html 标签
            //'ts' => [],
            'img' => ['upload', 'img'],           //设置上传文件字段
            'map' => [          //自定义验证参数
                [
                    ['name'],
                    'callback' => ['models\TestsModel', 'generate_username'],
                ],
                [
                    ['password'],
                    'callback' => [self::class, 'generate_password_hash']
                ],

            ],
            'ignore' => ['created_at', 'updated_at'],   //插入、更新时、自动忽略参数
         //   'json' => ['json_data']                     //json 格式化
        ],
        'after' => [            //在数据验证之后、 格式化数据
//            'b' => [],
//            'i' => [],
//            'f' => [],
//            's' => [],
//            'html' => [],
//            'ts' => [],
//            'img' => [],
//            'map' => [],
//            'ignore' => [],
            'json' => ['json_data'],
            'ts' => ['ct' => 'created_at', 'mt' => 'updated_at'],
        ]
    ];

    /**
     * 基于模型-参数验证器
     * @return array
     */
    public static function validates()
    {
        if (!self::$validates) {
            self::$validates = [
                'require' => [
                    'name', 'is_bool', 'json_data', 'school_id',
                    'msg' => [
                        'name' => '名称没有上传',
                        'json_data' => 'json＿data　没有上传'
                    ]
                ],
                'readonly' => ['is_bool'],
                'exist' => [
                    'school_id' => [
                        'table' => TBL_SCHOOL,                    //设置表关系
                        'target_fields' => ['school_id' => 'id'], // id 关联索引
                        'condition' => ['status' => 1],     //自定义其他条件
                        'allow_null' => false,
                        //'when' => ['status' => 3],    //如果满足条件、则开始验证、否则直接跳过
                        '!when' => ['status' => 3], //如果满足条件、则不进行验证、直接跳过
                        //'limit' => 1,            //查询的条数、默认 1
                        //'result_fields' => ['*'] //指定返回字段、查询结果
                    ],
                    'upload' => [
                        'callback' => [self::class, 'deleteUpload'],
                        'args' => ['upload', 'img']
                    ],
                    'msg' => [
                        'school_id' => '验证学校失败',
                        'upload' => '上传文件失败'
                    ]
                ],
                'repeat' => [      //repeat 重复验证 要么 验证 string or array ,不能同时验证
                    'name',
//                    ['name', 'school_id'],
                    'msg' => [
                        'name-school_id' => '学校里名称不可重复',
                        'name' => '名称不可重复'
                    ]
                ],
                'filter' => [
                    [
                        ['password'],       //验证的字段
                        'callback' => [self::class, 'TestPassword'], //自定义验证方法
                        'args' => ['password'],    //参数传递
                        //'results' => ['id'] //自定义返回的结果字段-前提是返回结果中包含了字段信息
                    ],
                    'msg' => [
                        'password' => '验证密码失败'
                    ]
                ],
                'range' => [
                    'status' => [self::OPEN, self::CLOSE, self::DELETE],
                    'msg' => [
                        'status' => '状态不在范围中'
                    ]
                ],
                'url' => ['content', 'msg' => ['content' => 'content 不是url']],
                'regular' => [
                    'name' => '/^[a-zA-Z0-9_]+$/',
                    'password' => '/^[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~\-:\.@()\[\]";,]+$/',
                    'msg' => [
                        'name' => '名称验证未通过,请检查格式'
                    ]
                ],
                'string' => [
                    'name' => ['min' => 1, 'max' => 50, 'truncate' => false],  //truncate 超出了就自动截断
                    'msg' => [
                        'name' => '名称长度超出范围'
                    ]
                ],
                'number' => [
                    'score' => ['min' => 1, 'max' => 10, 'fix' => false],
                    'msg' => [
                        'score' => '分数超出范围1-10'
                    ]
                ]
            ];
            self::orderValidates(self::$validates);
        }
        return self::$validates;
    }

    /**
     * 检索删除条件的限制--满足条件会在事务中删除
     * @var array
     */
    protected static $constraints = [];

    /**
     * 事务级别---级联删除
     * @var array
     */
    protected static $cascades = [];

    /**
     * 插入、 更新、 刪除 操作集合
     * @param $id
     * @param array $val
     * @return array|int|mixed|null
     */
    public function _set($id, $val = [])
    {
        $pack = compact(self::$sets['id']);
        return $this->internal_set($pack, $val);
    }


    public static function generate_password_hash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function generate_username($username)
    {
        return strtolower($username);
    }

    public static function deleteUpload($upload, $img)
    {
        @unlink($upload);
        @unlink($img);
        return true;
    }

    public static function TestPassword($password)
    {
        //var_dump($password);
        //return $password;
        return true;
    }
}