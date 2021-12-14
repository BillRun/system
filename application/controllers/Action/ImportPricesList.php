<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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

	/**
	 * Whether to remove usage types which don't exist in the input file
	 * @var boolean
	 */
	protected $should_remove_non_existing_usage_types = false;

	/**
	 * method to execute the bulk credit
	 * it's called automatically by the api main controller
	 * @return boolean true if successfull.
	 */
	public function execute() {
		$request = $this->getRequest()->getPost();
		$list = json_decode($request['prices'], true);
		if (!$this->validateList($list)) {
			return false;
		}

		if (isset($request['remove_non_existing_usage_types'])) {
			$this->should_remove_non_existing_usage_types = (boolean) $request['remove_non_existing_usage_types'];
		}
		if (isset($request['remove_non_existing_prefix'])) {
			$this->remove_non_existing_prefix = (boolean) $request['remove_non_existing_prefix'];
		}

		$ret = $this->importRates();

		$controllerOutput = array(
			'status' => 1,
			'desc' => 'success',
			'keys' => $ret,
		);
		return $this->getController()->setOutput(array($controllerOutput));
	}

	/**
	 * Get the update and the missingCategory keys for the importRates function.
	 * @param RatesModel $ratesModel - The model object for the rates.
	 * @return array Containing the updateKeys and the missingCategories.
	 */
	protected function getUpdatedAndMissingCategoryKeys($ratesModel) {
		// This array is to be filled with the new rates.
		$updatedKeys = array();

		// This array is to be filled with all missing categories found.
		$missingCategories = array();

		$activeRates = $ratesModel->getActiveRates(array_unique(array_keys($this->rules)));
		$ratesColl = Billrun_Factory::db()->ratesCollection();

		// Go through all the old rates.
		foreach ($activeRates as $oldRate) {
			$updatedRate = $this->updateRates($ratesModel, $oldRate, $ratesColl);
			// If the return key is false it means a missing category is found.
			if (isset($updatedRate[true])) {
				$updatedKeys[] = $updatedRate;
			} else if (isset($updatedRate[false])) {
				$missingCategories[] = $updatedRate;
			} else {
				// This can happen if saving to the mongo failed.
				Billrun_Factory::log("getUpdatedAndMissingCategoryKeys: Something went terribly wrong.", Zend_Log::ERR);
				return null;
			}
		}

		return array($updatedKeys, $missingCategories);
	}

	/**
	 * Get all rates from the rates collection.
	 * @return array - Array of rates in the collection divided to, updated, future, old and missing category.
	 */
	protected function importRates() {
		$updated_keys = array();
		$missing_categories = array();
		$old_or_not_exist = array();
		$ratesModel = new RatesModel();
		$future_keys = $ratesModel->getFutureRateKeys(array_unique(array_keys($this->rules)));
		foreach ($future_keys as $key) {
			unset($this->rules[$key]);
		}

		// If there are rules to select rates by.
		if ($this->rules) {
			$updatedAndMissingCategories = $this->getUpdatedAndMissingCategoryKeys($ratesModel);
			// This is a criticl error!
			if (!$updatedAndMissingCategories) {
				return null;
			}
			list($updated_keys, $missing_categories) = each($updatedAndMissingCategories);
		}

		// Check if there are keys that are in the db but not in the rules or keys that are in the rules
		// but not in the db.
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
		$oldRawData['to'] = new Mongodloid_Date($oldToDate->subSecond(1)->getTimestamp());
		$oldRate->setRawData($oldRawData);
		unset($newRawData['_id']);
		$newRawData['from'] = new Mongodloid_Date($newFromDate->getTimestamp());
	}

	/**
	 * Update the new rates according to the old rates.
	 * @param RatesModel $ratesModel - The rates model object.
	 * @param array $oldRate - Current old rate.
	 * @param Mongoldoid_Collection $ratesColl - The rates collection object.
	 * @return (boolean, string) Pair of boolean and string. If the boolean is true the rate is to be 
	 * added to the updated keys. If the boolean is false the rate is to be added to the missing categories.
	 */
	protected function updateRates($ratesModel, $oldRate, $ratesColl) {
		$newRawData = $oldRawData = $oldRate->getRawData();
		$rateKey = $oldRawData['key'];
		$this->setDates($rateKey, $oldRate, $newRawData, $oldRawData);

		if ($this->should_remove_non_existing_usage_types) {
			$this->removeNonExistingUsageTypes($this->rules[$rateKey]['usage_type_rates'], $oldRate['rates'], $newRawData['rates']);
		}

		// Write the updated old rate to the database.
		if (!$ratesColl->save($oldRate)) {
			Billrun_Factory::log("UpdateRates: failed to save old rate to the data base. " . print_r($oldRate, true), Zend_Log::ERR);
			return null;
		}

		$prefix = $this->getParamsPrefix($this->rules[$rateKey]['prefix'], $newRawData['params']['prefix']);
		$newRawData['params']['prefix'] = $prefix;

		foreach ($this->rules[$rateKey]['usage_type_rates'] as $usage_type => $usage_type_rate) {
			// If this returns false it means we found a missing category.
			if (!$this->setUsageTypeData($ratesModel, $usage_type, $usage_type_rate, $newRawData['rates'])) {
				return array(false => $rateKey);
			}
		}

		$newRate = new Mongodloid_Entity($newRawData);

		if (!$ratesColl->save($newRate)) {
			Billrun_Factory::log("UpdateRates: failed to save new rate to the data base. " . print_r($newRate, true), Zend_Log::ERR);
			return null;
		}

		return array(true => $rateKey);
	}

	/**
	 * Set the rates field of the new record with the rate usage types.
	 * @param RatesModel $ratesModel - The rates model object.
	 * @param string $usageType - Current usage type.
	 * @param string $usageTypeRate - Current rage usage type to set.
	 * @param array $ratesToSet - Rates field to set with usage values.
	 * @return boolean - False if found a missing category true otherwise.
	 */
	protected function setUsageTypeData($ratesModel, $usageType, $usageTypeRate, $ratesToSet) {
		if (!isset($ratesToSet[$usageType]) &&
				(!isset($usageTypeRate['category']) ||
				!$usageTypeRate['category'])) {
			return false;
		}
		$ratesToSet[$usageType]['unit'] = $ratesModel->getUnit($usageType);
		$ratesToSet[$usageType]['rate'] = $$ratesModel->getRateArrayByRules($usageTypeRate['rules']);
		if ($usageTypeRate['category']) {
			$ratesToSet['rates'][$usageType]['category'] = $usageTypeRate['category'];
		}
		$ratesToSet[$usageType]['access'] = floatval($usageTypeRate['access']);

		return true;
	}

	/**
	 * Validate a list item.
	 * @param array $item - Item to be validated.
	 * @param Zend_Date $now - The time now. This is sent so we won't have to allocate
	 * a Zend_Date object each time.
	 * @return string - String describing the error that occured. Empty string if no error.
	 */
	protected function validateItem($item, $now) {
		$error = "";
		if (!in_array($item['usage_type'], $this->usage_types)) {
			$error = 'Unknown usage type';
		} else if ($item['category'] && !in_array($item['category'], $this->categories)) {
			$error = 'Unknown category';
		} else if (!($this->isIntValue($item['rule']) && $item['rule'] > 0)) {
			$error = 'Illegal rule number';
		} else if (!($this->isIntValue($item['interval']) && $item['interval'] > 0)) {
			$error = 'Illegal interval';
		} else if (!($this->isIntValue($item['times']) && $item['times'] >= 0)) {
			$error = 'Illegal times';
		} else if (!(is_numeric($item['access_price']) && $item['access_price'] >= 0)) {
			$error = 'Illegal access price';
		} else if (!(is_numeric($item['price']) && $item['price'] >= 0)) {
			$error = 'Illegal price';
		} else if (!Zend_Date::isDate($item['from_date'], 'yyyy-MM-dd HH:mm:ss')) {
			$error = 'Illegal from date';
		} else if ((new Zend_Date($item['from_date'], 'yyyy-MM-dd HH:mm:ss')) <= $now) {
			$error = 'from date must be in the future';
		} else if (!$item['key']) {
			$error = 'Illegal key';
		}

		return $error;
	}

	/**
	 * Set the item's usage type value, return error string if failed.
	 * @param array $item - Item to set the usage type by.
	 * @return string - Error to report, empty if no error.
	 */
	protected function setRuleUsageTypeByItem($item) {
		$itemUsageType = $this->rules[$item['key']]['usage_type_rates'][$item['usage_type']];
		if (isset($itemUsageType['rules'][$item['rule']])) {
			return 'Duplicate rule (' . $item['rule'] . ') for ' . $item['key'] . ', ' . $item['usage_type'];
		}

		$itemUsageType['rules'][$item['rule']] = $item;

		if (isset($itemUsageType['category'])) {
			if ($itemUsageType['category'] != $item['category']) {
				return 'Conflict in category';
			}
		} else {
			$itemUsageType['category'] = $item['category'];
		}

		if (isset($itemUsageType['access'])) {
			if ($itemUsageType['access'] != $item['access_price']) {
				return 'Conflict in access price';
			}
		} else {
			$itemUsageType['access'] = $item['access_price'];
		}

		return "";
	}

	/**
	 * Build a rule in the rule list based on an input item.
	 * @param array $item - Item to fill the rules with values by.
	 * @param Zend_Date $now - The time now. This is sent so we won't have to allocate
	 * a Zend_Date object each time.
	 * @return string - Error to report, empty if no error.
	 */
	protected function buildRuleFromItem($item, $now) {
		$error = $this->validateItem($item, $now);
		if (!empty($error)) {
			// Send $item as received input.
			return $error;
		}

		$error = $this->setRuleUsageTypeByItem($item);
		if (!empty($error)) {
			// Send $item as received input.
			return $error;
		}

		if (isset($this->rules[$item['key']]['from'])) {
			if ($this->rules[$item['key']]['from'] != $item['from_date']) {
				return 'Conflict in from_date for ' . $item['key'];
			}
		} else {
			$this->rules[$item['key']]['from'] = $item['from_date'];
		}

		if (isset($this->rules[$item['key']]['prefix']) && !empty($this->rules[$item['key']]['prefix'])) {
			if (!empty($item['prefix']) && $this->rules[$item['key']]['prefix'] != $item['prefix']) {
				return 'Conflict in prefix for ' . $item['key'];
			}
		} else {
			$this->rules[$item['key']]['prefix'] = $item['prefix'];
		}

		return "";
	}

	/**
	 * Validate an input list of rules.
	 * @param array $list - Input list to validate.
	 * @return boolean true if successful false otherwise. 
	 * This function can only fail if 
	 */
	protected function validateList($list) {
		// exactly one infinite "times" for each rule
		// continuous rule numbers starting from 1
		if (!$list) {
			return $this->setError('Empty list');
		}
		$now = new Zend_Date();

		// Go through the items of the list.
		foreach ($list as $item) {
			$error = $this->buildRuleFromItem($item, $now);
			if (!empty($error)) {
				// Send $item as received input.
				return $this->setError($error, $item);
			}
		}

		// Validate the rules.
		$error = $this->validateRuleList();
		if (!empty($error)) {
			return $this->setError($error);
		}

		return TRUE;
	}

	/**
	 * Validate the rule numbers for the rate rules list.
	 * @param array $rateRules - Array of rules to validate.
	 * @return string - Error string. Empty string if no error.
	 */
	protected function validateRuleNumbers($rateRules) {
		$rule_numbers = array_map('intval', array_keys($rateRules));
		if (!in_array(1, $rule_numbers)) {
			return 'Missing first rule for';
		}
		$min = min($rule_numbers);
		$max = max($rule_numbers);
		if (count($rule_numbers) != $max - $min + 1) {
			return 'Missing rule for';
		}

		$ruleMaxTimes = $rate_rules[strval($max)]['times'];
		if ($ruleMaxTimes != '0' && $ruleMaxTimes != pow(2, 31) - 1) {
			return 'The last rule must be an infinite one';
		}

		return "";
	}

	/**
	 * Validate the number of inifinite rules in a rule list.
	 * @param array $rateRules - List of rules to validate.
	 * @return string - Error string. Empty if no error.
	 */
	protected function validateInfiniteRules($rateRules) {
		$infiniteCounter = 0;
		// Go through the rules.
		foreach ($rateRules as $rateRule) {
			if ($rateRule['times'] == '0' || $rateRule['times'] == pow(2, 31) - 1) {
				$infiniteCounter++;
			}
		}
		if ($infiniteCounter != 1) {
			return 'None or more than one infinite rule detected for';
		}
	}

	/**
	 * Validate this object's rule list.
	 * @return string - Returns error string to report. Empty string if no error.
	 */
	protected function validateRuleList() {
		// Go through the rules.
		foreach ($this->rules as $key => $rule) {
			// Go through the usage types.
			foreach ($rule['usage_type_rates'] as $usageType => $usageTypeRate) {
				$rateRules = $usageTypeRate['rules'];
				$error = $this->validateRuleNumbers($rateRules);
				if (!empty($error)) {
					// Append the key and usage type to the error.
					return $error . ' (' . $key . ', ' . $usageType . ')';
				}

				$error = $this->validateInfiniteRules($rateRules);
				if (!empty($error)) {
					// Append the key and usage type to the error.
					return $error . ' (' . $key . ', ' . $usageType . ')';
				}
			}
		}

		return "";
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
