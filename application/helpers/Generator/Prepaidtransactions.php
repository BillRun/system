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
class Generator_Prepaidtransactions extends Billrun_Generator_ConfigurableCDRAggregationCsv {

	static $type = 'prepaidtransactions';

	public function generate() {
		$fileData = $this->getNextFileData();
		$this->writeRows();
		$this->logDB($fileData);
	}

	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);

		return array('seq' => $seq, 'filename' => 'PREPAID_TRANSACTIONS_' . date('YmdHi',$this->startTime), 'source' => static::$type);
	}

	// ------------------------------------ Protected -----------------------------------------

	protected function getReportCandiateMatchQuery() {
		return array('urt' => array('$gt' => $this->getLastRunDate(static::$type)));
	}

	protected function getReportFilterMatchQuery() {
		return array();
	}

	// ------------------------------------ Helpers -----------------------------------------
	// 


	protected function isLineEligible($line) {
		return true;
	}

	protected function flattenArray($array, $parameters, &$line) {
		foreach ($array as $idx => $val) {
			if ($val instanceof Mongodloid_Ref) {
				$val = Billrun_DBRef::getEntity($val);
			}
			foreach ($parameters['mapping'] as $dataKey => $lineKey) {
				$fieldValue = Billrun_Util::getNestedArrayVal($val, $dataKey);
				if (!empty($fieldValue)) {
					$line[sprintf($lineKey, $idx + 1)] = $fieldValue;
				}
			}
		}
		return $array;
	}

}
