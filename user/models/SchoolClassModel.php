<?php
namespace user\models;

use vendor\base\ValidateModel;

class SchoolClassModel extends ValidateModel
{
    const NAME = 'school_class';

    const STATUS_ACTIVE = 1;
    const STATUS_DELETED = 0;

    protected static $fields = [
        'id' => null,
        'school_id' => null,
        'name' => null,
        'grade' => null,
        'major' => null,
        'status' => self::STATUS_ACTIVE,
        'created_at' => null,
        'updated_at' => null,
    ];

    protected static $sets = [
        'auto_inc' => '_id',
        'hash_id' => 'id',
        'id' => ['id'],
    ];

    protected static $filters = [
        'before' => [
            'i' => ['grade'],
            's' => ['name', 'major'],
            'ts' => ['ct' => 'created_at', 'mt' => 'updated_at'],
        ]
    ];

    protected static $constraints = [
        ['id' => ['table' => TBL_STUDENT, 'target_fields' => ['id' => 'class_id']]],
    ];

    protected static $cascades = [
        'id' => [
            //['table' => TBL_COURSE_ACCESS, 'target_fields' => ['id' => 'class_id']],
            ['table' => TBL_USER_CLASS, 'target_fields' => ['id' => 'class_id']],
        ]
    ];

    protected static $validates = [];

    public static function validates()
    {
        if (!self::$validates) {
            self::$validates = [
                'require' => ['school_id', 'name'],
                'repeat' => ['school_id', 'name'],
                'exist' => [
                    'school_id' => ['table' => TBL_SCHOOL, 'target_fields' => ['school_id' => 'id']],
                ],
                'range' => [
                    'status' => [self::STATUS_ACTIVE, self::STATUS_DELETED],
                ],
                'string' => [
                    'name' => ['min' => 0, 'max' => 255, 'truncate' => false],
                    'major' => ['min' => 0, 'max' => 255, 'truncate' => false],
                ],
                'number' => [
                    'grade' => ['min' => date('Y') - 10, 'max' => date('Y')]
                ]
            ];
            self::orderValidates(self::$validates);
        }
        return self::$validates;
    }

    public static function _list_not_page($school_id, $user_id = null)
    {
        if ($user_id) {
            $res = [];
            $class = UserClassModel::_select(['school_id' => $school_id, 'user_id' => $user_id], ['class_id']);
            if ($class) {
                $ids = array_column($class, 'class_id');

                return self::_select(['school_id' => $school_id, 'status' => self::STATUS_ACTIVE, 'id' => $ids], ['id', 'name']);
            }
            return $res;
        }

        return self::_select(['school_id' => $school_id, 'status' => self::STATUS_ACTIVE], ['id', 'name']);
    }

    public function _list($school_id, $user_id, $grade, $status, $search = [], $size, $page)
    {
        $res = [];

        $after_search = [];
        if (isset($user_id)) {
            if (isset($search['teacher_name'])) {
                $after_search = $search;
                self::search($after_search, ['teacher_name' => TBL_USER . '.realname']);
            }
            self::search($search, ['class_name' => self::NAME . '.name']);
        } else {
            self::search($search, ['teacher_name' => TBL_USER . '.realname', 'class_name' => self::NAME . '.name']);
        }

        $ids = self::getPageInfo($res, $school_id, $user_id, $grade, $status, $search, $size, $page);

        if (!$ids) {
            return $res;
        }

        $this_fields = self::$fields;
        unset($this_fields['name']);
        $this_fields = self::alias_fields(null, null, $this_fields);
        $fields = array_merge($this_fields, [
            self::NAME . '.name AS class_name',
            TBL_USER . '.realname AS teacher_name',
            TBL_USER . '.id AS user_id'
        ]);

        $rows = static::getDb()->select($fields)->from(self::NAME)
            ->join(TBL_USER_CLASS, [self::NAME . '.id' => TBL_USER_CLASS . '.class_id'])
            ->join(TBL_USER, [TBL_USER_CLASS . '.user_id' => TBL_USER . '.id'])
            ->where([self::NAME . '.id' => $ids])
            ->and_filter_where($after_search)
            ->orderby([[self::NAME .'._id', 'desc']])
            ->result();

        $classes = [];
        foreach ($rows as $row) {
            $teacher = ($row['teacher_name']) ? ['teacher_name' => $row['teacher_name'], 'user_id' => $row['user_id']] : null;
            unset($row['teacher_name'], $row['user_id']);
            if (!isset($classes[$row['id']])) {
                $classes[$row['id']] = $row + ['teachers' => $teacher ? [$teacher] : []];
            } else {
                $classes[$row['id']]['teachers'][] = $teacher;
            }
        }
        $res['_list'] = array_values($classes);

        return $res;

    }

    protected static function getPageInfo(&$res, $school_id, $user_id, $grade, $status, $search, &$size, &$page)
    {
        $where = [self::NAME . '.school_id' => $school_id];

        !isset($user_id) ?: $where[] = [TBL_USER_CLASS . '.user_id' => $user_id];
        !isset($grade) ?: $where[] = [self::NAME . '.grade' => $grade];
        !isset($status) ?: $where[] = [self::NAME . '.status' => $status];

        //ids fields
        $fields = 'distinct ' . self::NAME . '.id';
        $query = static::getDb()->select($fields)->from(self::NAME)
            ->join(TBL_USER_CLASS, [self::NAME . '.id' => TBL_USER_CLASS . '.class_id'])
            ->join(TBL_USER, [TBL_USER_CLASS . '.user_id' => TBL_USER . '.id'])
            ->where($where)
            ->and_filter_where($search);
        $offset = self::page($query, $page, $size, $res, $fields);
        if (isset($res['_list'])) {
            return [];
        }
        $ids = $query->limit($offset, $size)->result();
        return $ids ? array_column($ids, 'id') : [];
    }

    public function options($school_id, $user_id, $search = [], $size, $page)
    {
        $res = [];

        self::search($search, ['class_name' => self::NAME . '.name']);

        $fields = ['id as class_id', 'name as class_name'];

        $query = static::getDb()->select($fields)->from(self::NAME)
            ->and_filter_where($search);
        if (isset($user_id)) {
            $query->join(TBL_USER_CLASS, [self::NAME . '.id' => TBL_USER_CLASS . '.class_id'])
                ->and_where([TBL_USER_CLASS . '.user_id' => $user_id]);
        }

        $offset = self::page($query, $page, $size, $res);
        if (!isset($res['_list'])) {
            $res['_list'] = $query->limit($offset, $size)->result();
        }

        return $res;

    }

    public static function _get($id, $fields = null)
    {
        if ($fields === null) {
            $fields = self::getFields();
        }

        $res = static::getDb()->select($fields)->from(self::NAME)->where(['id' => $id])->result();

        if ($res === null) {
            self::throwDbException();
        }

        return $res;
    }

    public static function getByName($school_id, $name, $fields = null)
    {
        $fields ?: $fields = self::getFields();

        $res = static::getDb()->select($fields)->from(self::NAME)
            ->where(['school_id' => (string)$school_id, 'name' => trim($name)])
            ->result();

        if ($res === null) {
            self::throwDbException();
        }

        return $res;
    }

    private $inBatchContext = false;

    public function set_batch_context()
    {
        if (!$this->inBatchContext) {
            //切换主key
            self::$sets['id'] = ['school_id', 'name'];
            $this->inBatchContext = true;
        }

        return $this->inBatchContext;
    }

    public function reset_batch_context()
    {
        if ($this->inBatchContext) {
            //恢复主key
            self::$sets['id'] = ['id'];
            $this->inBatchContext = false;
        }

        return !$this->inBatchContext;
    }

    public function batch_set($school_id, $name, $vals)
    {
        //设置上下文
        $this->set_batch_context();

        $pack = compact(self::$sets['id']);
        $res = $this->internal_set($pack, $vals);

        return $res;
    }

    public function _set($id, $vals = [])
    {
        //设置上下文
        $this->reset_batch_context();

        $pack = ['id' => $id];
        return $this->internal_set($pack, $vals);
    }

    public function deactivate($school_id, $grade)
    {
        $res = $this->callInTransaction(function () use ($school_id, $grade) {
            $res = ['class_num' => 0, 'user_num' => 0];

            $res['class_num'] = $this->_update(['school_id' => $school_id, 'grade' => $grade], ['status' => self::STATUS_DELETED]);

            $class_ids = $this->_select(['school_id' => $school_id, 'grade' => $grade, 'status' => self::STATUS_DELETED], 'id');

            if ($class_ids) {
                /* @var $db \vendor\db\Db */
                $db = static::getDb();

                $res['user_num'] = $db->update(TBL_USER)->set(['status' => UserModel::STATUS_DELETED])
                    ->join(TBL_STUDENT, ['id' => 'user_id'])
                    ->where([TBL_STUDENT . '.school_id' => $school_id, 'class_id' => array_column($class_ids, 'id')])
                    ->result();
            }

            return $res;
        });

        return $res;
    }

}