<?php

class Admin_Table {

	/**
	 * Converts a value to uppercase or lowercase
	 * @param mixed $value
	 * @param string $ctype either "upper" or "lower"
	 */
	public static function convertValueByCaseType($value, $ctype) {
		switch ($ctype) {
			case "upper":
				$ret = strtoupper($value);
				break;
			case "lower":
				$ret = strtolower($value);
				break;

			default:
				$ret = $value;
				break;
		}
		return $ret;
	}

}
