<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Handle string and lined based records that are keyed by value and  seperated by a separator
 *
 */
class Billrun_Parser_KeyedSeparated extends Billrun_Parser_Separator {

	const SEP_REPLACMENT = "Billrun_Parser_KeyedSeparated_BILLRUN_SEP_REPLACEMENT_";

	public function parse() {
		$keyedRecord = $this->buildKeyedRecord($this->line);
		$retRecord = array();
		foreach ($keyedRecord as $key => $value) {
			if (isset($this->structure[$key])) {
				$retRecord[$this->structure[$key]] = $value;
			} else {
				Billrun_Factory::log("couldn't find $key in configuration", Zend_Log::DEBUG);
			}
		}
		$retRecord['stamp'] = md5(serialize($retRecord));
		return $retRecord;
	}

	protected function buildKeyedRecord($line) {
		$brokenLine = explode($this->getSeparator(), $this->escape($line, $this->getSeparator()));
		$keyedRecord = array();
		$lastKey = false;
		for ($i = 0; isset($brokenLine[$i]); $i++) {
			if ($i % 2) {
				$keyedRecord[$lastKey] = utf8_encode($this->unescape($brokenLine[$i], $this->getSeparator()));
			} else {
				$lastKey = utf8_encode($brokenLine[$i]);
			}
		}
		return $keyedRecord;
	}

	protected function escape($line, $sep) {
		return preg_replace("/(?=[^\"]*){$sep}(?=[^\"]*[^{$sep}]\")/", "", rtrim($line, "{$sep}\t\n\r\0\x0B"));
	}

	protected function unescape($val, $sep) {
		return preg_replace("/" . self::SEP_REPLACMENT . "/", $sep, $val);
	}

}

?>
