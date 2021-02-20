<?php
namespace vendor\base;

/**
 * 回调实例是model同类时，使用当前model
 */
class ModelValidator
{
	use ErrorTrait;
	
	const ERR_EMPTY = 1;
	const ERR_VALID = 2;
	const ERR_READONLY = 3;
	const ERR_REPEAT = 4;
	const ERR_LEN = 5;
	
	protected $model = null;
	protected $not_nulls = [];
	/**
	 * @var \vendor\db\Db $db
	 */
	protected $db = null;
	
	protected $exec = null;
	
	/**
	 * 动态
	 */
	protected $validates = [];
	
	public function __construct(ValidateModel $model, $db)
	{
		$this->model = $model;
		$this->not_nulls = $model->not_nulls();
		$this->db = $db;
	}
	
	/**
	 * @param array $validates
	 * @param string $exec
	 * @param array $snapshot
	 * @param array $vals
	 */
	public function validate($validates, $exec, $snapshot, &$vals)
	{
		$this->exec = $exec;
		foreach ($validates as $type => $validate) {
			switch ($type) {
				case 'require' :	//针对insert
					if (!$this->requires($validate, $vals)) {
						return false;
					}
					break;
				case 'readonly' :	//针对 update
					if ($exec === 'update') {
						$vals = array_diff_key($vals, array_fill_keys($validate, null));
					}
					break;
				case 'exist' :
					if (!$this->exist($validate, $snapshot, $vals)) {
						return false;
					}
					break;
				case 'repeat' :
					if (!$this->repeat($validate, $snapshot, $vals)) {
						return false;
					}
					break;
				case 'filter' :
					if (!$this->filter($validate, $snapshot, $vals)) {
						return false;
					}
					break;
				case 'range' :
				    $msg = [];
                    if (isset($validate['msg'])) {
                        $msg = $validate['msg'];
                        unset($validate['msg']);
                    }
					$validate = array_intersect_key($validate, $vals);
					foreach ($validate as $fd => $range) {
						if (!in_array($vals[$fd], $range)) {
						    if (isset($msg[$fd])) {
                                $this->addError($msg[$fd]);
                            } else {
                                $this->addError($fd . ' 不在正确范围内');
                            }
							return false;
						}
					}
					break;
				case 'url' :
                    $msg = [];
                    if (isset($validate['msg'])) {
                        $msg = $validate['msg'];
                        unset($validate['msg']);
                    }
					$validate = array_intersect_key(array_flip($validate), $vals);
                    $validate =  array_flip($validate);
					foreach ($validate as $fd) {
						if (filter_var($vals[$fd], FILTER_VALIDATE_URL) === false) {
                            if (isset($msg[$fd])) {
                                $this->addError($msg[$fd]);
                            } else {
                                $this->addError($fd . ' 非法的URL');
                            }
							return false;
						}
					}
					break;
				case 'regular' :
                    $msg = [];
                    if (isset($validate['msg'])) {
                        $msg = $validate['msg'];
                        unset($validate['msg']);
                    }
					$validate = array_intersect_key($validate, $vals);
					foreach ($validate as $fd => $pattern) {
						if (!preg_match($pattern, $vals[$fd])) {
                            if (isset($msg[$fd])) {
                                $this->addError($msg[$fd]);
                            } else {
                                $this->addError($fd . ' 参数不符合规则');
                            }
							return false;
						}
					}
					break;
				case 'string' :
                    $msg = [];
                    if (isset($validate['msg'])) {
                        $msg = $validate['msg'];
                        unset($validate['msg']);
                    }
					$validate = array_intersect_key($validate, $vals);
					$valid = Validators::lengthValidate($validate, $vals);
					if ($valid !== true) {
                        if (isset($msg[$valid])) {
                            $this->addError($msg[$valid]);
                        } else {
                            $this->addError($valid . ' 长度超出范围');
                        }
						return false;
					}
					break;
				case 'number' :
                    $msg = [];
                    if (isset($validate['msg'])) {
                        $msg = $validate['msg'];
                        unset($validate['msg']);
                    }
					$validate = array_intersect_key($validate, $vals);
					$valid = Validators::numberValidate($validate, $vals);
					if ($valid !== true) {
                        if (isset($msg[$valid])) {
                            $this->addError($msg[$valid]);
                        } else {
                            $this->addError($valid . ' 长度超出范围');
                        }
						return false;
					}
					break;
			}
		}
		
		return true;
	}
	
	/**
	 * @param array $validate
	 * @param array $vals
	 * @return boolean
	 */
	protected function requires($validate, $vals)
	{
	    if (!$validate) {
	        return true;
        }
        //获取自定义错误
        $msg = [];
        if (isset($validate['msg'])) {
            $msg = $validate['msg'];
            unset($validate['msg']);
        }
		$requires = is_array($validate[0]) ? $validate[0] : $validate;
		if ($this->exec === 'update') {
			$requires = array_intersect($requires, array_keys($vals));
		}

        $valid = Validators::requireValidate($requires, $vals, isset($validate['allowedEmpties']) ? $validate['allowedEmpties'] : []);
		if ($valid !== true) {
            if (isset($msg[$valid])) {
                $this->addError($msg[$valid]);
            } else {
                $this->addError($valid . ' 参数验证失败');
            }
//			$this->addError($valid, self::ERR_EMPTY);
			return false;
		}
		return true;
	}
	
	/**
	 * @param array $validate
	 * @param array $snapshot
	 * @param array $vals
	 * @throws InvalidConfigException
	 */
	protected function filter($validate, $snapshot, &$vals)
	{
        //获取自定义错误
        $msg = [];
        if (isset($validate['msg'])) {
            $msg = $validate['msg'];
            unset($validate['msg']);
        }

		//规范化
		$filters = isset($validate['callback']) ? [$validate] : $validate;
		$val_keys = array_keys($vals);
		
		foreach ($filters as $filter) {
			//检查字段
			$filter[0] = array_intersect($filter[0], $val_keys);
			if (!$filter[0]) {
				continue;
			}
			//检查callback
			if (!isset($filter['callback'])) {
				throw new InvalidConfigException();
			}
			if (isset($filter['instance']) && $filter['instance'] === true) {
				$filter['callback'][0] = $this->model instanceof $filter['callback'][0] ? $this->model : new $filter['callback'][0];
			}
			if (!is_callable($filter['callback'])) {
				throw new InvalidConfigException();
			}
			//设置共同参数
			$args = [];
			if (isset($filter['args'])) {
				foreach ($filter['args'] as $i => $arg) {
					$args[$i] = key_exists($arg, $vals) ? $vals[$arg] : (key_exists($arg, $snapshot) ? $snapshot[$arg] : null);
				}
			}
			foreach ($filter[0] as $fd) {
				$args[] = $vals[$fd];	//当前字段值
				$args[] = $fd;			//当前字段名
				
				$filterResult = call_user_func_array($filter['callback'], $args);

				if ($filterResult === false) {
					//$this->addError($fd, self::ERR_VALID);
                    if (isset($msg[$fd])) {
                        $this->addError($msg[$fd]);
                    } else {
                        $this->addError($fd . ' 参数验证失败');
                    }
                    return false;
				} elseif (isset($filter['results'])) {
                    $filterResult = (array)$filterResult;
					if (isset($filterResult[0])) {
						if (count($filter['results']) > count($filterResult)) {
							throw new InvalidConfigException();
						}
						foreach ($filter['results'] as $i => $rfd) {
							$vals[$rfd] = array_shift($filterResult);
						}
						!$filterResult ?: $vals[$fd] = array_shift($filterResult);
					} else {
						foreach ($filter['results'] as $i => $rfd) {
							!key_exists($rfd, $filterResult) ?: $vals[$rfd] = $filterResult[$rfd];
						}
						!key_exists($fd, $filterResult) ?: $vals[$fd] = $filterResult[$fd];
					}
				} else {
					$vals[$fd] = $filterResult;
				}
			}
		}
		
		return true;
	}
	
	/**
	 * @param array $validate
	 * @param array $snapshot
	 * @param array $vals
	 * @throws InvalidConfigException
	 * @throws \Exception
	 * @return boolean
	 */
	protected function exist($validate, $snapshot, &$vals)
	{
        if (!$validate) {
            return true;
        }

        //获取自定义错误
        $msg = [];
        if (isset($validate['msg'])) {
            $msg = $validate['msg'];
            unset($validate['msg']);
        }

		$validate = array_intersect_key($validate, $vals);

		$exists = null;	//do not need all results
		$valid = Validators::existValidate($validate, $vals + $snapshot, $this->db, false, $exists);
		if ($valid === true) {
			if ($exists) {
				foreach ($exists as $fd => $exist) {
					if (isset($validate[$fd]['instance']) && $validate[$fd]['instance'] === true) {
						$validate[$fd]['res_callback'][0] = $this->model instanceof $validate[$fd]['res_callback'][0] ? $this->model : new $validate[$fd]['res_callback'][0];
					}
					if (!is_callable($validate[$fd]['res_callback'])) {
						throw new InvalidConfigException();
					}
					//进一步回调验证，修改并返回vals
					$vals = call_user_func($validate[$fd]['res_callback'], $exist, $vals);
					if ($vals === false) {
						$this->addError($fd, self::ERR_VALID);
						return false;
					}
				}
			}
		} else {
			if (!$valid) {
				//unknow error
				throw new \Exception();
			} else {
				//$this->addError($valid, self::ERR_VALID);
                if (isset($msg[$valid])) {
                    $this->addError($msg[$valid]);
                } else {
                    $this->addError($valid . ' 参数验证失败');
                }
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * @param array $validate
	 * @param array $snapshot
	 * @param array $vals
	 * @throws InvalidConfigException
	 * @throws \Exception
	 * @return boolean
	 */
	protected function repeat($validate, $snapshot, $vals)
	{
	    if (!$validate) {
	        return true;
        }

	    $msg = [];
	    if (isset($validate['msg'])) {
	        $msg = $validate['msg'];
	        unset($validate['msg']);
        }

		//规范化
		if (is_string($validate[0])) {
			$validates = [$validate];
		} elseif (is_array($validate[0])) {
			if (isset($validate['when']) || isset($validate['!when'])) {
				$validates = [$validate];
			} else {
				$validates = $validate;
			}
		} else {
			throw new InvalidConfigException();
		}
		
		foreach ($validates as $validate) {
			if (is_array($validate[0])) {
				if (!Helpers::when($validate, $vals + $snapshot)) {
					continue;
				}
				$validate = $validate[0];
			}

			$validate_kfds = array_fill_keys($validate, null);
			$validate_vals = array_intersect_key($vals, $validate_kfds);
			if (!$validate_vals) {
				return true;
			}
			$validate_vals += array_intersect_key($snapshot, $validate_kfds);
			if (count($validate_vals) !== count($validate)) {
				throw new InvalidConfigException();
			}
			$validate_configs =  [
					$validate[0] => [
							'table' => $this->model::NAME,
							'target_fields' => array_combine($validate, $validate)
					]
			];

			$valid = Validators::existValidate($validate_configs, $validate_vals, $this->db, true);
			if ($valid !== true) {
				if (!$valid) {
					//unknow error
					throw new \Exception();
				} else {
				    //$this->addError($valid, self::ERR_VALID);
                    //如果是数组、则组合提示错误
                    $valid = implode('-', $validate);
                    if (isset($msg[$valid])) {
                        $this->addError($msg[$valid]);
                    } else {
                        $this->addError($valid . ' 参数重复');
                    }
					return false;
				}
			}
		}
		
		return true;
	}
}