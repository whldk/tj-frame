<?php

namespace vendor\base;

/**
 * @property \vendor\db\Db $db
 */
class DbSession extends Session
{
	use AppTrait;

	public $sessionTable = 'session';
	protected $idField = 'id';
	protected $extraFields = [];
	protected $basicFields = [];
	protected $requireFields = [];

	protected $useTimestamp = false;

	public function __construct($config = [])
	{
		parent::__construct($config);
		foreach ($config as $k => $v) {
			switch ($k) {
				case 'sessionTable' :
				case 'idField' :
					$this->$k = strval($v);
					break;
				case 'extraFields' :
				case 'requireFields' :
					$this->$k = (array)$v;
			}
		}
		$this->basicFields = [&$this->idField, 'expire', 'data'];
		$this->extraFields = array_diff_key($this->extraFields, array_fill_keys($this->basicFields, null));
	}

	public function setIdField($idField)
	{
		if ($this->idField === $idField) {
			return;
		}

		$this->extraFields[$this->idField] = null;
		unset($this->extraFields[$idField]);

		$this->idField = $idField;
	}

    public function setExtraFields($key, $value)
    {
    	if (key_exists($key, $this->extraFields)) {
    		$this->extraFields[$key] = $value;
    	}
    }

    public function getExtraFields($key = null)
    {
    	if ($key === null) {
    		return $this->extraFields;
    	} elseif (key_exists($key, $this->extraFields)) {
    		return $this->extraFields[$key];
    	}
    	return null;
    }

    /**
     * @return boolean always true
     */
    public function getUseCustomStorage()
    {
        return true;
    }


    /**
	 * Updates the current session ID with a newly generated one .
	 * Please refer to <http://php.net/session_regenerate_id> for more details.
	 * @param bool $deleteOldSession Whether to delete the old associated session file or not.
	 */
	public function regenerateID($deleteOldSession = false)
	{
		$oldID = session_id();
		// if no session is started, there is nothing to regenerate
		if (empty($oldID)) {
			return;
		}

		parent::regenerateID(false);
		$newID = session_id();
		// if session id regeneration failed, no need to create/update it.
		if (empty($newID)) {
			//warning : Failed to generate new session ID
			return;
		}

		$exist = $this->_select($oldID);
		if (!$exist) {
			// shouldn't reach here normally
			$fields = $this->composeFields('');
			$this->_insert($newID, $fields);
			return;
		}

		$deleteOldSession ? $this->_update($oldID, [$this->idField => $newID]) : $this->_insert($newID, $exist);
	}

	/**
	 * Session write handler.
	 * Do not call this method directly.
	 * @param string $id session ID
	 * @param string $data session data
	 * @return bool whether session write is successful
	 */
	public function writeSession($id, $data)
	{
		// exception must be caught in session write handler
		// http://us.php.net/manual/en/function.session-set-save-handler.php#refsect1-function.session-set-save-handler-notes
		try {
            $fields = $this->composeFields($data);
            $this->_select($id) ? $this->_update($id, $fields) : $this->_insert($id, $fields);
		} catch (\Exception $e) {
			error_log($e->__toString());
			return false;
		}

		return true;
	}

    /**
     * Session read handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return string the session data
     */
    public function readSession($id)
    {
        $exist = $this->_select($id, false);

        if ($exist) {
        	$data = $exist['data'];
        	if ($this->extraFields) {
	        	$this->extraFields = array_intersect_key($exist, $this->extraFields);
        	}
        	if ($this->useTimestamp) {
        		$this->extraFields['updated_at'] = time();
        	}
        } else {
        	$data = '';
        	if ($this->useTimestamp) {
        		$this->extraFields['created_at'] = $this->extraFields['updated_at'] = time();
        	}
        }

        return $data;
    }

    /**
     * Session destroy handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return bool whether session is destroyed successfully
     */
    public function destroySession($id)
    {
        $this->_delete($id);
        return true;
    }

    /**
     * Removes all session variables
     */
    public function removeAll()
    {
    	$this->open();
    	foreach (array_keys((array)$_SESSION) as $key) {
    		unset($_SESSION[$key]);
    	}
    	foreach (array_keys((array)$this->extraFields) as $key) {
    		$this->extraFields[$key] = null;
    	}
    }

    public function clearAll()
    {
    	$this->close();
    	foreach (array_keys((array)$_SESSION) as $key) {
    		unset($_SESSION[$key]);
    	}
    	foreach (array_keys((array)$this->extraFields) as $key) {
    		$this->extraFields[$key] = null;
    	}
    }

    /**
     * Frees all session variables and destroys all data registered to a session.
     */
    public function destroy()
    {
    	if ($this->getIsActive()) {
    		$sessionId = session_id();
    		$this->close();
    		$this->setId($sessionId);
    		$this->open();
    		session_unset();
    		$this->removeAll();
    		session_destroy();
    		$this->setId($sessionId);
    	}
    }

    /**
     * Session GC (garbage collection) handler.
     * Do not call this method directly.
     * @param int $maxLifetime the number of seconds after which data will be seen as 'garbage' and cleaned up.
     * @return bool whether session is GCed successfully
     */
    public function gcSession($maxLifetime)
    {
        $this->db->delete()
	        ->table($this->sessionTable)
	        ->where(['<', 'expire', time()])
	        ->result();

        return true;
    }

    protected function _select($id, $expire = null)
    {
    	$now = time();

    	$query = $this->db->select()->from($this->sessionTable)
    	->where([$this->idField => $id]);

    	if ($expire === true) {
    		$query->and_where(['<', 'expire', $now]);
    	} elseif ($expire === false) {
    		$query->and_where(['>', 'expire', $now]);
    	}

    	$res = $query->result();
    	$res = $res ? $res[0] : [];

    	return $res;
    }

    protected function _delete($id)
    {
    	$res = $this->db->delete($this->sessionTable)
	    	->where([$this->idField => $id])
	    	->result();
    	return $res;
    }

    protected function _update($id, $fields)
    {
    	if ($this->useTimestamp) {
    		$fields['updated_at'] = time();
    	}

    	$res = $this->db->update($this->sessionTable)
	    	->set($fields)
	    	->where([$this->idField => $id])
	    	->result();
    	return $res;
    }

    protected function _insert($id, $fields)
    {
    	foreach ($this->requireFields as $require) {
    		if (!isset($fields[$require])) {
    			return 0;
    		}
    	}

    	if ($this->useTimestamp) {
    		$fields['created_at'] = $fields['updated_at'] = time();
    	}

    	$fields[$this->idField] = $id;

    	$res = $this->db->insert($fields)
	    	->table($this->sessionTable)
	    	->result();
    	return $res;
    }

    protected function composeFields($data)
    {
    	$fields = ['expire' => time() + $this->getTimeout(), 'data' => $data];
    	if ($this->extraFields && is_array($this->extraFields)) {
    		$fields += $this->extraFields;
    	}
    	return $fields;
    }
}
