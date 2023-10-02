<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2023 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Static functions for supporting  development functionality
 *
 */
class Billrun_Utils_Dev {
	const textColors = [
		"Reset" => 0,
		"Black"   => 30,
		"Red"     => 31,
		"Green"   => 32,
		"Yellow"  => 33,
		"Blue"    => 34,
		"Magenta" => 35,
		"Cyan"    => 36,
		"White"   => 37
	];

	public static function colorText($text, $color = 'Reset') {
		if(empty(static::textColors[$color])) {
			return $text;
		}
		return "\033[;".static::textColors[$color].'m'.$text."\033[0m";
	}
}
