<?php
namespace vendor\base;

use vendor\db\Db;
use vendor\exceptions\DbErrorException;
use vendor\exceptions\InvalidConfigException;
use vendor\exceptions\UnknownException;

class Validators
{
	public static function requireValidate($fields, $vals, $allowedEmpties = [])
	{
		$fields = (array)$fields;
		foreach ($fields as $key => $field) {
		    if ($key === 'msg') {
                continue;
            }
			if (key_exists($field, $vals)
					 && (	$vals[$field] || 
					 		is_int($vals[$field]) || is_float($vals[$field]) ||
					 		is_string($vals[$field]) && strlen(trim($vals[$field])) || 
					 		(isset($allowedEmpties[$field]) && in_array($vals[$field], $allowedEmpties[$field], true))
					 )
			) {
				continue;
			} else {
				return $field;
			}
		}
		return true;		
	}
	
	/**
	 * @param array $fields 
	 * [
	 * 		'field1' => ['table' => '', 'target_fields' => [], 'condition' => [], 'result_fields' => [], 'allow_null' => false]
	 * 		'field2' => ['callback' => '', 'target_fields' => ['' => ''], 'allow_null' => false],
	 * ]
	 * @param Db $db
	 * @throws DbErrorException
	 * @return null|string|true
	 */
	public static function existValidate($configs, $vals, $db, $existReturn = false, &$allResults = null)
	{
		$needAllResults = $allResults !== null;
		foreach ($configs as $field => $v) {
			if (isset($v['allow_null']) && $v['allow_null'] && $vals[$field] === null) {
				$res = true;
			} elseif (isset($v['table'])) {
				if (isset($v['when'])) {
					foreach ($v['when'] as $f => $tv) {

						if (!key_exists($f, $vals) || $vals[$f] != $tv) {
							continue 2;
						}
					}
				}
				if (isset($v['!when'])) {
					foreach ($v['!when'] as $f => $tv) {
						if (key_exists($f, $vals) && $vals[$f] == $tv) {
							continue 2;
						}
					}
				}
				isset($v['target_fields']) ?: $v['target_fields'] = [$field => $field];
				$condition = [];
				foreach ($v['target_fields'] as $f => $tf) {
					$condition[$tf] = isset($vals[$f]) ? $vals[$f] : null;
				}
				if (isset($v['condition'])) {
					$condition[] = $v['condition']; 
				}
				isset($v['result_fields']) ?: $v['result_fields'] = $v['target_fields'];
				$v['limit'] = isset($v['limit']) ? (int)$v['limit'] : 1;
				$res = $db->select($v['result_fields'])->from($v['table'])->where($condition)->limit($v['limit'])->result();
				if ($res === null) {
					throw new DbErrorException();	//db error
				}
			} elseif (isset($v['callback'])) {
				if (isset($v['args'])) {
					foreach ($v['args'] as $f) {
						$args[] = isset($vals[$f]) ? $vals[$f] : null;
					}
				}
				//res==null means unknown error
				$res = call_user_func_array($v['callback'], $args);
			} else {
				//unknown error
				throw new InvalidConfigException();
			}
			if ($res === null) {
				//unknown error
				throw new UnknownException();
			}
			if (($res && $existReturn) || (!$res && !$existReturn)) {
				return $field;
			}
			if ($needAllResults || isset($v['res_callback'])) {
				$allResults[$field] = $res;
			}
		}
		return true;
	}
	
	/**
	 *  @param array $fields ['field' => ['min' => 0, 'max' => 0, 'truncate' => false]]
	 */
	public static function lengthValidate($fields, &$vals)
	{
		foreach ($fields as $field => $v) {
		    if ($field === 'msg') {
		        continue;
            }
			$len = mb_strlen($vals[$field]);
;
			if (isset($v['min']) && $len < $v['min']) {
			    if (isset($v['msg'])) {
			        if (isset($v['msg'][$field])) {
			            return [$field, $v['msg'][$field]];
                    }
                }
				return $field;
			}
			if (isset($v['max']) && $len > $v['max']) {
				if (!isset($v['truncate']) || !$v['truncate']) {
                    if (isset($v['msg'])) {
                        if (isset($v['msg'][$field])) {
                            return [$field, $v['msg'][$field]];
                        }
                    }
					return $field;
				}
				$vals[$field] = mb_substr($vals[$field], 0, $v['max']);
			}
		}
		return true;
	}
	
	/**
	 * @param array $fields ['field' => ['min' => 0, 'max' => 0, 'fix' => false]]
	 */
	public static function numberValidate($fields, &$vals)
	{
		foreach ($fields as $field => $v) {
			if (isset($v['min']) && $vals[$field] < $v['min']) {
				if (!isset($v['fix']) || !$v['fix']) {
					return $field;
				}
				$v[$field] = $v['min'];
			} elseif (isset($v['max']) && $vals[$field] > $v['max']) {
				if (!isset($v['fix']) || !$v['fix']) {
					return $field;
				}
				$v[$field] = $v['max'];
			}
		}
		return true;
	}
}