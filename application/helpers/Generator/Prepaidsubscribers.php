<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Udata Generator class
 *
 * @package  Models
 * @since    4.0
 */
class Generator_Prepaidsubscribers extends Billrun_Generator_ConfigurableCDRAggregationCsv {

	static $type = 'prepaidsubscribers';

	public function generate() {
		$fileData = $this->getNextFileData();
		$this->writeRows();
		$this->logDB($fileData);
	}

	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);

		return array('seq' => $seq, 'filename' => 'PREPAID_SUBSCRIBERS_' . date('YmdHi'), 'source' => static::$type);
	}

	//--------------------------------------------  Protected ------------------------------------------------

	protected function writeRows() {
		if (!empty($this->headers)) {
			$this->writeHeaders();
		}
		foreach ($this->data as $line) {
			if ($this->isLineEligible($line)) {
				$this->writeRowToFile($this->translateCdrFields($line, $this->translations), $this->fieldDefinitions);
			}
		}
		$this->markFileAsDone();
	}

	protected function getReportCandiateMatchQuery() {
		return array();
	}

	protected function getReportFilterMatchQuery() {
		return array();
	}

	protected function isLineEligible($line) {
		return true;
	}

	// ------------------------------------ Helpers -----------------------------------------
	// 

	protected function countBalances($sid, $parameters, &$line) {
		$time = new MongoDate();

		return $this->db->balancesCollection()->query(array('sid' => $sid, 'from' => array('$lt' => $time), 'to' => array('$gt' => $time)))->cursor()->count(true);
	}

	protected function flattenBalances($sid, $parameters, &$line) {
		$time = new MongoDate();
		$balances = $this->db->balancesCollection()->query(array('sid' => $sid, 'from' => array('$lt' => $time), 'to' => array('$gt' => $time)));
		return $this->flattenArray($balances, $parameters, $line);
	}

	protected function flattenArray($array, $parameters, &$line) {
		$idx = 0;
		foreach ($array as $val) {
			$dstIdx = isset($parameters['key_field']) ? $val[$parameters['key_field']] : $idx + 1;
			foreach ($parameters['mapping'] as $dataKey => $lineKey) {
				$fieldValue = is_array($val) || is_object($val) ? Billrun_Util::getNestedArrayVal($val, $dataKey) : $val;
				if (!empty($fieldValue)) {
					$line[sprintf($lineKey, $dstIdx)] = $fieldValue;
				}
			}
			$idx++;
		}
		return $array;
	}

	protected function multiply($value, $parameters, $line) {
		return $value * $parameters;
	}

	protected function lastSidTransactionDate($value, $parameters, $line) {
		$usage = Billrun_Factory::db()->linesCollection()->query(array_merge(array('sid' => $value), $parameters['query']))->cursor()->sort(array('urt' => -1))->limit(1)->current();
		if (!$usage->isEmpty()) {
			return $this->translateUrt($usage['urt'], $parameters);
		}
	}

}
