<?php
namespace vendor\helpers;

class TimeFormat
{
	/**
	 * 秒 转 秒前、分钟前、小时前、天前
	 */
	public static function timeAgo($time)
	{
		if ($time < 60) {
			return $time . '秒前';
		}
		if ($time < 3600) {
			return floor($time / 60) . '分钟前';
		}
		if ($time < 86400) {
			return floor($time / 3600) . '小时前';
		}
	
		return floor($time / 86400) . '天前';
	}
	
	/**
	 * @param bool $startFromDay 表明格式是否以天开始
	 * @param bool $endWithSec 表明格式是否以秒结束
	 */
	public static function timeLength($time, $startFromDay = false, $endWithSec = false)
	{
		$time = (int)$time;
		$str = '';
	
		if ($startFromDay) {
			$day = (int)($time / 86400);
			if ($day) {
				$str .= $day . '天';
				$time %= 86400;
			}
		}
	
		$hour = (int)($time / 3600);
		if ($str || $hour) {
			$str .= $hour . '小时';
			$time %= 3600;
		}
	
		$min = (int)($time / 60);
		if ($endWithSec) {
			if ($str || $min) {
				$str .= $min . '分';
				$time %= 60;
			}
			$str .= $time . '秒';
		} else {
			$str .= $min . '分';
		}
	
		return $str;
	}
}