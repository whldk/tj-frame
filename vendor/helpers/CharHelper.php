<?php
namespace vendor\helpers;

class CharHelper
{
	public static function int2Chars($int)
	{
		$output = '';
		
		if ($int < 1) {
			return $output;
		}
		
		do {
// 			if ($int == 26) {
// 				$output = 'Z' . $output;
// 				break;
// 			}
			
			$mod = $int % 26;
			if ($mod === 0) {
				$output = 'Z' . $output;
				$int -= 26;
			} else {
				$output = chr($mod + 64) . $output;
			}
			$int = intval($int / 26);
		} while ($int >= 1);
		
		return $output;
	}
}