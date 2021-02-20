<?php
namespace vendor\db;

use vendor\exceptions\RollbackException;
use vendor\exceptions\DbErrorException;

/**
 * @property \PDO $db
 */
trait PdoTrait
{
	/**
	 * 执行sql并取结果，select默认按照assoc数组方式返回
	 * @return null|array|integer 出错时null，select查询array，其他int
	 */
	public function result($reset = true)
	{
		$result = null;
	
		//profile start
		if ($this->enable_log) {
			$start_micro_time = microtime(true) * 1000;
		}
	
		$this->stmt = $this->db->prepare($this->build_statement());
		if ($this->stmt) {
			foreach ($this->prepared_params as $param_holder => $param) {
				$this->stmt->bindParam($param_holder, $param['value'], $param['type']);
			}
			if ($this->stmt->closeCursor() === true && $this->stmt->execute() === true) {
				if ($this->sql_type == 'select' || $this->sql_type == 'select for update') {
					$result = $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
					$result !== false ?: $result = null;
				} else {
					$result = $this->stmt->rowCount();
				}
			} elseif ($this->enable_log) {
				$this->log('sql execute failed.', 'error', $this->stmt->errorCode(), implode(';', $this->stmt->errorInfo()));
			}
		} elseif ($this->enable_log) {
			$this->log('prepare statement failed.', 'error', $this->db->errorCode(), implode(';', $this->db->errorInfo()));
		}
	
		//profile end
		if ($this->enable_log) {
			$end_micro_time = microtime(true) * 1000;
		}
		//log last sql and excute time in micro seconds
		if ($this->enable_log) {
			$last_sql = $this->get_last_sql();
			$this->log($last_sql, 'sql');
			$profile = $end_micro_time - $start_micro_time;
			$this->log("excute time : {$profile} ms", 'profile');
		}
	
		//重制查询
		if ($reset) {
			$this->reset();
		} else {
			if ($this->old_sql_type) {
				$this->sql_type = $this->old_sql_type;
				$this->old_sql_type = null;
			}
			if ($this->old_select) {
				$this->_select = $this->old_select;
				$this->old_select = null;
			}
		}
	
		if ($result === null && $this->db->inTransaction() && $this->callbackOnError !== null) {
			call_user_func_array($this->callbackOnError[$this->transactionLevel][0], $this->callbackOnError[$this->transactionLevel][1]);
		}
		
		return $result;
	}
	
	/**
	 * 返回上一个插入id或序列值
	 * @return string|integer
	 */
	public function get_last_insert_id()
	{
		return $this->db->lastInsertId();
	}
	
	protected $callbackOnError = null;
	protected $defaultCallbackOnError = null;
	
	public function inTransaction()
	{
		return $this->db->inTransaction();
	}
	
	private $transactionLevel = 0;
	
	/**
	 * @return boolean
	 */
	public function begin_transaction($callbackOnError = null, $callbackParams = [])
	{
		$this->transactionLevel++;
		
		if ($callbackOnError === null) {
			$this->callbackOnError[$this->transactionLevel] = $this->defaultCallbackOnError;
		} else {
			$this->callbackOnError[$this->transactionLevel] = [$callbackOnError, (array)$callbackParams];
		}
		
		if ($this->transactionLevel === 1 || !$this->db->inTransaction()) {
			if ($this->db->beginTransaction() === false) {
				throw new DbErrorException('PDO beginTransaction error.');
			}
		}
		
		return true;
	}
	
	/**
	 * @return boolean
	 */
	public function commit()
	{
		unset($this->callbackOnError[$this->transactionLevel]);
		
		$this->transactionLevel--;
		
		if ($this->transactionLevel === 0) {
			if ($this->db->commit() === false) {
				throw new DbErrorException('PDO commit error.');
			}
		}
		
		return true;
	}
	
	/**
	 * @param boolean $continue
	 * @return boolean
	 */
	public function rollback($continue = true)
	{
		unset($this->callbackOnError[$this->transactionLevel]);
		
		$this->transactionLevel--;
		
		if ($this->transactionLevel === 0) {
			if ($this->db->rollBack() === false) {
				throw new DbErrorException('PDO rollback error.');
			}
		} elseif ($continue) {
			//rollback all the way to the top level
			throw new RollbackException('Still in deep transaction level, rollbacking...');
		}
		
		return true;
	}
	
	/**
	 * 执行查询sql
	 * @param string $sql
	 * @return array
	 */
	public function query($sql)
	{
		$result = false;
	
		$this->last_sql = $sql;
	
		//profile start
		if ($this->enable_log) {
			$start_micro_time = microtime(true) * 1000;
		}
	
		$stmt = $this->db->query($sql);
		if ($stmt) {
			$result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
			$stmt->closeCursor();
		}
	
		//profile end
		if ($this->enable_log) {
			$end_micro_time = microtime(true) * 1000;
			
			//log last sql and excute time in micro seconds
			$this->log($sql, 'sql');
			$profile = $end_micro_time - $start_micro_time;
			$this->log("excute time : {$profile} ms", 'profile');
			
			//log error
			if ($result === false) {
				$this->log('prepare statement failed.', 'error', $this->db->errorCode(), implode(';', $this->db->errorInfo()));
			}
		}
	
		if ($result === false && $this->db->inTransaction() && $this->callbackOnError !== null) {
			call_user_func_array($this->callbackOnError[$this->transactionLevel][0], $this->callbackOnError[$this->transactionLevel][1]);
		}
		
		return $result;
	}
	
	/**
	 * 执行操作sql
	 * @param string $sql
	 * @return integer|false
	 */
	public function execute($sql)
	{
		$result = false;
		
		$this->last_sql = $sql;
	
		//profile start
		if ($this->enable_log) {
			$start_micro_time = microtime(true) * 1000;
		}
	
		$result = $this->db->exec($sql);
	
		//profile end
		if ($this->enable_log) {
			$end_micro_time = microtime(true) * 1000;
			
			//log last sql and excute time in micro seconds
			$this->log($sql, 'sql');
			$profile = $end_micro_time - $start_micro_time;
			$this->log("excute time : {$profile} ms", 'profile');
			
			//log error
			if ($result === false) {
				$this->log('prepare statement failed.', 'error', $this->db->errorCode(), implode(';', $this->db->errorInfo()));
			}
		}
		
		if ($result === false && $this->db->inTransaction() && $this->callbackOnError !== null) {
			call_user_func_array($this->callbackOnError[$this->transactionLevel][0], $this->callbackOnError[$this->transactionLevel][1]);
		}
	
		return $result;
	}
}