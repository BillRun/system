<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2022 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Static functions providing general usage CDR functionality
 *
 */
class Billrun_Utils_Credit {

	/**
	 * Crete a credit CDR  from  credit API info.
	 */
	public static function parse($credit_row) {

		$ret = static::validateFields($credit_row);

		$ret['skip_calc'] = static::getSkipCalcs($ret);
		$ret['process_time'] = new Mongodloid_Date();
		$ret['usaget'] = static::getCreditUsaget($ret);
		$rate = Billrun_Rates_Util::getRateByName($credit_row['rate']);
		if ($rate->isEmpty()) {
			throw new Exception("Rate doesn't exist");
		}
		$ret['credit'] = array(
			'usagev' => $ret['usagev'],
			'credit_by' => 'rate',
			'rate' => $ret['rate'],
			'usaget' => Billrun_Rates_Util::getRateUsageType($rate)
		);
		if (static::isCreditByPrice($ret)) {
			static::parseCreditByPrice($ret);
		} else {
			static::parseCreditByUsagev($ret);
		}
		if(!empty($credit_row['account_level'])) {
			$ret['account_level'] = $credit_row['account_level'];
		}
		if(isset($credit_row['recalculation_type'])){
			$grouping_keys = Billrun_Compute_Suggestions::getGroupingFieldsByRecalculationType($credit_row['recalculation_type']);
			foreach ($grouping_keys as $grouping_key){
				$value = Billrun_util::getIn($credit_row, $grouping_key);
				if(isset($value)){
					Billrun_Util::setIn($ret, $grouping_key, $value);
				}
			}
		}

		return $ret;
	}
	/**
	 * Run the calculators  on creditC CDRs
	 */
	public static function calculateCreditCDRs($creditCdrs) {
		//set up a dummy processor  that  will do nothing  as the queueCalculators require a processor to work
		$processorOptions =[
			'type' => 'Credit',
			'parser' => 'none',
		];
		$dummyProcessor = Billrun_Processor::getInstance($processorOptions);

		foreach ($creditCdrs as $event) {
			$dummyProcessor->addDataRow($event);
			$queueRow = $event;
			$queueRow['calc_name'] = false;
			$queueRow['calc_time'] = false;
			$queueRow['in_queue_since'] = new Mongodloid_Date();
			$dummyProcessor->setQueueRow($queueRow);
		}

		$options = array(
			'autoload' => 0,
			'realtime' => true,
			'credit' => true,
		);
		$dummyProcessor->parse();
		$calculatorData = $dummyProcessor->getData();
		$queueCalculators = new Billrun_Helpers_QueueCalculators($options);
		if (!$queueCalculators->run($dummyProcessor, $calculatorData)) {
			Billrun_Factory::log("Billrun_Processor: error occured while running queue calculators.", Zend_Log::ERR);
			return FALSE;
		}

		if(!empty($dummyProcessor->getQueueData())) {
			return false;
		}

		return $calculatorData['data'];
	}

	public static function parseCreditByPrice(&$row) {
		$row['credit']['aprice'] = $row['aprice'];
		if (!isset($row['multiply_charge_by_volume']) || boolval($row['multiply_charge_by_volume'])) {
			$row['aprice'] = $row['aprice'] * $row['usagev'];
		}
		$row['prepriced'] = true;
	}

	public static function parseCreditByUsagev(&$row) {
		$row['usagev'] = 1;
		$row['prepriced'] = false;
	}

	public static function isCreditByPrice($row) {
		return isset($row['aprice']);
	}

	public static function getCreditUsaget($row) {
		if (!isset($row['aprice'])) {
			return (isset($row['credit_type']) && in_array($row['credit_type'], ['charge' , 'refund'])) ? $row['credit_type'] : 'refund';
		}
		return ($row['aprice'] >= 0 ? 'charge' : 'refund');
	}

	public static function getSkipCalcs($row) {
		$skipArray = array('unify');
		return $skipArray;
	}

	public static function validateFields($credit_row) {
		$fields = Billrun_Factory::config()->getConfigValue('credit.fields', array());
		$ret = array();

		foreach ($fields as $fieldName => $field) {
			if (isset($field['mandatory']) && $field['mandatory']) {
				if (isset($credit_row[$fieldName])) {
					$ret[$fieldName] = $credit_row[$fieldName];
				} else if (isset($field['alternative_fields']) && is_array($field['alternative_fields'])) {
					$found = false;
					foreach ($field['alternative_fields'] as $alternativeFieldName) {
						if (isset($credit_row[$alternativeFieldName])) {
							$ret[$fieldName] = $credit_row[$alternativeFieldName];
							$found = true;
							break;
						}
					}

					if (!$found) {
						throw new Exception( 'Following field/s are missing: one of: (' . implode(', ', array_merge(array($fieldName), $field['alternative_fields'])) . ')' );
					}
				} else {
					throw new Exception( 'Following field/s are missing: ' . $fieldName);
				}
			} else if (isset($credit_row[$fieldName])) { // not mandatory field
				$ret[$fieldName] = $credit_row[$fieldName];
			} else {
				continue;
			}

			if (!empty($field['validator'])) {
				$validator = Billrun_TypeValidator_Manager::getValidator($field['validator']);
				if (!$validator) {
					Billrun_Factory::log('Cannot get validator for field ' .  $fieldName . '. Details: ' . print_r($field, 1));
					throw new Exception( 'General error');
				}
				$params = isset($field['validator_params']) ? $field['validator_params'] : array();
				if (!$validator->validate($ret[$fieldName], $params)) {
					throw new Exception( 'Field ' . $fieldName . ' should be of type ' . ucfirst($field['validator']));
				}
			}

			if (!empty($field['conversionMethod'])) {
				$ret[$fieldName] = call_user_func($field['conversionMethod'], $ret[$fieldName]);
			}
		}

		// credit custom fields
		if (isset($credit_row['uf'])) {
			if (!isset($ret['uf'])) {
				$ret['uf'] = array();
			}
			$entry = json_decode($credit_row['uf'], JSON_OBJECT_AS_ARRAY);
			$ufFields = Billrun_Factory::config()->getConfigValue('lines.credit.fields', array());
			foreach ($ufFields as $field) {
				$key = $field['field_name'];
				if (!empty($field['mandatory']) && !isset($entry[$key])) {
					throw new Exception( 'Following field is missing: uf.' . $key);
				} else if (isset($entry[$key])) {
					$ret['uf'][$key] = $entry[$key];
				}
			}
		}

		return $ret;
	}

}
