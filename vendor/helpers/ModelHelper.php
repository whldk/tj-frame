<?php
namespace vendor\helpers;

use vendor\base\Model;
use vendor\base\Helpers;
use vendor\exceptions\DbErrorException;
use vendor\exceptions\InvalidParamException;

class ModelHelper
{
	/**
	 * 利用自增字段升序遍历
	 * 注意：无论$fields参数是否包含auto_inc字段，返回字段里面永远包含auto_inc字段
	 * @param array $fields
	 * @param int $size
	 */
	public static function iterateByAutoInc($model, $where, array $fields, string $auto_inc = 'id', int $size = 100)
	{
		if (!is_subclass_of($model, Model::class)) {
			return [];
		}
		
		/* @var \vendor\base\Model $model */
		$fields ?: $fields = $model::getFields();
		if (!in_array($auto_inc, $fields)) {
			$fields[] = $auto_inc;
		}
		
		/* @var \vendor\db\Db $db */
		$db = $model::getDb();
		
		$query = $db->select($fields)->from($model::NAME)->where($where)->orderby($auto_inc, 'ASC');
		$lastId = 0;
		$rows = [];
		do {
			$rows = $query->and_where(['>', $auto_inc, $lastId])->limit($size)->result(false);
			if ($rows === null) {
				throw new DbErrorException();
			}
			//返回获取的记录
			yield $rows;
			$lastRow = array_pop($rows);
			if (!$lastRow) {
				break;
			}
			$lastId = $lastRow[$auto_inc];
		} while (count($rows) === $size);
		
		return [];
	}
	
	/**
	 * 利用offset进行升序遍历
	 * 在不锁表的情况下，会出现漏扫或者重复扫描的情况
	 */
	public static function iterate($model, $where, $fields, array $order, int $size = 100)
	{
		if (!is_subclass_of($model, Model::class)) {
			return [];
		}
		
		/* @var \vendor\base\Model $model */
		$fields ?: $fields = $model::getFields();
		
		/* @var \vendor\db\Db $db */
		$db = $model::getDb();
		$query = $db->select($fields)->from($model::NAME)->where($where);
		if ($order) {
			$query->orderby($order);
		}
		
		$offset = 0;
		do {
			$rows = $query->limit($offset, $size)->result(false);
			if ($rows === null) {
				throw new DbErrorException();
			}
			//返回获取的记录
			yield $rows;
			$offset += $size;
		} while (count($rows) === $size);
		
		return [];
	}
	
	/**
	 * @throws \InvalidArgumentException
	 * @return boolean 返回是否进行了exist检查
	 */
	public static function checkExist(&$res, array $config, array $data)
	{
		if (!isset($config['model'])) {
			throw new InvalidParamException(__METHOD__, 'config');
		}
		/* @var \vendor\base\Model $model */
		$model = $config['model'];
		if (!is_subclass_of($model, Model::class)) {
			throw new InvalidParamException(__METHOD__, 'config');
		}
		if (!isset($config['targets']) || !$config['targets']) {
			throw new InvalidParamException(__METHOD__, 'config');
		}
		$target_fields = $config['targets'];
		
		if (!Helpers::when($config, $data)) {
			return false;
		}
		
		//开始执行
		$where = [];
		foreach ($target_fields as $f => $tf) {
			$where[$tf] = $data[$f] ?? null;
		}
		if (isset($config['condition'])) {
			$where[] = $config['condition'];
		}
		
		$result_fields = array_values(ArrayHelper::solid_value('results', $config, $target_fields));
		$limit = $config['limit'] ?? 1;
		
		$res = $model::_select($where, $result_fields, (int)$limit);
		
		return true;
	}
	
	public static function delete(&$res, array $config, array $data)
	{
		if (!isset($config['model'])) {
			throw new InvalidParamException(__METHOD__, 'config');
		}
		/* @var \vendor\base\Model $model */
		$model = $config['model'];
		if (!is_subclass_of($model, Model::class)) {
			throw new InvalidParamException(__METHOD__, 'config');
		}
		if (!isset($config['targets']) || !$config['targets']) {
			throw new InvalidParamException(__METHOD__, 'config');
		}
		$target_fields = $config['targets'];
		
		if (!Helpers::when($config, $data)) {
			return false;
		}
		
		//开始执行
		$where = [];
		foreach ($target_fields as $f => $tf) {
			$where[$tf] = $data[$f] ?? null;
		}
		if (isset($config['condition'])) {
			$where[] = $config['condition'];
		}
		
		$res = $model::_delete($where ?: '0=1');
		
		return true;
	}
	
	/**
	 * 这里的主要缺陷是，如果需要range的条件和目标对象一致，这个排序在不锁定记录的情况下没有意义
	 * @param mixed $model
	 * @param string $field
	 * @param mixed $target_where
	 * @param mixed $range_where
	 * @param int $step
	 * @param int $first
	 * @throws InvalidParamException
	 * @return number
	 */
	public static function incrField($model, string $field, $target_where, $range_where, int $step = 1, int $first = 1)
	{
		if (!is_subclass_of($model, Model::class)) {
			throw new InvalidParamException(__METHOD__, 'model');
		}
		/* @var \vendor\base\Model $model */
		$select_max = $model::getDb()->select("MAX(`{$field}`) AS `max_val`")->from($model::NAME)->where($range_where)->get_last_sql(3);
		$incr_max = "IFNULL(((SELECT `max_val` FROM ($select_max)  AS `sub_select`) + {$step}), $first)";
		$res = $model::_update($target_where, [$field => $incr_max], 0, true);
		return $res;
	}

    /**
     * 小范围跨度内，拖拽排序(order字段必须是INT)
     * 注意：相应字段上，需要建立索引，否则会造成全表锁定
     * @param $model
     * @param string $order_field   排序字段
     * @param array $from_id        调整对象
     * @param array $to_id          对调的对象
     * @param array $range_fields   顺序调整范围字段
     * @param int $tmp_order        用于占位的顺序值，默认取有符号INT32最大
     * @return int|mixed|null       返回影响的记录数
     * @throws DbErrorException
     * @throws InvalidParamException
     * @throws \vendor\exceptions\RollbackException
     * @throws \vendor\exceptions\UnknownException
     * @throws \vendor\exceptions\UserErrorException
     */
	public static function dragSort($model, string $order_field, array $from_id, array $to_id, array $range_fields, int $tmp_order = MAX_INT32)
	{
		if (!is_subclass_of($model, Model::class)) {
			throw new InvalidParamException(__METHOD__, 'model');
		}

		if (array_diff(array_keys($from_id), array_keys($to_id))) {
			throw new InvalidParamException(__METHOD__, 'from_id/to_id');
		}

		if (!ArrayHelper::diff_assoc($from_id, $to_id)) {
			return 0;
		}
		
		/* @var \vendor\base\Model $model */
		$res = $model::execInTransaction(function () use ($model, $order_field, $from_id, $to_id, $range_fields, $tmp_order) {
			//@todo 问题：是锁定整个range还是锁定这两个，毕竟这两个也会影响所有的
			$rows = $model::_select_for_update(['OR', $from_id, $to_id], array_merge($range_fields, array_keys($from_id), [$order_field]));

			if (count($rows) !== 2) {
                return false;
			    //throw new InvalidParamException(__METHOD__, 'from_id/to_id');
			}
			//获取顺序调整范围
			$range_where = array_intersect_key($rows[0], array_fill_keys($range_fields, null));

			if (ArrayHelper::diff_assoc($range_where, $rows[1])) {
			    return false;
				//throw new InvalidParamException(__METHOD__, 'from_id/to_id');
			}
			$range_where ?: $range_where = '1=1';

			//获取两方顺序
			if (ArrayHelper::diff_assoc($from_id, $rows[0])) {
				$from_order = (int)$rows[1][$order_field];
				$to_order = (int)$rows[0][$order_field];
			} else {
				$from_order = (int)$rows[0][$order_field];
				$to_order = (int)$rows[1][$order_field];
			}
			//占位、移动影响的其他记录（包含被调对象）、获取真正的顺序
			$tmp_take = [$order_field => $tmp_order];
			$take = [$order_field => $to_order];
			$take_where = $from_id;
			if ($from_order < $to_order) {
				$move = [$order_field => "`{$order_field}` - 1"];
				$move_where = [['>', $order_field, $from_order], ['<=', $order_field, $to_order]];
			} else {
				$move = [$order_field => "`{$order_field}` + 1"];
				$move_where = [['>=', $order_field, $to_order], ['<', $order_field, $from_order]];
			}
			
			$model::_update([$range_where, $take_where], $tmp_take);
			return $model::_update([$range_where, $move_where], $move, 0, true) + $model::_update([$range_where, $take_where], $take);
		});
		return $res;
	}
}