<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Import prices lists action class
 *
 * @package  Action
 * @since    0.8
 */
class ImportPricesListAction extends ApiAction {

	protected $usage_types = array('call', 'sms', 'data', 'incoming_call', 'mms', 'incoming_sms');
	protected $categories = array('base', 'intl', 'roaming', 'special');

	/**
	 * Array of rules derived from csv
	 * @var array
	 */
	protected $rules = array();
	protected $model;

	/**
	 * Whether to remove usage types which don't exist in the input file
	 * @var boolean
	 */
	protected $should_remove_non_existing_usage_types = false;

	/**
	 * method to execute the bulk credit
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		$request = $this->getRequest()->getPost();
		$list = json_decode($request['prices'], true);
		$this->model = new RatesModel();
		if (isset($request['remove_non_existing_usage_types'])) {
			$this->should_remove_non_existing_usage_types = (boolean) $request['remove_non_existing_usage_types'];
		}
		if (isset($request['remove_non_existing_prefix'])) {
			$this->remove_non_existing_prefix = (boolean) $request['remove_non_existing_prefix'];
		}
		if (!($ret = $this->validateList($list))) {
			return $ret;
		}
		$ret = $this->importRates();
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'keys' => $ret,
		)));
		return true;
	}

	protected function importRates() {
		$updated_keys = array();
		$missing_categories = array();
		$old_or_not_exist = array();
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$future_keys = $this->model->getFutureRateKeys(array_unique(array_keys($this->rules)));
		foreach ($future_keys as $key) {
			unset($this->rules[$key]);
		}
		if ($this->rules) {
			$active_rates = $this->model->getActiveRates(array_unique(array_keys($this->rules)));
			foreach ($active_rates as $old_rate) {
				$this->updateRates($old_rate, $rates_coll,$updated_keys, $missing_categories);
			}
		}
		if (count($updated_keys) + count($future_keys) + count($missing_categories) != count($this->rules)) {
			$all_keys = array_unique(array_keys($this->rules));
			$old_or_not_exist = array_diff($all_keys, $updated_keys, $missing_categories);
		}
		return array('updated' => $updated_keys, 'future' => $future_keys, 'missing_category' => $missing_categories, 'old' => $old_or_not_exist);
	}
	
	/**
	 * Remove the non existing usage types from the rates record.
	 * @param array $rulesForRate - Rules for the current rate.
	 * @param array $oldRates - Array of old key=>value rates.
	 * @param array $newRates - Array of new key=>value rates to update.
	 */
	protected function removeNonExistingUsageTypes($rulesForRate, $oldRates, $newRates) {
		$new_usage_types = array_unique(array_keys($rulesForRate));
		$old_usage_types = array_unique(array_keys($oldRates));
		$usage_types_to_remove = array_diff($old_usage_types, $new_usage_types);
		foreach ($usage_types_to_remove as $usage_types) {
			unset($newRates[$usage_types]);
		}
	}
	
	/**
	 * Get the params prefix to set.
	 * @param array $ratePrefix - The prefix for the current rate proccessed.
	 * @param array $currentPrefix - The current prefix of the record to be updated.
	 * @return string Correct prefix for params.
	 */
	protected function getParamsPrefix($ratePrefix, $currentPrefix) {
		$prefix = explode(',', $ratePrefix);
		
		// If requested not to remove existing prefix, we concatenate it.
		if (!$this->remove_non_existing_prefix) {
			$additional_prefix = array_diff($prefix, $currentPrefix);
			$prefix = array_merge($currentPrefix, $additional_prefix);
		}
		
		return $prefix;
	}
	
	/**
	 * Set the dates for the new raw record.
	 * @param array $rateKey - The current rate key record.
	 * @param array $oldRate - The old rate record.
	 * @param array $newRawData - The new raw data record to set the date for.
	 * @param array $oldRawData - The old raw data record to set the date for.
	 */
	protected function setDates($rateKey, $oldRate, $newRawData, $oldRawData) {
		$newFromDate = new Zend_Date($this->rules[$rateKey]['from'], 'yyyy-MM-dd HH:mm:ss');
		$oldToDate = clone $newFromDate;
		$oldRawData['to'] = new MongoDate($oldToDate->subSecond(1)->getTimestamp());
		$oldRate->setRawData($oldRawData);
		unset($newRawData['_id']);
		$newRawData['from'] = new MongoDate($newFromDate->getTimestamp());
	}
	
	protected function updateRates($old_rate, $rates_coll,$updated_keys, $missing_categories) {
		$new_raw_data = $old_raw_data = $old_rate->getRawData();
		$rate_key = $old_raw_data['key'];
		$this->setDates($rate_key, $old_rate, $new_raw_data, $old_raw_data);

		if ($this->should_remove_non_existing_usage_types) {
			$this->removeNonExistingUsageTypes($this->rules[$rate_key]['usage_type_rates'],
											   $old_rate['rates'],
											   $new_raw_data['rates']);
		}

		$prefix = 
			$this->getParamsPrefix($this->rules[$rate_key]['prefix'],
								   $new_raw_data['params']['prefix']);
		$new_raw_data['params']['prefix']= $prefix;
		
		foreach ($this->rules[$rate_key]['usage_type_rates'] as $usage_type => $usage_type_rate) {
			// If this returns false it means we found a missing category.
			if(!$this->setUsageTypeData($usage_type,
										$usage_type_rate,
										$new_raw_data['rates'])) {
				$missing_categories[] = $rate_key;
				return false;
			}
		}
		$updated_keys[] = $rate_key;
		$new_rate = new Mongodloid_Entity($new_raw_data);
		// TODO: Validate the save?
		$old_rate->save($rates_coll);
		$new_rate->save($rates_coll);
	}
	
	/**
	 * Set the rates field of the new record with the rate usage types.
	 * @param string $usageType - Current usage type.
	 * @param string $usageTypeRate - Current rage usage type to set.
	 * @param array $ratesToSet - Rates field to set with usage values.
	 * @return boolean - False if found a missing category true otherwise.
	 */
	protected function setUsageTypeData($usageType, $usageTypeRate, $ratesToSet) {
		if (!isset($ratesToSet[$usageType]) && 
		   (!isset($usageTypeRate['category'])	    || 
			!$usageTypeRate['category'])) {
			return false;
		}
		$ratesToSet[$usageType]['unit'] = $this->model->getUnit($usageType);
		$ratesToSet[$usageType]['rate'] = $this->model->getRateArrayByRules($usageTypeRate['rules']);
		if ($usageTypeRate['category']) {
			$ratesToSet['rates'][$usageType]['category'] = $usageTypeRate['category'];
		}
		$ratesToSet[$usageType]['access'] = floatval($usageTypeRate['access']);
	}
	
	protected function validateList($list) {
		// exactly one infinite "times" for each rule
		// continuous rule numbers starting from 1
		if (!$list) {
			return $this->setError('Empty list');
		}
		$now = new Zend_Date();
		foreach ($list as $item) {
			if (!in_array($item['usage_type'], $this->usage_types)) {
				return $this->setError('Unknown usage type', $item);
			} else if ($item['category'] && !in_array($item['category'], $this->categories)) {
				return $this->setError('Unknown category', $item);
			} else if (!($this->isIntValue($item['rule']) && $item['rule'] > 0)) {
				return $this->setError('Illegal rule number', $item);
			} else if (!($this->isIntValue($item['interval']) && $item['interval'] > 0)) {
				return $this->setError('Illegal interval', $item);
			} else if (!($this->isIntValue($item['times']) && $item['times'] >= 0)) {
				return $this->setError('Illegal times', $item);
			} else if (!(is_numeric($item['access_price']) && $item['access_price'] >= 0)) {
				return $this->setError('Illegal access price', $item);
			} else if (!(is_numeric($item['price']) && $item['price'] >= 0)) {
				return $this->setError('Illegal price', $item);
			} else if (!Zend_Date::isDate($item['from_date'], 'yyyy-MM-dd HH:mm:ss')) {
				return $this->setError('Illegal from date', $item);
			} else if ((new Zend_Date($item['from_date'], 'yyyy-MM-dd HH:mm:ss')) <= $now) {
				return $this->setError('from date must be in the future', $item);
			} else if (!$item['key']) {
				return $this->setError('Illegal key', $item);
			}

			if (isset($this->rules[$item['key']]['usage_type_rates'][$item['usage_type']]['rules'][$item['rule']])) {
				return $this->setError('Duplicate rule (' . $item['rule'] . ') for ' . $item['key'] . ', ' . $item['usage_type']);
			} else {
				$this->rules[$item['key']]['usage_type_rates'][$item['usage_type']]['rules'][$item['rule']] = $item;
			}

			if (isset($this->rules[$item['key']]['usage_type_rates'][$item['usage_type']]['category'])) {
				if ($this->rules[$item['key']]['usage_type_rates'][$item['usage_type']]['category'] != $item['category']) {
					return $this->setError('Conflict in category', $item);
				}
			} else {
				$this->rules[$item['key']]['usage_type_rates'][$item['usage_type']]['category'] = $item['category'];
			}

			if (isset($this->rules[$item['key']]['usage_type_rates'][$item['usage_type']]['access'])) {
				if ($this->rules[$item['key']]['usage_type_rates'][$item['usage_type']]['access'] != $item['access_price']) {
					return $this->setError('Conflict in access price', $item);
				}
			} else {
				$this->rules[$item['key']]['usage_type_rates'][$item['usage_type']]['access'] = $item['access_price'];
			}

			if (isset($this->rules[$item['key']]['from'])) {
				if ($this->rules[$item['key']]['from'] != $item['from_date']) {
					return $this->setError('Conflict in from_date for ' . $item['key']);
				}
			} else {
				$this->rules[$item['key']]['from'] = $item['from_date'];
			}

			if (isset($this->rules[$item['key']]['prefix']) && !empty($this->rules[$item['key']]['prefix'])) {
				if (!empty($item['prefix']) && $this->rules[$item['key']]['prefix'] != $item['prefix']) {
					return $this->setError('Conflict in prefix for ' . $item['key']);
				}
			} else {
				$this->rules[$item['key']]['prefix'] = $item['prefix'];
			}
		}
		foreach ($this->rules as $key => $rule) {
			foreach ($rule['usage_type_rates'] as $usage_type => $usage_type_rate) {
				$rate_rules = $usage_type_rate['rules'];
				$rule_numbers = array_map('intval', array_keys($rate_rules));
				if (!in_array(1, $rule_numbers)) {
					return $this->setError('Missing first rule for ' . $key . ', ' . $usage_type);
				}
				$min = min($rule_numbers);
				$max = max($rule_numbers);
				if (count($rule_numbers) != $max - $min + 1) {
					return $this->setError('Missing rule for ' . $key . ', ' . $usage_type);
				}
				$infinite_counter = 0;
				foreach ($rate_rules as $rate_rule) {
					if ($rate_rule['times'] == '0' || $rate_rule['times'] == pow(2, 31) - 1) {
						$infinite_counter++;
					}
				}
				if ($infinite_counter != 1) {
					return $this->setError('None or more than one infinite rule detected for ' . $key . ', ' . $usage_type);
				}
				if ($rate_rules[strval($max)]['times'] != '0' && $rate_rules[strval($max)]['times'] != pow(2, 31) - 1) {
					return $this->setError('The last rule must be an infinite one (' . $key . ', ' . $usage_type . ')');
				}
			}
		}
		return TRUE;
	}

	/**
	 * 
	 * @param mixed $value
	 * @return boolean
	 */
	protected function isIntValue($value) {
		return is_numeric($value) && ($value == intval($value));
	}

}
