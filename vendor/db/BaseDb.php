<?php
namespace vendor\db;

use PDO;
use PDOStatement;
use vendor\base\LogTrait;

/**
 * db类，基于PDO
 * 注意：
 * 表名、字段名都会进行小写转换
 * 
 * @author Administrator
 */
class BaseDb
{
	use LogTrait;
	
	protected $config = [
			'dsn' => null,
			'username' => null,
			'passwd' => null,
			'options' => [
					PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
					PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
			]
	];
	
	const ESCAPE_CHR = '!';
	
	protected static $compare_ops = [
			'LIKE', 'NOT LIKE', 'IN', 'NOT IN',
			'=', '!=', '>', '<', '>=', '<=', '<>'
	];
	protected static $logic_ops = ['AND', 'OR'];
	
	/**
	 * @var PDO
	 */
	protected $db;
	/**
	 * @var PDOStatement
	 */
	protected $stmt;
	protected $last_sql = '';
	
	protected $sql_type = 'select';	//默认select查询
	protected $raw_sql = '';		//原生(prepare)sql
	protected $_tbl;
	protected $_select = '*';//fields
	protected $_where;	//condition
	protected $_filter_where;	//condition
	protected $_limit;
	protected $_orderby;	//fields
	protected $_groupby;	//fields
	protected $_having;	//condition
	protected $_set;
	protected $_insert_fields;
	protected $_insert_data;
	protected $_join = [];	//array
	
	protected $_odk_update = [];	//on duplicate key update
	
	protected $prepared_params = [];
	protected $params_count = 0;
	
	protected $old_sql_type;
	protected $old_select;
	
	/**
	 * 注意设置utf8
	 * @param string $dsn 例如 mysql:host=localhost;dbname=DB;charset=UTF8
	 * @param string $username
	 * @param string $passwd
	 * @param array $options
	 */
	public function __construct($config = [])
	{
		$pdo_config = array_intersect_key($config, ['dsn' => null, 'username' => null, 'passwd' => null]);
		$this->config = $pdo_config + $this->config;
		$this->config['options'] = isset($config['options']) ? $config['options'] + $this->config['options'] : $this->config['options'];
		
		/* trait construct */
		$this->logTrait((isset($config['logger']) ? $config['logger'] : []) + ['logFile' => DIR_LOG . DIRECTORY_SEPARATOR . 'db.log']);
		
		if ($this->connect() !== true) {
			throw new \Exception('PDO connection failed.');
		}
	}
	
	public function __destruct()
	{
		$this->db = null;
	}
	
	public function connect()
	{
		try {
			$this->db = new PDO($this->config['dsn'], $this->config['username'], $this->config['passwd'], $this->config['options']);
			return true;
		} catch (\PDOException $e) {
			if ($this->enable_log) {
				$this->log('PDO connection failed.', 'error', $e->getCode(), $e->getMessage());
			}
			return false;
		}
	}
	
	public function close()
	{
		$this->db = null;
	}
	
	/**
	 * 添加预处理语句中的变量
	 * @param mixed $val
	 * @return string 占位符
	 */
	protected function add_prepared_param($val)
	{
		$param_holder = ':param' . ++$this->params_count;
		
		if (is_null($val)) {
			$type = PDO::PARAM_NULL;
		} elseif (is_int($val)) {
			$type = PDO::PARAM_INT;
		} elseif (is_double($val)) {
			$type = PDO::PARAM_STR;
		} else {
			$type = PDO::PARAM_STR;
			$val = trim(strval($val));
		}
		$this->prepared_params[$param_holder] = ['type' => $type, 'value' => $val];
		
		return $param_holder;
	}
	
	/**
	 * 设置操作的表
	 * @param string|array $tbl
	 * @return Db
	 */
	public function table($tbl)
	{
		if (empty($tbl)) {
			$this->_tbl = null;
			return $this;
		}
		
		if (is_string($tbl)) {
			$tbl = trim($tbl, ', ');
			//user, user u, user AS u
			if (strpos($tbl, ',')) {
				//多表
				$tables = explode(',', $tbl);
				foreach ($tables as $table) {
					$table = trim($table);
					if (!empty($table)) {	//注意空字符串
						$this->_tbl .= ', ' . $this->build_field($table);
					}
				}
			} elseif (!empty($tbl)) {
				//单表
				$this->_tbl .= ', ' . $this->build_field($tbl);
			}
		} elseif (is_array($tbl)) {
			foreach ($tbl as $key => $val) {
				if (is_string($val)) {	//其他情况，直接忽略
					$val = trim($val);
					if (!empty($val)) {
						if (is_numeric($key)) {
							$this->_tbl .= ', ' . $this->build_field($val);
						} else {
							$key = trim($key);
							$key = $this->build_field($key);
							$this->_tbl .= ', ' . $key . ' `' . $val . '`'; 
						}
					}
				}
			}
		}
		
		$this->_tbl = trim($this->_tbl, ', ');
		
		return $this;
	}
	
	/**
	 * 生成标准化的field
	 * field 可能是：
	 * max(money), sum(money), balance.money, distinct name, count(*) as count等形式 
	 * @param string $field
	 * @return string
	 */
	protected function build_field($field)
	{
		$field = strtolower(trim($field, ".  \t\n\r\0\x0B"));
		if (empty($field)) {
			return '';
		}
		
		$field = str_replace('`', '', $field);
	
		$pattern = '/^(distinct\s+)?([a-z0-9_]+\s*\(\s*)?([a-z0-9_]+|\*)((\.[a-z0-9_]+|\.\*)*)(\s*\)\s*)?((\s+as\s+|\s+)([a-z0-9_]+))?$/i';
// 		$pattern = '/^(distinct\s+)?([a-zA-Z0-9_]+\s*\(\s*)?([a-z0-9_]+|\*)((\.[a-z0-9_]+|\.\*)*)(\s*\)\s*)?((\s+as\s+|\s+)([a-z0-9_]+))?$/i';
		$field = preg_replace_callback($pattern, [$this, 'replace_field'], $field);
		$field = str_replace('.', '`.`', $field);
		
		return $field;
	}
	
	protected function replace_field($matches)
	{
		$replaced_field = @"$matches[1]$matches[2]`$matches[3]$matches[4]`$matches[6]";
		if (!empty($matches[7])) {
			$replaced_field .= "$matches[8]`$matches[9]`";
		}
		return $replaced_field;
	}
	
	/**
	 * 生成sql的where条件，基于prepare预处理语句
	 * 注意：不支持纯字段条件，可以通过join和on来间接实现
	 * @param string|array $condition 推荐array
	 * @param bool $filter 是否过滤空条件，默认false
	 * @return string
	 */
	protected function build_where($condition, $filter = false)
	{
		if (empty($condition)) {
			return '';
		}
		
		if (is_string($condition)) {
			return $condition;
		}
		
		if (is_array($condition)) {
			//获取操作符
			$op = $this->get_op($condition);
			
			//如果$op是compare_ops
			if (in_array($op, self::$compare_ops)) {
				return $this->build_condition($condition, $op, $filter);
			}
			
			//组装
			$conditions = [];
			foreach ($condition as $key => $val) {
				if (is_numeric($key)) {
					//'xxxxxxxxxx', []
					if ($val) {
						if (is_string($val)) {
							$conditions[] = $val;
						} elseif (is_array($val)) {
							$tmp_condition = $this->build_where($val, $filter);
							$tmp_condition === '' ?: $conditions[] = $tmp_condition;
						}
					}
				} else {
					$key = $this->build_field($key);	//规范下
					//'name' => 'xxx', 'name' => []   后者生成in查询
					if (is_array($val)) {
						$val = array_unique(array_values($val));
						$hits = [];
						foreach ($val as $hit) {
							$hits[] = $this->add_prepared_param($hit);
						}
						if ($filter && empty($hits)) {	//如果设置了过滤
							continue;
						}
						
						//IN () 改成 0=1 （尽量在业务逻辑里面处理）
						if ($hits) {
							$hits = '(' . implode(',', $hits) . ')';
							$conditions[] = $key . ' IN ' . $hits;
						} else {
							$conditions[] = '0=1';
						}
						
					} else {
						if ($filter && (is_null($val) || $val === '')) {	//如果设置了过滤
							continue;
						}
						if (is_null($val)) {
							$conditions[] = $key . ' IS ' . $this->add_prepared_param($val);
						} else {
							$conditions[] = $key . ' = ' . $this->add_prepared_param($val);
						}
					}
				} 
			}
			$conditions = $conditions ? '(' . implode(") {$op} (", $conditions) . ')' : '';
			return $conditions;
		}
		
		return '';
	}
	
	protected function build_condition($condition, $op, $filter = false)
	{
		//数组，且至少有两个元素
		if (!is_array($condition) || count($condition) < 2) {
			return '';
		}
		
		list($field, $val) = array_values($condition);
		$extra_condition = array_slice($condition, 2);
		
		if ($filter && (is_null($val) || $val === '' || $val === [])) {	//如果设置了过滤
			return '';
		}
		
		$field = $this->build_field(strval($field));
		
		$condition = '';
		switch ($op) {
			case 'LIKE' : 
			case 'NOT LIKE' : 
				$val = strval($val);	//必须为字符串
				//escape
				@list($side, $escape, $escape_chr) = $extra_condition;
				if ($escape !== false) {
					isset($escape_chr) && strlen($escape_chr) === 1 ?: $escape_chr = self::ESCAPE_CHR;
					$val = str_replace([$escape_chr, '%', '_'], [$escape_chr . $escape_chr, $escape_chr . '%', $escape_chr . '_'], $val);
				}
				//side
				if ($side === 'left') {
					$val = '%' . $val;
				} elseif ($side === 'right') {
					$val .= '%';
				} elseif ($side === 'both' || !$side) {
					$val = '%' . $val . '%';
				}
				$param_holder = $this->add_prepared_param($val);
				$condition = "{$field} {$op} {$param_holder}" . ($escape !== false ? " ESCAPE '{$escape_chr}'" : '');
				break;
			case 'IN' : 
			case 'NOT IN' : 
				if (is_string($val)) {
					//1,2,3
					$val = explode(',', $val);
					$val = array_map('trim', $val);
				}
				$hits = [];
				if (is_array($val)) {
					foreach ($val as $hit) {
						$hits[] = $this->add_prepared_param($hit);
					}
				}
				$hits = '(' . implode(',', $hits) . ')';
				$condition = "{$field} {$op} {$hits}";
				break;
			case '=' :
				!is_null($val) ?: $op = 'IS';
				$param_holder = $this->add_prepared_param($val);
				$condition = "{$field} {$op} {$param_holder}";
				break;
			case '!=' :
			case '<>' :
				!is_null($val) ?: $op = 'IS NOT';
			default :
				$param_holder = $this->add_prepared_param($val);
				$condition = "{$field} {$op} {$param_holder}";
		}
		
		return $condition;
	}
	
	/**
	 * 获取操作符，并过滤掉condition中的操作符
	 * @param mixed &$condition
	 * @return string 操作符
	 * @see db::$logic_ops
	 * @see db::$compare_ops
	 */
	protected function get_op(&$condition)
	{
		if (isset($condition[0]) && is_string($condition[0])) {
			$op = strtoupper(trim($condition[0]));
			if (in_array($op, self::$logic_ops) || in_array($op, self::$compare_ops)) {
				unset($condition[0]);
			} else {
				$op = 'AND';
			}
		} else {
			$op = 'AND';
		}
	
		return $op;
	}
	
	/**
	 * 设置where条件
	 * condition的形式：
	 * 基本形式 ： ['logic_op', 'field' => 'value', 'field' => ['value'], ['compare_op', 'field', 'value']]
	 * 其他就是基本形式的复合
	 * @param mixed $condition
	 * @return Db
	 */
	public function where($condition)
	{
		$this->_where = $condition;
		return $this;
	}
	
	/**
	 * @param mixed $condition
	 * @return Db
	 */
	public function or_where($condition)
	{
		if ($this->_where) {
			
			//@todo need test, string => array
			if (is_string($this->_where)) {
				$this->_where = [$this->_where];
				$op = 'AND';
			} else {
				$op = $this->get_op($this->_where);
			}
			
			if ($op == 'OR') {
				array_push($this->_where, $condition);
			} elseif ($op == 'AND') {
				$this->_where = [$this->_where, $condition];
			} else {
				// 比较运算符
				array_unshift($this->_where, $op);
				$this->_where = [$this->_where, $condition];
			}
			array_unshift($this->_where, 'OR');
		} else {
			$this->_where = $condition;
		}
		
		return $this;
	}

	/**
	 * @param mixed $condition
	 * @return Db
	 */
	public function and_where($condition)
	{
		if ($this->_where) {
			
			//@todo need test, string => array
			if (is_string($this->_where)) {
				$this->_where = [$this->_where];
				$op = 'AND';
			} else {
				$op = $this->get_op($this->_where);
			}
			
			if ($op == 'AND') {
				array_push($this->_where, $condition);
			} elseif ($op == 'OR') {
				array_unshift($this->_where, $op);
				$this->_where = [$this->_where, $condition];
			} else {
				// 比较运算符
				array_unshift($this->_where, $op);
				$this->_where = [$this->_where, $condition];
			}
			array_unshift($this->_where, 'AND');
		} else {
			$this->_where = $condition;
		}
		
		return $this;
	}
	
	/**
	 * @param mixed $condition
	 * @return Db
	 */
	public function and_filter_where($condition)
	{
		if ($this->_filter_where) {
			$op = $this->get_op($this->_filter_where);
			if ($op == 'AND') {
				array_push($this->_filter_where, $condition);
			} else {
				//比较运算
				$this->_filter_where = [$this->_filter_where, $condition];
			}
		} else {
			$this->_filter_where = $condition;
		}
		return $this;
	}
	
	/**
	 * 
	 * @param integer $offset
	 * @param integer $num
	 * @return Db
	 */
	public function limit($offset = 10, $num = null)
	{
		$this->_limit = ' LIMIT ' . intval($offset);
		if (!is_null($num))	{
			$this->_limit .= ',' . $num;
		}
		
		return $this;
	}
	
	/**
	 * 生成逗号分隔形式的fields
	 * @param string|array $fields
	 * @return string
	 */
	protected function comma_fields($fields)
	{
		if (is_string($fields)) {
			$fields = strtolower(trim($fields, ',. '));
			$fields = explode(',', $fields);
		}
		
		if (is_array($fields) && !empty($fields)) {
			foreach ($fields as $i => $field) {
				$fields[$i] = $this->build_field($field);
			}
			return implode(',', $fields);
		}
		
		return '';
	}
	
	public function raw()
	{
		$this->reset();
		$this->sql_type = 'raw';
		return $this;
	}
	
	/**
	 * 添加预处理语句中的多个变量,并且替换为占位符
	 * @param array $vals
	 */
	public function add_prepared_params(&$vals)
	{
		$vals = array_map([$this, 'add_prepared_param'], $vals);
	}
	
	public function sql($sql)
	{
		$this->raw_sql = (string)$sql;
		return $this;
	}
	
	/**
	 * 注意：采用mysql函数的字段，写成数组的形式
	 * @param string|array $fields
	 * @return Db
	 */
	public function select($fields = '*')
	{
		$this->reset();
		
		$this->sql_type = 'select';
		
		if ($fields == '*') {
			$this->_select = '*';
		} else {
			$this->_select = $this->comma_fields($fields);
		}
		
		return $this;
	}
	
	/**
	 * 注意：采用mysql函数的字段，写成数组的形式
	 * @param string|array $fields
	 * @return Db
	 */
	public function select_for_update($fields = '*')
	{
		$this->reset();
		
		$this->sql_type = 'select for update';
		
		if ($fields == '*') {
			$this->_select = '*';
		} else {
			$this->_select = $this->comma_fields($fields);
		}
		
		return $this;
	}
	
	/**
	 * 
	 * @param string $field
	 * @return Db
	 */
	public function count($field = '*', $alias = 'count', $reset = false)
	{
		if ($reset) {
			$this->reset();
		} else {
			$this->old_sql_type = $this->sql_type;
			$this->old_select = $this->_select;
		}
		
		$this->sql_type = 'select';
		
		if ($field == '*') {
			$this->_select = 'COUNT(*) as ' . $alias;
		} else {
			$field = $this->build_field(strval($field));
			$this->_select = "COUNT({$field})  as {$alias}";
		}
		
		return $this;
	}
	
	/**
	 * 
	 * @param mixed $tbl
	 * @return Db
	 */
	public function from($tbl)
	{
		$this->table($tbl);
		return $this;
	}
	
	/**
	 * 排序
	 * @param string|array $fields 字符串时，只能是字段不含排序方式；数组时，可定义多字段及排序方式 [['name','desc'], ['money', 'asc']]
	 * @param string $order
	 * @return Db
	 */
	public function orderby($fields, $order = 'ASC')
	{
		if (is_string($fields)) {
			$fields = $this->build_field($fields);
			$this->_orderby = " ORDER BY {$fields} {$order}";
		} elseif (is_array($fields)) {
			$orderby = [];
			foreach ($fields as $field) {
				if (is_string($field)) {
					$field = $this->build_field($field);
					$orderby[] = "{$field} {$order}";
				} elseif (is_array($field) && !empty($field)) {
					$field = array_values($field);
					$field[0] = $this->build_field($field[0]);
					$orderby[] = $field[0] . ' ' . (isset($field[1]) ? $field[1] : $order);
				}
			}
			$this->_orderby = ' ORDER BY ' . implode(',', $orderby);
		}
		
		return $this;
	}
	
	/**
	 * 
	 * @param string|array $fields
	 * @return Db
	 */
	public function groupby($fields)
	{
		$this->_groupby = ' GROUP BY ' . $this->comma_fields($fields);
		return $this;
	}
	
	/**
	 * 
	 * @param mixed $condition
	 * @return Db
	 */
	public function having($condition)
	{
		$this->_having = $condition;
		return $this;
	}
	
	/**
	 *
	 * @param mixed $tbl
	 * @return Db
	 */
	public function update($tbl = null)
	{
		$this->reset();
		$this->sql_type = 'update';
		if ($tbl !== null) {
			$this->table($tbl);
		}
		return $this;
	}
	
	/**
	 * @param string|array $fields  'field' 或者 ['field' => 'val', ...]
	 * @param mixed $val
	 * @return Db
	 */
	public function set($fields, $raw = false)
	{
		if (!is_array($fields)) {
			throw new \InvalidArgumentException();
		}
		
		$set = [];
		if ($raw) {
			foreach ($fields as $field => $val) {
				if (!is_string($val)) {
					throw new \InvalidArgumentException();
				}
				$field = $this->build_field($field);
				$set[] = "{$field} = {$val}";
			}
		} else {
			foreach ($fields as $field => $val) {
				$field = $this->build_field($field);
				$param_holder = $this->add_prepared_param($val);
				$set[] = "{$field} = {$param_holder}";
			}
		}
		$this->_set = ' SET ' . implode(',', $set);
		
		return $this;
	}
	
	/**
	 * @param string|array $set  'field' 或者 ['field' => 'val', ...]
	 * @param mixed $val
	 * @return Db
	 */
	public function on_duplicate_key_update($fields, $raw = false)
	{
		if (!is_array($fields)) {
			throw new \InvalidArgumentException();
		}
		
		$set = [];
		if ($raw) {
			foreach ($fields as $field => $val) {
				if (!is_string($val)) {
					throw new \InvalidArgumentException();
				}
				$field = $this->build_field($field);
				$set[] = "{$field} = {$val}";
			}
		} else {
			foreach ($fields as $field => $val) {
				$field = $this->build_field($field);
				$param_holder = $this->add_prepared_param($val);
				$set[] = "{$field} = {$param_holder}";
			}
		}
		$this->_odk_update = ' ON DUPLICATE KEY UPDATE ' . implode(',', $set);
	
		return $this;
	}
	
	/**
	 * @return Db
	 */
	public function delete($tbl = null)
	{
		$this->reset();
		$this->sql_type = 'delete';
		if ($tbl !== null) {
			$this->table($tbl);
		}
		return $this;
	}
	
	/**
	 * 
	 * @param string|array $vals 单条可用字符串，多条用数组
	 * @param string|array $fields
	 * @return Db
	 */
	public function insert($vals, $fields = null, $ignore = false)
	{
		$this->reset();
		
		$this->sql_type = $ignore ? 'insert ignore' : 'insert';
		
		//设置字段
		if (empty($fields)) {
			$this->_insert_fields = null;
		} else {
			$this->_insert_fields = ' (' . $this->comma_fields($fields) . ')';
		}
		
		//设置数据
		if (is_string($vals)) {
			//单条
			$vals = explode(',', $vals);
			$vals = array_map([$this, 'add_prepared_param'], $vals);
			$this->_insert_data = ' VALUES (' . implode(',', $vals) . ')';
		} elseif (is_array($vals) && !empty($vals)) {
			//单条/多条
			$data = [];
			foreach ($vals as $i => $val) {
				if (!is_numeric($i) && !is_array($val)) {
					$this->_insert_fields = ' (' . $this->comma_fields(array_keys($vals)) . ')';
					$vals = array_map([$this, 'add_prepared_param'], $vals);
					$data[] = implode(',', $vals);
					break;
				}
				if (is_string($val)) {
					$val = explode(',', $val);
				} 
				if (is_array($val) && !empty($val)) {
					$val = array_map([$this, 'add_prepared_param'], $val);
					$data[] = implode(',', $val);
				} else {
					unset($vals[$i]);	//foreach 中进行unset
				}
			}
			$this->_insert_data = ' VALUES (' . implode('),(', $data) . ')';
		}
		
		return $this;
	}
	
	/**
	 * 重置查询
	 * @return Db
	 */
	public function reset()
	{
		$this->sql_type = 'select';	//默认select查询
		
		$this->raw_sql = '';		//原生查询
		
		$this->_tbl = null;
		$this->_select = '*';	//fields
		$this->_where = null;	//condition
		$this->_filter_where = null;	//condition
		$this->_limit = null;
		$this->_orderby = null;	//fields
		$this->_groupby = null;	//fields
		$this->_having = null;	//condition
		$this->_set = null;
		$this->_insert_fields = null;
		$this->_insert_data = null;
		$this->_join = [];
		
		$this->_odk_update = [];	//on duplicate key update
		
		$this->reset_prepared_params();
		
		$this->old_sql_type = null;
		$this->old_select = null;
		
		return $this;
	}
	
	/**
	 * 重置预处理参数
	 * @return Db
	 */
	public function reset_prepared_params()
	{
		$this->prepared_params = [];
		$this->params_count = 0;
		
		return $this;
	}
	
	/**
	 * 构建sql预处理语句
	 * @return string
	 */
	protected function build_statement()
	{
		$sql = '';
		
		switch ($this->sql_type) {
			case 'select' :
			case 'select for update':
				$sql .= 'SELECT ' . $this->_select;
				$sql .= ' FROM ' . $this->_tbl;
				//构建join
				if ($this->_join) {
					$sql .= ' ' . implode(' ', $this->_join);
				}
				//构造where条件
				$sql .= $this->combine_where();
				//group by
				$sql .= $this->_groupby;
				//构建having
				if ($this->_having) {
					$sql .= ' HAVING ' . $this->build_where($this->_having);
				}
				//order by
				$sql .= $this->_orderby;
				//limit
				$sql .= $this->_limit;
				if ($this->sql_type === 'select for update') {
					$sql .= ' FOR UPDATE';
				}
				break;
			case 'insert' : 
				$sql .= 'INSERT INTO ' . $this->_tbl;
				$sql .= $this->_insert_fields;
				$sql .= $this->_insert_data;
				!$this->_odk_update ?: $sql .= $this->_odk_update;
				break;
			case 'insert ignore' : 
				$sql .= 'INSERT IGNORE INTO ' . $this->_tbl;
				$sql .= $this->_insert_fields;
				$sql .= $this->_insert_data;
				break;
			case 'update' : 
				$sql .= 'UPDATE ' . $this->_tbl;
				//构建join
				if ($this->_join) {
					$sql .= ' ' . implode(' ', $this->_join);
				}
				$sql .= $this->_set;
				$sql .= $this->combine_where();
				$sql .= $this->_orderby;
				$sql .= $this->_limit;
				break;
			case 'delete' : 
				$sql .= 'DELETE FROM ' . $this->_tbl;
				$sql .= $this->combine_where();
				$sql .= $this->_orderby;
				$sql .= $this->_limit;
				break;
			case 'raw' :
				$sql .= $this->raw_sql;
				break;
		}
		
		$sql .= ';';
		
		if ($this->enable_log) {
			$this->log($sql, 'prepared-sql');
		}
		
		return $sql;
	}
	
	protected function combine_where()
	{
		$where = '';
		//构造where条件
		$this->_where = $this->build_where($this->_where);
		$this->_filter_where = $this->build_where($this->_filter_where, true);
		if ($this->_where && $this->_filter_where) {
			$where = ' WHERE (' . $this->_where . ') AND (' . $this->_filter_where . ')';
		} elseif ($this->_where || $this->_filter_where) {
			$where = ' WHERE ' . $this->_where . $this->_filter_where;
		} elseif ($this->sql_type == 'delete') {
			$where = ' WHERE 0=1';	
		}
		
		return $where;
	}
	
	/**
	 * 返回上一条查询sql
	 * @param integer $mode 1返回sql的原生debug信息，2返回sql语句（默认），3从缓存信息构建获取
	 * @return string
	 */
	public function get_last_sql($mode = 2)
	{
		if ($mode == 3) {
			$prepare_sql = $this->build_statement();
			$sql = $this->prepareToSql($prepare_sql);
		} elseif ($this->stmt instanceof PDOStatement) {
			if ($mode == 1) {
				ob_start();
				$this->stmt->debugDumpParams();
				$sql = ob_get_clean();
			} elseif ($mode == 2) {
				$prepare_sql = $this->stmt->queryString;
				$sql = $this->prepareToSql($prepare_sql);
			}
		} else {
			$sql = $this->last_sql;
		}
		
		return $sql;
	}
	
	protected function prepareToSql($prepare_sql)
	{
		$patterns = array_keys($this->prepared_params);
		foreach ($patterns as $i => $pattern) {
			$patterns[$i] = '/(' . $pattern . ')([^0-9]{1})/';
		}
		$replaces = array_column($this->prepared_params, 'value');
		foreach ($replaces as $i => $replace) {
			if ($replace === null) {
				$replace = 'NULL';
			} elseif ($replace === 0) {
				$replace = '0';
			} elseif (is_string($replace)) {
				$replace = $this->db->quote($replace);
			}
			$replace .= '${2}';
			$replaces[$i] = $replace;
		}
		$sql = preg_replace($patterns, $replaces, $prepare_sql);
		return $sql;
	}
	
	/**
	 * 注意：
	 * 1、on条件仅支持field字段运算，不支持field-value运算
	 * 2、on条件仅支持AND联合条件，不支持OR
	 * @param string $type
	 * @param string $tbl
	 * @param string|array $on
	 */
	public function join($tbl, $on, $type = 'LEFT JOIN')
	{
		$tbl = $this->build_field($tbl);
		if (is_string($on)) {
			$on = explode(',', $on);
		}
		if (is_array($on) && !empty($on)) {
			$on_condition = [];
			foreach ($on as $field => $another_field) {
				if (is_numeric($field)) {
					list($field, $another_field) = explode('=', strval($another_field));
				}
				if (!$field || !$another_field) {
					continue;
				}
				$on_condition[] = $this->build_field($field) . ' = ' . $this->build_field($another_field);
			}
		}
		
		$this->_join[] = "{$type} {$tbl} ON " . implode(' AND ', $on_condition);
		
		return $this;
	}
	
	/**
	 * 转义
	 * @param string $str
	 * @return string
	 */
	public function quote($str)
	{
		return $this->db->quote($str);
	}
}