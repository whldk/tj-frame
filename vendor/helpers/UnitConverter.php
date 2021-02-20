<?php
namespace vendor\helpers;

class UnitConverter
{
	const DIV_ = 1;
	const DIV_K = 1000;
	const DIV_M = 1000000;
	const DIV_G = 1000000000;
	const DIV_T = 1000000000000;
	const DIV_P = 1000000000000000;
	const DIV_E = 1000000000000000000;
	
	private static $divMap = [
			'' => self::DIV_,
			'K' => self::DIV_K,
			'M' => self::DIV_M,
			'G' => self::DIV_G,
			'T' => self::DIV_T,
			'P' => self::DIV_P,
			'E' => self::DIV_E,
	];
	
	protected static $bitsMap = [
			'' => 0,
			'K' => 3,
			'M' => 6,
			'G' => 9,
			'T' => 12,
			'P' => 15,
			'E' => 18,
	];
	
	/**
	 * '', 'K', 'M', 'G', 'T', 'P' 互转（自适应）
	 * @param $number int
	 * @param $suffix string 默认后缀 H/s
	 * @param $type int 1 返回字符串（默认）， 2 返回数组
	 */
	public static function unit_auto_converter($number, $suffix = 'H/s', $type = 1)
	{
		list($digit, $unit) = self::split_digit_unit($number, $suffix);
		
		if ($digit === 0) {
			return $type === 1 ?  $digit . $unit . $suffix : [$digit, $unit . $suffix];
		}
		
		$bits = self::$bitsMap[$unit];
		$max_diff_bits = self::$bitsMap['E'] - $bits;
		
		$diff_unit = '';
		$diff_bits = 0;
		foreach (self::$bitsMap as $next_diff_unit => $next_diff_bits) {
			if ($digit < self::$divMap[$next_diff_unit] || $next_diff_bits == $max_diff_bits) {
				$digit = round($digit / self::$divMap[$diff_unit], 2);
				$unit = array_search($bits + $diff_bits, self::$bitsMap) ?: '';
				break;
			} else {
				$diff_unit = $next_diff_unit;
				$diff_bits = $next_diff_bits;
			}
		}
		
		return $type === 1 ?  $digit . $unit . $suffix : [$digit, $unit . $suffix];
	}
	
	protected static function split_digit_unit($number, $suffix)
	{
		if (is_numeric($number)) {
			return [$number, ''];
		}
		
		if ($suffix_len = strlen($suffix)) {
			$digit = substr($number, 0, 0 - $suffix_len);
			if (is_numeric($digit)) {
				return [$digit, ''];
			}
		} else {
			$digit = $number;
		}
	
		$unit = strtoupper(substr($digit, -1));
		if (!key_exists($unit, self::$bitsMap)) {
			return [0, ''];
		}
		$digit = substr($digit, 0, -1);
		if (!is_numeric($digit)) {
			return [0, ''];
		}
		
		return [$digit, $unit];
	}
	
	/**
	 * '', 'K', 'M', 'G', 'T', 'P' 互转（自定义）
	 */
	public static function unit_converter($string, $to_unit = '', $suffix = '')
	{
		if(empty($string) || !key_exists($to_unit, self::$bitsMap)){
			return 0;
		}
	
		list($digit, $unit) = self::split_digit_unit($string, $suffix);
		$diff_bits = self::$bitsMap[$to_unit] - self::$bitsMap[$unit];
		
		if ($diff_bits < 0) {
			$abs_diff = pow(10, - $diff_bits);
			$digit *= $abs_diff;
		} else {
			$abs_diff = pow(10, + $diff_bits);
			$digit /= $abs_diff;
		}
			
		return $digit;
	}
	
}