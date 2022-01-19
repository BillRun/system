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
class Generator_Prepaidmtr extends Billrun_Generator_ConfigurableCDRAggregationCsv {

	static $type = 'prepaidmtr';

	public function generate() {
		$fileData = $this->getNextFileData();
		$this->writeRows();
		$this->logDB($fileData);
	}

	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);

		return array('seq' => $seq, 'filename' => 'PREPAID_MTR_' . date('YmdHi',$this->startTime), 'source' => static::$type);
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

	protected function getFromDBRef($dbRef, $parameters, &$line) {
		$entity = $this->collection->getRef($dbRef);
		if ($entity && !$entity->isEmpty()) {
			return $entity[$parameters['field_name']];
		}
		return FALSE;
	}

	protected function getFromDBRefUrt($dbRef, $parameters, &$line) {
		$value = $this->getFromDBRef($dbRef, $parameters, $line);
		if (!empty($value)) {
			return $this->translateUrt($value, $parameters);
		}
	}

	protected function getPlanId($value, $parameters, $line) {
		$plan = Billrun_Factory::db()->plansCollection()->query(array('key' => $value))->cursor()->sort(array('urt' => -1))->limit(1)->current();
		if (!$plan->isEmpty()) {
			return $plan['external_id'];
		}
	}

	protected function flattenArray($array, $parameters, &$line) {
		foreach ($array as $idx => $val) {
			if ($val instanceof Mongodloid_Ref || isset($val['$ref'], $val['$id'])) {
				$val = $this->collection->getRef($val);
			}
			$dstIdx = isset($parameters['key_field']) ? $val[$parameters['key_field']] : $idx + 1;
			foreach ($parameters['mapping'] as $dataKey => $lineKey) {
				$fieldValue = is_array($val) || is_object($val) ? Billrun_Util::getNestedArrayVal($val, $dataKey) : $val;
				if (!empty($fieldValue)) {
					$line[sprintf($lineKey, $dstIdx)] = $fieldValue;
				}
			}
		}
		return $array;
	}

}
