<?php
namespace vendor\base;

use vendor\base\Helpers;
use vendor\base\ValidateModel;
use \HTMLPurifier_Config;
use vendor\exceptions\InvalidConfigException;

class ModelFilter
{
	protected $model = null;
	protected $not_nulls = [];
	protected $before_filters = null;
	protected $after_filters = null;
	
	protected $is_before = true;
	
	public function __construct(ValidateModel $model)
	{
		$this->model = $model;
		$this->not_nulls = $model->not_nulls();
		$filters = $this->model->filters();
		!isset($filters['before']) ?: $this->before_filters = $filters['before'] ;
		!isset($filters['after']) ?: $this->after_filters = $filters['after'] ;
	}
	
	public function before_filter(&$snapshot, &$vals)
	{
		$this->is_before = true;
		
		$vals = array_intersect_key($vals, $snapshot);		//过滤vals中非法fields
		$vals = Helpers::array_diff_assoc($vals, $snapshot);	//过滤$vals中的等值

		if ($this->before_filters) {
			$this->internal_filter($this->before_filters, $snapshot, $vals);
		}
		$vals = Helpers::array_diff_assoc($vals, $snapshot);	//过滤$vals中的等值
	}
	
	public function after_filter(&$snapshot, &$vals)
	{
		$this->is_before = false;
		
		if ($this->after_filters) {
			$this->internal_filter($this->after_filters, $snapshot, $vals);
		}
		$vals = Helpers::array_diff_assoc($vals, $snapshot);
	}
	
	/**
	 * 允许为null
	 */
	protected function internal_filter($filters, &$snapshot, &$vals)
	{
		foreach ($filters as $type => $filter) {
			if (!$filter) {
				continue;
			}
			switch ($type) {
				case 'b' :
				case 'i' :
				case 'f' :
				case 's' :
					$this->typeVal($type, $filter, $vals);
					break;
				case 'html' :
					$this->html($filter, $vals);
					break;
				case 'ts' :
					$this->timestamp($filter, $snapshot, $vals);
					break;
				case 'img' :
					$this->img($filter, $snapshot, $vals);
					break;
				case 'map' :
					$this->map($filter, $snapshot, $vals);
					break;
				case 'ignore' :
					$this->ignore($filter, $vals);
					break;
				case 'json' :
					$this->json($filter, $snapshot, $vals);
					break;
			}
		}
	}
	
	protected function timestamp($filter, $snapshot, &$vals)
	{
		$now = time();
		if (isset($filter['mt'])) {
			$vals[$filter['mt']] = $now;
		}
		if (isset($filter['ct'])) {
			if (isset($snapshot[$filter['ct']]) && $snapshot[$filter['ct']]) {
				unset($vals[$filter['ct']]);
			} else {
				$vals[$filter['ct']] = $now;
			}
		}
	}
	
	protected function json($filter, &$snapshot, &$vals)
	{
		if ($this->is_before) {
			foreach ($filter as $fd) {
				!isset($snapshot[$fd]) || is_string($snapshot[$fd]) ?: $snapshot[$fd] = json_encode($snapshot[$fd]);
			}
		} else {
			foreach ($filter as $fd) {
				!isset($vals[$fd]) || is_array($vals[$fd]) || !$vals[$fd] ?: $vals[$fd] = json_decode($vals[$fd], true);
			}
		}
	}
	
	/**
	 * 跟ModelValidator::repeat逻辑有点像
	 * @param array $filter
	 * @param array $vals
	 * @throws InvalidConfigException
	 */
	protected function ignore($filter, &$vals)
	{
		//规范化
		if (is_string($filter[0])) {
			$filters = [$filter];
		} elseif (is_array($filter[0])) {
			$filters = isset($filter['when']) || isset($filter['!when']) ? [$filter] : $filter;
		} else {
			throw new InvalidConfigException();
		}

		foreach ($filters as $filter) {
			if (is_array($filter[0])) {
				if (Helpers::when($filter, $vals) === false) {
					continue;
				}
				$filter = $filter[0];
			}
			$vals = array_diff_key($vals, array_fill_keys($filter, null));
		}
	}
	
	/**
	 * 回调过滤
	 * @param array $filter
	 * @param array $snapshot 用于联合vals获callback参数
	 * @param array $vals
	 */
	protected function map($filter, $snapshot, &$vals)
	{
		//规范化
		$filters = isset($filter['callback']) ? [$filter] : $filter;
		$val_keys = array_keys($vals);
		foreach ($filters as $filter) {
			//检查字段是否改变
			$changed = false;

			if (isset($filter['args'])) {
				$filter['args'] = (array)$filter['args'];
				if (array_intersect($filter['args'], $val_keys)) {
					$changed = true;
				}
			}
			if (!$changed) {
				$filter[0] = array_intersect($filter[0], $val_keys);
				if (!$filter[0]) {
					continue;
				}
			}
			
			//检查callback
			if (!isset($filter['callback']) || !is_callable($filter['callback'])) {
				throw new InvalidConfigException();
			}
			//设置共同参数
			$args = [];
			if (isset($filter['args'])) {
				foreach ($filter['args'] as $i => $arg) {
					$args[$i] = isset($vals[$arg]) ? $vals[$arg] : (isset($snapshot[$arg]) ? $snapshot[$arg] : null);
				}
			}
			//当前过滤值参数
			foreach ($filter[0] as $fd) {
				$extraArgs = [];
				$extraArgs[] = isset($vals[$fd]) ? $vals[$fd] : (isset($snapshot[$fd]) ? $snapshot[$fd] : null);	//当前字段值
				$extraArgs[] = $fd;			//当前字段名
				$vals[$fd] = call_user_func_array($filter['callback'], array_merge($args, $extraArgs));
			}
		}
	}
	
	/**
	 * @param array $filter
	 * @param array $snapshot 用于获取旧url，并且进行回收
	 * @param array $vals
	 */
	protected function img($filter, $snapshot, &$vals)
	{
		$mime = 'image';
		$size = [];
		$md5 = [];
		$save_name = [];
		$info = false;
		if (is_array($filter[0])) {
			$filter_fds = $filter[0];
			if (isset($filter['mime'])) {
				$mime = $filter['mime'];
			}
			if (isset($filter['size'])) {
				$size = $filter['size'];
			}
			if (isset($filter['md5'])) {
				$md5 = $filter['md5'];
			}
			if (isset($filter['save_name'])) {
				$save_name = $filter['save_name'];
			}
			if (isset($filter['info'])) {
				$info = boolval($filter['info']);
			}
		} else {
			$filter_fds = $filter;
		}
		$filter_fields = array_intersect_key($snapshot, $vals, array_fill_keys($filter_fds, null));
		if ($filter_fields) {
			$this->model->upload($filter_fields, $mime, $size, $md5, $save_name, $info, true);
			$vals = $filter_fields + $vals;
		}
	}
	
	protected function html($filter, &$vals)
	{
		$config = null;
		if (is_array($filter[0])) {
			$filter_fds = $filter[0];
			if (isset($filter['config'])) {
				$config = HTMLPurifier_Config::createDefault();
				$def = $config->getHTMLDefinition(true);
				foreach ($filter['config'] as $element) {
					$def->addElement($element[0], $element[1], $element[2], $element[3], $element[4]);
				}
			}
		} else {
			$filter_fds = $filter;
		}
		foreach ($filter_fds as $fd) {
			if (!key_exists($fd, $vals) || $vals[$fd] === null && !in_array($fd, $this->not_nulls)) {
				return;
			}
			$vals[$fd] = (string)$vals[$fd];
			!$vals[$fd] ?: $vals[$fd] = $this->model->htmlpurifier->purify($vals[$fd], $config);
		}
	}
	
	protected function typeVal($type, $filter, &$vals)
	{
		switch ($type) {
			case 'b' :
				foreach ($filter as $fd) {
					if (!key_exists($fd, $vals) || $vals[$fd] === null && !in_array($fd, $this->not_nulls)) {
						return;
					}
					$vals[$fd] = $vals[$fd] ? 1 : 0;
				}
				break;
			case 'i' :
				foreach ($filter as $fd) {
					if (!key_exists($fd, $vals) || $vals[$fd] === null && !in_array($fd, $this->not_nulls)) {
						return;
					}
					$vals[$fd] = intval($vals[$fd]);
				}
				break;
			case 'f' :
				foreach ($filter as $fd) {
					if (!key_exists($fd, $vals) || $vals[$fd] === null && !in_array($fd, $this->not_nulls)) {
						return;
					}
					$vals[$fd] = floatval($vals[$fd]);
				}
				break;
			case 's' :
				foreach ($filter as $fd) {
					if (!key_exists($fd, $vals) || $vals[$fd] === null && !in_array($fd, $this->not_nulls)) {
						return;
					}
					$vals[$fd] = htmlspecialchars(trim(strval($vals[$fd])), ENT_QUOTES | ENT_HTML401);
				}
				break;
		}
	}
	
}