<?php namespace common\models;

use vendor\base\ValidateModel;
use vendor\helpers\ModelHelper;

abstract class CategoryModel extends ValidateModel
{
	const STATUS_DELETED = 0;
	const STATUS_ACTIVE = 1;
	
	const MAX_LEVEL = 3;
	
	public static $statuses = [self::STATUS_ACTIVE, self::STATUS_DELETED];
	
	protected static $sets = [
			'auto_inc' => '_id',
			'hash_id' => 'id',
			'id' => ['id'],
	];
	
	protected static $fields = [
			'id' => null,
			'pid' => '',
			'level' => -1,
			'name' => '',
			'order' => 0,
			'status' => self::STATUS_ACTIVE,
            'icon' => ''
	];
	
	protected static $filters = [
			'before' => [
					's' => ['name', 'pid'],
					'i' => ['level', 'status'],
					'ignore' => ['order'],
                    'img' => ['icon']
			],
	];
	
	protected static $validates = [];
	
	public static function validates()
	{
		if (!static::$validates) {
			static::$validates = [
					'require' => ['name'],
					'readonly' => ['pid', 'level'],
					'filter' => [
							'pid' => [
									'callback' => [static::class, 'validatePid'],
									'args' => ['level'],
									'results' => ['level'],
							]
					],
					'string' =>  [
							'name' => ['min' => 0, 'max' => 50, 'truncate' => false],
					],
					'range' => [
							'status' => self::$statuses
					],
					'repeat' => [['pid', 'level', 'name']],
			];
			self::orderValidates(static::$validates);
		}
		return static::$validates;
	}
	
	protected static $constraints = [];
	
	public static function constraints()
	{
		if (!static::$constraints) {
			static::$constraints = [
					'id' => [
							[
									'model' => static::class,
									'targets' => ['id' => 'pid'],
									'!when' => ['level' => -1],
							]
					],
			];
		}
		return static::$constraints;
	}
	
	public static function validatePid($level, $pid)
	{
		$pInfo = self::_select_one([['>=', 'level', 1], 'status' => self::STATUS_ACTIVE, 'id' => $pid], 'level');

		if (!$pInfo) {
			return false;
		}

		if ($level == -1) {
			return true;
		}

		$level = $pInfo['level'] >= static::MAX_LEVEL - 1 ? -1 : $pInfo['level'] + 1;

		return [$level];
	}
	
	public function _set($id, $vals = [])
	{
		$res = $this->internal_set(['id' => $id], $vals);
		return $res;
	}
	
	protected function after_insert($fields, $res)
	{
		$res = ModelHelper::incrField(static::class, 'order', ['id' => $fields['id']], ['pid' => $fields['pid']]);
		return $res;
	}
	
	public function set_order(string $id, string $target_id = '')
	{
		$res = ModelHelper::dragSort(static::class, 'order', ['id' => $id], ['id' => $target_id], ['pid']);
		if (!$res) {
		    return self::addError('order', self::ERR_VALID);
        }
		return $res;
	}
	
	public static function _get($id, $fields = null)
	{
		$res = self::_select(['id' => $id], $fields);
		return $res;
	}
	
	public static function getByPid($pid, $status = null, $order = [], $all = false, $fields = null, &$breadCrumbs = [])
	{
		$pid = (string)$pid;
		
		self::order($order);
		
		$where = ['pid' => $pid];
		if ($status !== null) {
			$where['status'] = $status;
		}
		
		$res = static::get_by_pid($where, $fields ?: static::getFields(), $order, $all, $breadCrumbs);
		
		return $res;
	}
	
	public static function get_by_pid($where, $fields, $order, $all, &$breadCrumbs = [])
	{
		$res = self::_select($where, $fields, 0, $order);
		if ($all && $res) {
			if ($breadCrumbs) {
				$row = reset($res);
				$end = end($breadCrumbs);
				if ($end['level'] != -1 && $end['id'] == $row['pid']) {
					$breadCrumbs[$row['id']] = $row;
				}
			}
			foreach ($res as &$row) {
				if ($row['level'] == -1) {
					continue;
				}
				$where['pid'] = $row['id'];
				$row['sub_categories'] = self::get_by_pid($where, $fields, $order, $all, $breadCrumbs);
			}
		}
		return $res;
	}

    /**
     * 获取某一分类下面的所有level=-1的子分类
     * @param $cid
     * @param array $search
     * @return array
     */
	public static function getAllLeafCids($cid, $search = [])
	{
		$cid = (string)$cid;
		self::search($search, ['name' => 'name']);
		
		$cids = [$cid];
		
		$candidateCids = [$cid];
		
		$where = $search;
		$where['pid'] = &$candidateCids;
		
		do {
			$nextCandidateCids = self::_select($where, ['id', 'level']);
			if (!$nextCandidateCids) {
				break;
			}
			
			$candidateCids = [];
			foreach ($nextCandidateCids as $nextCandidateCid) {
				if ($nextCandidateCid['level'] == -1) {
					$cids[] = $nextCandidateCid['id'];
				} else {
					$candidateCids[] = $nextCandidateCid['id'];
				}
			}
		} while ($candidateCids);
		
		return $cids;
	}
	
	protected static $searchFilter = ['name' => 'name'];
	
	protected static $priorOrder = [];
	protected static $defaultOrder = [['order', 'ASC']];
	protected static $orderFilter = [];
	
	protected static $matchFilter = ['status'];
	protected static $mustMatchFilter = ['pid'];
	
	protected static $rangeFilter = [];
	
}