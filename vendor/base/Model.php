<?php
namespace vendor\base;

use vendor\hashids\Hashids;
use vendor\db\Db;
use vendor\base\ErrorTrait;
use vendor\base\AppTrait;
use vendor\base\UploadManager;
use vendor\exceptions\RollbackException;
use vendor\exceptions\DbErrorException;
use vendor\exceptions\UnknownException;
use vendor\exceptions\UserErrorException;
use vendor\exceptions\InvalidConfigException;

/**
 * @method \vendor\db\Db getDb()
 * @property \vendor\db\Db $db
 */
class Model
{
	use ErrorTrait, AppTrait;
	
	const ERR_EMPTY = 1;
	const ERR_VALID = 2;
	const ERR_READONLY = 3;
	const ERR_REPEAT = 4;
	const ERR_LEN = 5;
	
	const NAME = '';
	const MODULE = '';
	
	const PAGESIZE = 10;
	
	const MAX_RES_SIZE = 1000;
	
	/**
	 * 例如
	 * [
	 * 		'auto_inc' => '_id',
	 *		'hash_id' => 'id',
	 *		'id' => ['id', 'case_id'],
	 * ]
	 * @var array
	 */
	protected static $sets = [];
	
	protected static $fields = [];
	
	public function __construct()
	{
		if (!isset(static::$sets['auto_inc']) && isset(static::$sets['hash_id'])) {
			throw new InvalidConfigException();
		}
	}
	
	public static function fields()
	{
		return static::$fields;
	}
	
	public static function listFields()
	{
		return array_keys(static::$fields);
	}
	
	public static function getFields()
	{
		return array_keys(static::$fields);
	}
	
	public static function alias_fields($alias = null, $specify = [], $fields = null, $tbl = null)
	{
		$tbl !== null ?: $tbl = static::NAME;
		$specify = (array)$specify;
		
		if (!$fields) {
			$fields = array_keys(static::fields());
		} else {
			$fields = isset($fields[0]) ? $fields : array_keys($fields);
		}
		
		if (empty($specify)) {
			if ($alias === null) {
				foreach ($fields as $i => $k) {
					$fields[$i] = "{$tbl}.{$k}";
				}
			} else {
				foreach ($fields as $i => $k) {
					$fields[$i] = "{$tbl}.{$k} as {$alias}_{$k}";
				}
			}
		} else {
			if ($alias === null) {
				foreach ($fields as $i => $k) {
					if (in_array($k, $specify)) {
						$fields[$i] = "{$tbl}.{$k}";
					}
				}
			} else {
				foreach ($fields as $i => $k) {
					if (in_array($k, $specify)) {
						$fields[$i] = "{$tbl}.{$k} as {$alias}_{$k}";
					}
				}
			}
		}
	
		return $fields;
	}
	
	/**
	 * @var Hashids
	 */
	private static $hashids;
	
	final public static function hashids()
	{
		if (!isset(self::$hashids[static::NAME])) {
			self::$hashids[static::NAME] = new Hashids(md5(static::NAME));
		}
		return self::$hashids[static::NAME];
	}
	
	public static function getUploadDir()
	{
		return static::NAME . '/' . date('Y/m');
	}
	
	/**
	 * $fields are filled by relative urls after files being uploaded
	 * @return boolean
	 */
	public function upload(&$fields, $mimes = null, $sizes = [], $md5Fields = [], $saveNames = [], $keepFileInfo = false, $trash = true)
	{
		$upload = new UploadManager(static::getUploadDir());
		$res = $upload->upload($fields, $mimes, $sizes, $md5Fields, $saveNames, $keepFileInfo, $trash);
		if ($res === false) {
			$this->errors += $upload->errors();
		}
		$urls = $upload->getUrls();
		foreach ($fields as $k => $v) {
			if (isset($urls[$k])) {
				 $fields[$k] =  $urls[$k];
			}
		}
		return $res;
	}
	
	public static function search(&$search, $filter)
	{
		if (!$search) {
			$search = [];
		} else {
			$tmp = (array)$search;
			$search = [];
			foreach ($tmp as $fd => $v) {
				if ($v && key_exists($fd, $filter)) {
					$field = $filter[$fd];
					$end = strlen($field) - 1;
					$prefix = $field[0];
					$suffix = $field[$end];
					if ($prefix === '%' && $suffix !== '%') {
						$side = 'left';
					} elseif ($prefix !== '%' && $suffix === '%') {
						$side = 'right';
					} else {
						$side = 'both';
					}
					$field = trim($field, '%');
					$search[] = ['like', $field, $v, $side];
				}
			}
		}
	}
	
	public static function order(&$order, $filter)
	{
		if (!$order) {
			$order = [];
		} else {
			$tmp = (array)$order;
			$order = [];
			foreach ($tmp as $fd => $direction) {
				if (!key_exists($fd, $filter)) {
					continue;
				}
				$direction = strtoupper($direction);
				$direction === 'ASC' || $direction === 'DESC' ?: $direction = 'ASC';
				$order[] = [$filter[$fd], $direction];
			}
		}
	}

    /**
     * 增减字段
     * @param mixed $where
     * @param string $field
     * @param number $num 负数为减
     * @return boolean
     */
     public static function _incr($where, array $incr)
    {
        $res = self::_update($where, $incr);
        return !!$res;
    }
	
	public static function page($query, &$page, &$size, &$page_info, $fields = '*')
	{
		$size = (int)$size;
		$page = (int)$page;
		
		$size >= 1 && $size <= static::MAX_RES_SIZE ?: $size =  static::PAGESIZE;
		$page >= 1 ?: $page = 1;
		
		$offset = $size * ($page - 1);
		
		$total = $query->count($fields)->result(false);
		$total = $total ? (int)$total[0]['count'] : 0;
		
		$page_info = [
			'pagesize' => $size,
			'page' => $page,
			'total_page' => ceil($total/$size),
			'total' => $total,
		];
		
		if ($offset >= $total) {
			$page_info['_list'] = [];
		}
		
		return $offset;
	}
	
	/**
	 * 事务内调用，并处理错误
	 * @param array|string $callback
	 * @param array $params
	 * @throws RollbackException
	 * @throws DbErrorException
	 * @throws UnknownException
	 * @throws UserErrorException
	 * @throws \Exception
	 */
	public function callInTransaction($callback, $params = [])
	{
		$res = null;
		$db = static::getDb();
		
		$db->begin_transaction([static::class, 'throwDbException']);
		try {
			$res = call_user_func_array($callback, $params);
			if ($res === null) {
				throw new UnknownException();
			}
			$db->commit();			//commit
		} catch (RollbackException $e) {
			//回滚异常：手动深层回滚
			$db->rollback();		//rollback
		} catch (DbErrorException $e) {
			//db异常：因db错误回滚
			$db->rollback(false);	//rollback
			throw $e;
		} catch (UnknownException $e) {
			//未知异常：因未知原因抛异常回滚
			$db->rollback(false);		//rollback
			//检查是否有错误
			if (self::$last_error) {
				$this->mergeLastError();
			}
			if ($db->inTransaction()) {
				if ($this->errors) {
					throw new UserErrorException($this->errors);
				} else {
					throw $e;
				}
			} else {
				$res = null;
			}
		} catch (UserErrorException $e) {
			//因用户原因抛异常
			$db->rollback(false);		//rollback
			$this->mergeLastError();
			if ($db->inTransaction()) {
				throw $e;
			} else {
				$res = null;
			}
		} catch (\Exception $e) {
			$db->rollback(false);		//rollback
			throw $e;
		}
		
		return $res;
	}
	
	/**
	 * @see Model::callInTransaction
	 * 静态版
	 * @throws RollbackException
	 * @throws DbErrorException
	 * @throws UnknownException
	 * @throws UserErrorException
	 * @throws \Exception
	 */
	public static function execInTransaction($callback, $params = [])
	{
		$res = null;
		$db = static::getDb();
		
		$db->begin_transaction([static::class, 'throwDbException']);
		try {
			$res = call_user_func_array($callback, $params);
			if ($res === null) {
				throw new UnknownException();
			}
			$db->commit();			//commit
		} catch (RollbackException $e) {
			//回滚异常：手动深层回滚
			$db->rollback();		//rollback
		} catch (DbErrorException $e) {
			//db异常：因db错误回滚
			$db->rollback(false);	//rollback
			throw $e;
		} catch (UnknownException $e) {
			//未知异常：因未知原因抛异常回滚
			$db->rollback(false);		//rollback
			if ($db->inTransaction()) {
				if (self::$last_error) {
					throw new UserErrorException(self::$last_error);
				} else {
					throw $e;
				}
			} else {
				$res = null;
			}
		} catch (UserErrorException $e) {
			//因用户原因抛异常
			$db->rollback(false);		//rollback
			if ($db->inTransaction()) {
				throw $e;
			} else {
				$res = null;
			}
		} catch (\Exception $e) {
			$db->rollback(false);		//rollback
			throw $e;
		}
		
		return $res;
	}
	
	public static function throwDbException()
	{
		throw new DbErrorException();
	}
	
	/**
	 * 注意数量问题
	 */
	final public static function _select($pack, $fields, $num = null, $order = null)
	{
		$fields ?: $fields = static::getFields();
		$query = static::getDb()->select($fields)->from(static::NAME)
			->where($pack);

        if ($order !== null) {
            $query->orderby($order);
        }

		if ($num !== null) {
			$num = (int)$num;
			$query->limit($num);
		}

		$res = $query->result();
		
		if ($res === null) {
			self::throwDbException();
		}
		
		return $num === 1 ? ($res ? $res[0] : []) : $res;
	}
	
	final public static function _batch_insert($inserts, $fields, $chunkSize = 100)
	{
		$inserts = (array)$inserts;
		if (!$inserts) {
			return 0;
		}
		
		$res = self::execInTransaction(function () use ($inserts, $fields, $chunkSize) {
			$db = static::getDb();
			$inserts = array_chunk($inserts, $chunkSize);
			$ignore = isset(static::$sets['auto_inc']);
			
			$res = 0;
			foreach ($inserts as $batch_insert) {
				$tmp = $db->insert($batch_insert, $fields, $ignore)->table(static::NAME)->result();
				$res += $tmp;
			}
			
			return $res;
		});
		
		return $res;
	}
	
	final public static function _insert($fields, $dku = null)
	{
		$query = static::getDb()->insert($fields, null)->table(static::NAME);
		
		if (!isset(static::$sets['auto_inc'])) {
			$query->on_duplicate_key_update($dku ?: $fields);
		}
		
		$res = $query->result();
		
		if ($res === null) {
			self::throwDbException();
		}
		
		return $res;
	}
	
	final public static function _update($where, $vals, $limit = null)
	{
		if (!$where || !$vals) {
			return 0;
		}
		
		$query = static::getDb()->update(static::NAME)
			->set($vals)->where($where);
		
		if ($limit) {
			$query->limit((int)$limit);
		}
		
		$res = $query->result();
		
		if ($res === null) {
			self::throwDbException();
		}
		
		return $res;
	}
	
	final public static function _delete($where)
	{
		if (!$where) {
			return 0;
		}
		
		$res = static::getDb()->delete(static::NAME)
			->where($where)->result();
		
		if ($res === null) {
			self::throwDbException();
		}
		
		return $res;
			
	}
}