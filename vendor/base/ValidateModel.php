<?php
namespace vendor\base;

use vendor\exceptions\InvalidConfigException;
use vendor\exceptions\ServerErrorException;

class ValidateModel extends Model
{
	protected static $not_nulls = [];
	
	/**
	 * 例如
	 * [
	 *		'before' => [
	 *			's' => ['name', 'category', 'chief_complaint', 'medical_history'],
	 *			'html' => ['instruction']
	 *			'b' => ['isRed'],
	 * 			'i' => ['inquiry_limit', 'exam_limit', 'test_limit'],
	 * 			'f' => ['height'],
	 * 			'ts' =>  ['ct' => 'created_at', 'mt' => 'updated_at']
	 * 			'img' => ['img'], //[['img']],
	 * 			'map' => [[['name'], 'callback' => []]]
	 * 			'ignore' => [[], 'when'=> []] or ['xxx', 'xxx']	//when/!when
	 *		],
	 *		'after' => [
	 *			'json' => ['feedback'],
	 *		],
	 *	]
	 * @var array
	 */
	protected static $filters = [];
	
	/**
	 * 例如
	 * [
	 *		'require' => [['name', 'gender], 'allowedEmpties' => ['name' => [null]]],
	 *		'readonly' => ['name'],
	 *		'exist' => [
	 *			'field1' => ['table' => '', 'target_fields' => [], 'result_fields' => [], 'res_callback' => [], 'allow_null' => false]
	 *			'field2' => ['callback' => '', 'args' => [0=>'', 1=>'', 2=>''], 'res_callback' => [], 'allow_null' => false]
	 *		],
	 *		'repeat' => ['name'],	// [field1, field2], when/!when
	 *		'range' => ['name' => ['xiaoming', 'xiaohong'], 'gender' => ['male', 'female']],
	 *		'filter' => [[], 'callback' => [], 'args' => ['item_id']],
	 *		'regular' => ['name' => '/^abc$/', 'gender' => '/^abc$/'],
	 *		'string' =>  ['field' => ['min' => 0, 'max' => 0, 'truncate' => false]],
	 *		'number' =>  ['field' => ['min' => 0, 'max' => 0, 'fix' => false]],
	 *	]
	 * @var array
	*/
	protected static $validates = [];

	protected static $vPriority = [
			'require' => 1,
			'readonly' => 1,
			'range' => 2,
			'exist' => 3,
			'filter' => 4,
			'url' => 5,
			'regular' => 5,
			'string' => 5,
			'number' => 5,
			'repeat' => 6
	];
	
	/**
	 * [
	 *		['id' => ['table' => TBL_FORUM_POST, 'target_fields' => ['id' => 'forum_id']]],
	 *		['id' => ['table' => self::NAME, 'target_fields' => ['id' => 'pid']]],
	 * ];
	 */
	protected static $constraints = [];
	
	/**
	 * [
    		'id' => [
    				['table' => TBL_CASE_PATIENT, 'target_fields' => ['id' => 'case_id'], 'when' => []],
    				['table' => TBL_CASE_EXPERT, 'target_fields' => ['id' => 'case_id'], 'when' => []],
    		]
    ]
	 * @var array
	 */
	protected static $cascades = [];
	
	public function __construct()
	{
		parent::__construct();
		if (!isset(static::$sets['id'])) {
			throw new InvalidConfigException();
		}
	}

	final public static function not_nulls()
	{
		return static::$not_nulls;
	}
	
	public static function filters()
	{
		return static::$filters;
	}
	
	/**
	 * @tutorial 可以设置上下文切换validates
	 * validate 的顺序需要按照[vPriority]排序
	 * @see ValidateModel::orderValidates()
	 * @see ValidateModel::$vPriority
	 */
	public static function validates()
	{
		self::orderValidates(static::$validates);
		return static::$validates;
	}
	
	final protected static function orderValidates(&$validates)
	{
		$vp = $validates;
		foreach ($vp as $v => $validate) {
			$vp[$v] = isset(static::$vPriority[$v]) ? static::$vPriority[$v] : 999;
		}
		array_multisort($vp, $validates);
	}
	
	public static function constraints()
	{
		return static::$constraints;
	}
	
	public static function cascades()
	{
		return static::$cascades;
	}
	
	/**
	 * @var ModelFilter $modelFilter
	 */
	protected $modelFilter = null;
	
	protected function modelFilter()
	{
		if (!$this->modelFilter) {
			$this->modelFilter = new ModelFilter($this);
		}
		return $this->modelFilter;
	}
	
	/**
	 * @var ModelValidator $modelValidator
	 */
	protected $modelValidator = null;
	
	protected function modelValidator()
	{
		if (!$this->modelValidator) {
			$this->modelValidator = new ModelValidator($this, static::getDb());
		}
		return $this->modelValidator;
	}
	
	protected function internal_set($pack, $vals)
	{
		$res = null;

		$this->clearErrors();
		
		list($exec, $snapshot) = $this->before_set($pack, $vals);

		switch ($exec) {
			case 'insert' :
				$res = $this->internal_insert($vals + $snapshot);
				break;
			case 'update' :
				$res = $this->internal_update($pack, $snapshot, $vals);
				break;
			case 'delete' :
				$res = $this->internal_delete($pack, $snapshot);
		}
		return $res;
	}
	
	protected function updates_of_insert($fields, $pack, $_id = null)
	{
		return [];
	}
	
	protected function return_of_insert($fields, $pack, $_id = null)
	{
		return [];
	}
	
	protected function okuFields($fields)
	{
		//剔除readonly字段，暂不剔除id字段
		$validates = static::validates();
		$readonly = isset($validates['readonly']) ? array_fill_keys($validates['readonly'], null) : [];
		
		return array_diff_key($fields, $readonly);
	}
	
	protected function internal_insert($fields)
	{
		$res = $this->callInTransaction(function () use ($fields) {
			//先插入
			$this->_insert($fields, $this->okuFields($fields));

			$where = [];
			$updates = [];
			$_id = null;
			$pack = [];
			
			$sets = static::$sets;
			
			if (isset($sets['hash_id'])) {
				$_id = static::getDb()->get_last_insert_id();
				if (!$_id) {
					throw new ServerErrorException();
				}
				$hashid = self::hashids()->encode($_id);
				if (!$hashid) {
					throw new ServerErrorException();
				}
				$where[$sets['auto_inc']] = $_id;
				$updates[$sets['hash_id']] = $hashid;
				$pack[$sets['hash_id']] = $hashid;
			} elseif (isset($sets['auto_inc'])) {
				$_id = static::getDb()->get_last_insert_id();
				if (!$_id) {
					throw new ServerErrorException();
				}
				$where[$sets['auto_inc']] = $_id;
				if (isset($sets['id']) && $sets['id'] === [$sets['auto_inc']]) {
					$pack = [$sets['auto_inc'] => $_id];
				} else {
					$pack = array_intersect_key($fields, array_fill_keys($sets['id'], null));
				}
			} else {
				$pack = array_intersect_key($fields, array_fill_keys($sets['id'], null));
				$where = $pack;
			}
			
			$updates += $this->updates_of_insert($fields, $pack, $_id);
			if ($updates) {
				$this->_update($where, $updates);
				$pack = array_intersect_key($updates, $pack) + $pack;
			}
			
			$res = $pack + $this->return_of_insert($updates + $fields, $pack, $_id);
			
			return $res;
		});
		
		return $res;
	}
	
	protected function internal_update($pack, $snapshot, $vals)
	{
		return $this->_update($pack, $vals);
	}
	
	protected function internal_delete($pack, $snapshot)
	{
		if (!static::cascades()) {
			return $this->_delete($pack);
		}
		
		$res = $this->callInTransaction(function () use ($pack, $snapshot) {
			$cascades = static::cascades();
			$db = static::getDb();
			foreach ($cascades as $id => $refs) {
				if (isset($refs['table']) || isset($refs['callback'])) {
					//规范格式
					$refs = [$refs];
				}
				foreach ($refs as $ref) {
					if (Helpers::when($ref, $snapshot) === false) {
						continue 1;
					}
					if (isset($ref['table']) && isset($ref['target_fields']) && $ref['target_fields']) {
						$condition = [];
						foreach ($ref['target_fields'] as $fd => $tfd) {
							$condition[$tfd] = $snapshot[$fd];
						}
						$res = $db->delete($ref['table'])->where($condition)->result();
					} elseif (isset($ref['callback'])) {
						$args = [];
						if (isset($ref['args'])) {
							foreach ($ref['args'] as $fd) {
								$args[] = isset($snapshot[$fd]) ? $snapshot[$fd] : null;
							}
						}
						if (isset($ref['instance']) && $ref['instance'] === true) {
							$ref['callback'][0] = $this instanceof $ref['callback'][0] ? $this : new $ref['callback'][0];
						}
						$res = call_user_func_array($ref['callback'], $args);
						if ($res === null) {
							if ($ref['callback'][0] instanceof Model) {
								$this->errors = $ref['callback'][0]->errors();
							}
							throw new \Exception();
						}
					} else {
						throw new InvalidConfigException();
					}
				}
			}
			return $this->_delete($pack);
		});
		
		return $res;
	}
	

	protected function get_exec($pack, $vals)
	{
		$exec = null;
		$checkExist = false;
	
		$sets = static::$sets;
		if ($vals === null) {
			$exec = 'delete';
			$checkExist = true;
		} elseif (!isset($sets['auto_inc'])) {
			$checkExist = true;
		} else {
			if (in_array($sets['auto_inc'], $sets['id']) || isset($sets['hash_id']) && in_array($sets['hash_id'], $sets['id'])) {
				if (isset($pack[$sets['auto_inc']]) || isset($sets['hash_id']) && isset($pack[$sets['hash_id']])) {
					$exec = 'update';
					$checkExist = true;
				} else {
					$exec = 'insert';
				}
			} else {
				$checkExist = true;
			}
		}
	
		if ($checkExist === true) {
			$existValidate = [
					$sets['id'][0] => [
							'table' => static::NAME,
							'target_fields' => array_combine($sets['id'], $sets['id']),
							'result_fields' => array_keys(static::fields())]
			];
			$existRow = [];
			$exist = Validators::existValidate($existValidate, $pack, static::getDb(), false, $existRow);
			if ($exist === null) {
				self::throwDbException();
			}
			if ($exist === true) {
				if ($exec === null) {
					$exec = 'update';
				}
			} else {
				if ($exec === null) {
					$exec = 'insert';
				} else {
					//return $this->addError($sets['id'], self::ERR_VALID);
                    return $this->addError($sets['id'] . ' 非法');
				}
			}
		}
	
		if ($exec === 'insert') {
			$fields = static::fields();
			return [$exec, $fields];
		} else {
			$fields = $existRow[$sets['id'][0]][0];
			$filters = static::filters();
			if (isset($filters['after']['json'])) {
				foreach ($filters['after']['json'] as $fd) {
					$fields[$fd] === '' ?: $fields[$fd] = json_decode($fields[$fd], true);
				}
			}
			return [$exec, $fields];
		}
	}
	
	/**
	 * 因为vals的引用性，vals自身会被修改和过滤
	 * @param array $pack
	 * @param array $vals
	 * @return null|array
	 */
	protected function before_set($pack, &$vals)
	{
		list($exec, $snapshot) = $this->get_exec($pack, $vals);

		switch ($exec) {
			case 'insert' :
				//归并主key
				$vals = $pack + $vals;
				break;
			case 'update' :
				break;
			case 'delete' :
				//验证delete
				return $this->before_delete($snapshot);
			default :
				return null;
		}
		
		$modelFilter = $this->modelFilter();

		//filter before
		$modelFilter->before_filter($snapshot, $vals);

		//validate
		if ($this->validate($exec, $snapshot, $vals) !== true) {
			return null;
		}


		//filter after

		$modelFilter->after_filter($snapshot, $vals);


		return [$exec, $snapshot];
	}
	
	/**
	 * @param array $snapshot
	 * @throws \Exception
	 * @return null|array
	 */
	protected function before_delete($snapshot)
	{
		//检查constraints
		if ($constraints = static::constraints()) {
			isset($constraints[0]) ?: $constraints = [$constraints];
			foreach ($constraints as $constraint) {
				$valid = Validators::existValidate($constraint, $snapshot, static::getDb(), true);
				if ($valid !== true) {
					if (!$valid) {
						//未知错误
						throw new \Exception();
					} else {
					    //return $this->addError($valid . '非法', self::ERR_VALID);
						return $this->addError($valid . '非法');
					}
				}
			}
		}
		
		//删除之前，删除文件
		static::trashFiles($snapshot);
		
		return ['delete', $snapshot];
	}
	
	/**
	 * @todo 删除其他类型的文件，除了图片
	 * @param array $snapshot
	 */
	protected static function trashFiles($snapshot)
	{
		//trash file
		$filters = static::filters();
		if (isset($filters['before']['img'])) {
			$imgs = is_array($filters['before']['img'][0]) ? $filters['before']['img'][0] : $filters['before']['img'];
			foreach ($imgs as $img) {
				UploadManager::trash($snapshot[$img]);
			}
		}
	}
	
	/**
	 * 此处可以重写方法，并且切换上下文，获取不同的validates设置值
	 * @see ValidateModel::validates()
	 * @param string $exec
	 * @param array $snapshot
	 * @param array $vals
	 * @return boolean
	 */
	public function validate($exec, $snapshot, &$vals)
	{
		$validates = static::validates();
		return $this->internal_validate($validates, $exec, $snapshot, $vals);
	}
	
	/**
	 * 根据上下文或当前值，动态修改$validates，再调用父类的次方法
	 * @param array $validates
	 * @param string $exec
	 * @param array $snapshot
	 * @param array $vals
	 * @return boolean
	 */
	protected function internal_validate($validates, $exec, $snapshot, &$vals)
	{
		$validator = $this->modelValidator();
		
		$this->clearErrors();
		$res = $validator->validate($validates, $exec, $snapshot, $vals);
		if ($res === false) {
			if (!$this->errors) {
				$this->errors = $validator->errors();
				$this->setLastError($this->errors);
			}
		}
		
		return $res;
	}
	
}