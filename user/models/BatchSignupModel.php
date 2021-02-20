<?php
namespace user\models;

use user\models\UserModel;
use user\models\StudentModel;
use vendor\base\Model;

class BatchSignupModel extends Model
{
	const NAME = 'user';
	
	const ROW_LIMIT = 5000;

	/**
	 * 列信息名称，用于生成错误信息
	 * @var array
	 */
	protected static $colNames = [
			'username' => '用户名',
			'realname' => '真实姓名',
            'password' => '密码',
			'gender' => '性别',
			'class_name' => '班级名称',
			'grade' => '年级',
	];
	
	/**
	 * 角色分组信息名称，用于生成错误信息
	 * @var array
	 */
	protected static $groupNames = [
			UserModel::GROUP_SCHOOL_ADMIN => '管理员',
			UserModel::GROUP_TEACHER => '教师',
			UserModel::GROUP_STUDENT => '学生',
	];
	
	protected $data = [];
	
	protected $school_id;
	protected $group;
	protected $overwrite;
	
	protected $user;
	
	protected $class_map = [];
	protected $class_range = null;	//or array
	
	/**
	 * 按照数字key的顺序排列
	 */
	protected $tpl;
	
	protected static $userTpl = [
			0 => 'username',
			1 => 'realname',
			2 => 'password',
			3 => 'gender',
	];
	
	/**
	 * 学生模板，继承user模板
	 * @var array
	 */
	protected static $studentTpl = [
			0 => 'username',
			1 => 'realname',
			2 => 'password',
			3 => 'gender',
			4 => 'class_name',
			5 => 'grade',
	];
	
	public function getData()
	{
		return $this->data;
	}
	
	/**
	 * 功能：从文件获取数据
	 * 错误：会处理文件错误
	 */
	public function setData($data, $excelFile, $group)
	{
		$group = (int)$group;
		if (!in_array($group, UserModel::$groups)) {
			return $this->addError('group 非法');
		}
		
		$this->group = $group;
		$this->tpl = $group == UserModel::GROUP_STUDENT ? self::$studentTpl : self::$userTpl;
	
		if ($data) {
			$this->data = (array)$data;
			if (count($this->data) > self::ROW_LIMIT) {
				return $this->addError('数据条目超出限制数目:' . self::ROW_LIMIT);
			}
			if (!isset($data[0]) || !is_array($data[0]) || count($data[0]) != count($this->tpl)) {
				return $this->addError('数据列不符合要求');
			}
		} else {
			try {
				$phpExcel = \PHPExcel_IOFactory::load($excelFile);
				$phpExcel->setActiveSheetIndex(0);
				$activeSheet = $phpExcel->getActiveSheet();
				$rowCount = $activeSheet->getHighestDataRow();
					
				//获取有数据的区域
				$colCount = $activeSheet->getHighestDataColumn();
				if (ord($colCount) - ord('A') >= count($this->tpl) - 1) {
					$colCount = chr(ord('A') + count($this->tpl) - 1);
				} else {
					return $this->addError('数据列不符合要求');
				}
				$dimension = 'A2:' .  $colCount . $rowCount;
				//从数据区域获取数据
				$this->data = $activeSheet->rangeToArray($dimension);
				//删除空行
				$this->delEmptyRows();
				//判断行数
				if (count($this->data) > self::ROW_LIMIT) {
					return $this->addError('数据条目超出限制数目:' . self::ROW_LIMIT);
				}
			} catch (\PHPExcel_Reader_Exception $e) {
				return $this->addError('上传的文件不符合要求');
			} catch (\PHPExcel_Exception $e) {
				return $this->addError('上传的文件不符合要求');
			} catch (\Exception $e) {
				return null;
			}
				
		}
		
		return true;
	}
	
	protected function delEmptyRows()
	{
		$this->data = array_values(array_filter($this->data, function ($row) {
			foreach ($row as $v) {
				if (is_null($v) || is_string($v) && trim($v) == '') {
					continue;
				}
				return true;
			}
			return false;
		}));
	}
	
	/**
	 * @param 
	 */
	public function batch_signup($school_id, $class_ids = null, $overwrite = false)
	{
		//如果没有数据，直接返回
		if (!$this->data) {
			return [
					'data' => [],
					'total' => 0,
					'count' => 0,
					'err_lines' => [],
					'err_count' => 0,
			];
		}
		
		$this->school_id = $school_id;
		ksort($this->tpl, SORT_NUMERIC);
		$this->class_range = $class_ids;
		$this->overwrite = $overwrite;
		
		$total = count($this->data);	//总计数
		$count = 0;			//成功计数
		$err_lines = [];	//错误行和信息
		
		if ($this->group == UserModel::GROUP_STUDENT) {
			$method = 'set_student';
		} else {
			$method = 'set_user';
		}
		
		//设置超时时间
		set_time_limit((int)ceil($count / 2));
		foreach ($this->data as $i => $row) {
			$res = false;
			if (is_array($row) && count($row) === count($this->tpl)) {
				$row = array_combine($this->tpl, $row);
				$res = $this->$method($row);
			} else {
				$this->addError('数据列不符合要求');
			}
			
			if ($res !== false) {
				$count++;
			} else {
				$err_lines[$i] = $this->errors;
				$this->clearErrors();
			}
		}
		
		return [
				'data' => $total > $count ? $this->data : [],
				'total' => $total,
				'count' => $count,
				'err_lines' => $err_lines,
				'err_count' => count($err_lines),
		];
	}
	
	/**
	 * 通过班级名称获取班级id
	 * 报错：1、创建失败；2、无权限
	 */
	protected function get_class_id($class_name, $grade)
	{
		if (!isset($this->class_map[$class_name])) {
			$model = new SchoolClassModel();
			$res = $model->batch_set($this->school_id, $class_name, ['grade' => $grade]);
			if ($res === null) {
				//$this->addError('class_name', $model->errors());
				$this->addError("班级{$class_name}创建或更新失败");
				return false;
			}
			
			$res = SchoolClassModel::getByName($this->school_id, $class_name, 'id');
			if ($res) {
				$class_id = $res[0]['id'];
				$this->class_map[$class_name] = $class_id;
			} else {
				//$this->addError('class_name', $model->errors());
				$this->addError("班级获取失败");
				return false;
			}
		}
		
		if (isset($this->class_range) && !in_array($this->class_map[$class_name], $this->class_range)) {
			//$this->addError('class_name', self::ERR_VALID);
			$this->addError("班级'{$class_name}'不属于当前用户所管理，当前用户无权限执行此操作");
			return false;
		}
		
		return $this->class_map[$class_name];
	}
	
	/**
	 * 通过性别或许性别字段值
	 */
	protected function get_gender($str)
	{
		$str = trim($str);
		if (mb_strpos($str, '男') !== false) {
			return UserModel::G_MALE;
		} elseif (mb_strpos($str, '女') !== false) {
			return UserModel::G_FEMALE;
		} else {
			return UserModel::G_UNKNOWN;
		}
	}
	
	/**
	 * 创建或更细用户，返回user_id
	 * @param array $vals
	 * @return boolean
	 */
	protected function set_user($vals)
	{
		$vals['school_id'] = $this->school_id;
		$vals['group'] = $this->group;
		//$vals['password'] = substr($vals['password'], -6);
        $vals['password'] = str_replace(' ', '', $vals['password']);
        $vals['username'] = str_replace(' ', '', $vals['username']);
        if (strlen($vals['password']) < 6) {
			$vals['password'] = UserModel::INIT_PASSWD;
		}
		$vals['gender'] = $this->get_gender($vals['gender']);
		
		$userModel = new UserModel();
		if ($this->overwrite) {
			$res = $userModel->batch_set($this->school_id, $vals['username'], $vals['group'], $vals);
		} else {
			$res = $userModel->_set(null, $vals);
		}
		
		if (!$res) {
			$this->errors = $userModel->errors();
			if (isset($this->errors['username']) && $this->errors['username'] == self::ERR_REPEAT) {
				$this->addError($this->overwrite ? '该用户名已存在其他身份' : '用户名重复，跳过');
			} else {
				if (in_array(self::ERR_EMPTY, $this->errors)) {
					$this->addError('用户名或真实姓名为空');
				} elseif (in_array(self::ERR_LEN, $this->errors)) {
					$this->addError('用户名或真实姓名过长或过短');
				} elseif (in_array(self::ERR_VALID, $this->errors)) {
					$this->addError( '用户名或密码非法');
				} else {
					$this->addError( '插入或更新用户失败');
				}
			}
			$userModel->clearErrors();
			return false;
		}
		
		return $res['id'];
	}
	
	/**
	 * 设置学生的时候，db步骤比较多，采用事务处理
	 */
	protected function set_student($vals)
	{
		$res = $this->callInTransaction(function () use ($vals) {
			//获取用户id
			$user_vals = array_intersect_key($vals, array_fill_keys(self::$userTpl, null));
			$user_id = $this->set_user($user_vals);
			if (!$user_id) {
				throw new \vendor\exceptions\UnknownException();
			}
			
			//获取班级id
			$class_id = $this->get_class_id($vals['class_name'], $vals['grade']);
			if (!$class_id) {
				throw new \vendor\exceptions\UnknownException();
			}
			
			//设置学生班级
			$stuModel = new StudentModel();
			$res = $stuModel->_set($this->school_id, $user_id, $class_id);
			if ($res === null) {
				$this->errors = $stuModel->errors();
				$this->addError('设置学生班级失败');
				
				$stuModel->clearErrors();
				
				throw new \vendor\exceptions\UnknownException();
			}
			
			return true;
		});
		
		return $res === true;
	}
	
}