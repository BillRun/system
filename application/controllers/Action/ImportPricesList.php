<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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
	protected $remove_non_existing_usage_types = false;

	/**
	 * method to execute the bulk credit
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		$request = $this->getRequest()->getPost();
		$list = json_decode($request['prices'], true);
		$this->model = new RatesModel();
		if (isset($request['remove_non_existing_usage_types'])) {
			$this->remove_non_existing_usage_types = (boolean) $request['remove_non_existing_usage_types'];
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
				$new_raw_data = $old_raw_data = $old_rate->getRawData();
				$rate_key = $old_raw_data['key'];
				$new_from_date = new Zend_Date($this->rules[$rate_key]['from'], 'yyyy-MM-dd HH:mm:ss');
				$old_to_date = clone $new_from_date;
				$old_raw_data['to'] = new MongoDate($old_to_date->subSecond(1)->getTimestamp());
				$old_rate->setRawData($old_raw_data);
				unset($new_raw_data['_id']);
				$new_raw_data['from'] = new MongoDate($new_from_date->getTimestamp());

				if ($this->remove_non_existing_usage_types) {
					$new_usage_types = array_unique(array_keys($this->rules[$rate_key]['usage_type_rates']));
					$old_usage_types = array_unique(array_keys($old_rate['rates']));
					$usage_types_to_remove = array_diff($old_usage_types, $new_usage_types);
					foreach ($usage_types_to_remove as $usage_types) {
						unset($new_raw_data['rates'][$usage_types]);
					}
				}

				$new_prefix = explode(',', $this->rules[$rate_key]['prefix']);
				if (!$this->remove_non_existing_prefix) {
					$additional_prefix = array_diff($new_prefix, $new_raw_data['params']['prefix']);
					$combined_prefix = array_merge($new_raw_data['params']['prefix'], $additional_prefix);
					$new_raw_data['params']['prefix'] = $combined_prefix;
				} else {
					$new_raw_data['params']['prefix'] = $new_prefix;
				}

				foreach ($this->rules[$rate_key]['usage_type_rates'] as $usage_type => $usage_type_rate) {
					if (!isset($new_raw_data['rates'][$usage_type]) && (!isset($usage_type_rate['category']) || !$usage_type_rate['category'])) {
						$missing_categories[] = $rate_key;
						continue 2;
					}
					$new_raw_data['rates'][$usage_type]['unit'] = $this->model->getUnit($usage_type);
					$new_raw_data['rates'][$usage_type]['rate'] = $this->model->getRateArrayByRules($usage_type_rate['rules']);
					if ($usage_type_rate['category']) {
						$new_raw_data['rates'][$usage_type]['category'] = $usage_type_rate['category'];
					}
					$new_raw_data['rates'][$usage_type]['access'] = floatval($usage_type_rate['access']);
				}
				$updated_keys[] = $rate_key;
				$new_rate = new Mongodloid_Entity($new_raw_data);
				$old_rate->save($rates_coll);
				$new_rate->save($rates_coll);
			}
		}
		if (count($updated_keys) + count($future_keys) + count($missing_categories) != count($this->rules)) {
			$all_keys = array_unique(array_keys($this->rules));
			$old_or_not_exist = array_diff($all_keys, $updated_keys, $missing_categories);
		}
		return array('updated' => $updated_keys, 'future' => $future_keys, 'missing_category' => $missing_categories, 'old' => $old_or_not_exist);
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
					if ($rate_rule['times'] == '0' || intval($rate_rule['times'])*intval($rate_rule['interval']) >= pow(2, 31) - 4096) {
						$infinite_counter++;
					}
				}
				if ($infinite_counter != 1) {
					return $this->setError("None or more than one infinite rule detected for {$key} , {$usage_type} got {$infinite_counter} infnities ");
				}
				if ($rate_rules[strval($max)]['times'] != '0' && !$rate_rules[strval($max)]['times']*$rate_rule['interval'] >= pow(2, 31) - 4096) {
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
