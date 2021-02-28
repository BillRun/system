<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi unique get operation
 * Retrieve list of entities while the key or name field is unique
 * This is accounts unique get
 *
 * @package  Billapi
 * @since    5.5
 */
class Models_Action_Import_Rates extends Models_Action_Import {
	
	/**
	 * @var array
	 */
	protected $rates_by_plan = array();
	
	protected $specical_field = [
		'__MULTI_FIELD_ACTION__',
		'__UPDATER__',
		'__CSVROW__',
		'__ERRORS__',
		'price_plan',
		'price_from',
		'price_to',
		'price_interval',
		'price_value',
		'usage_type_value',
		'usage_type_unit',
	];

	protected $tax_fields = [
		'tax__type',
		'tax__taxation',
		'tax__custom_logic',
		'tax__custom_tax'
	];
	
	/**
	 * On Create action check for:
	 * 1. Create Rate  - OK
	 * 2. Not allow rate revision
	 * 3. Update PLAN price
	 *  or on Update actin check for:
	 * 1. Update rate revision
	 * 2. Update Plan revision
	 * 
	 */
	protected function runManualMappingQuery($entities) {
		$entities = $this->combineRateLines($entities);
		$this->rates_by_plan = $this->getRatePlans($entities);
		return parent::runManualMappingQuery($entities);
	}
	
	protected function getRatePlans($entities) {
		$rates = array();
		foreach ($entities as $rate) {
			$keyField = !empty($rate['__UPDATER__']['value']) ? $rate['__UPDATER__']['value'] : 'key';
			$rates[$keyField] = array();
			if (!empty($rate['rates'])) {
				foreach ($rate['rates'] as $usaget => $pricing) {
					$rates[$keyField] = array_merge($rates[$keyField], array_keys($pricing));
				}
			}
		}
		return $rates;
	}

	protected function combineRateLines($entities) {
		$operation = $this->operation;
		$import_fields = $this->update['import_fields'];
		$multi_value_fields = array_column(array_filter($import_fields, function($field) {
			return $field['multiple'] === true;
		}), 'value');
		$revision_date_field = ($operation === 'create') ? 'from' : 'effective_date';
		$entity_key = Billrun_Util::getIn($entities, [0, '__UPDATER__', 'value'], Billrun_Util::getIn($entities, 'key', 'key'));
		$entities_by_key = Billrun_Util::groupArrayBy($entities, [$entity_key, $revision_date_field]);
		$combined_entities = [];
		foreach ($entities_by_key as $key => $group_by_date) {
			foreach ($group_by_date as $date => $rate_group) {
				$acc = $rate_group[0];
				foreach ($rate_group as $idx => $rate) {
					$acc = $this->combineRate($acc, $rate, $idx, $operation, $multi_value_fields);
				}
				$combined_entities[] = $acc;
			}
		}
		return $combined_entities;
	}

	protected function combineRate($combine_rate, $rate_line, $index, $operation, $multi_value_fields) {
		$is_percentage = Billrun_Util::getIn($rate_line, ['rates', 'percentage'], '') !== '';
		$is_price = Billrun_Util::getIn($rate_line, 'price_value', '') !== '';
		$is_base = Billrun_Util::getIn($rate_line, 'price_plan', 'BASE') === 'BASE';

		$csv_row = Billrun_Util::getIn($rate_line, '__CSVROW__', 'unknown');
		$erros_path = ['__ERRORS__', $csv_row];
		$errors = Billrun_Util::getIn($combine_rate, $erros_path, []);

		if ($is_percentage && !$is_base) {
			$usage_type = Billrun_Util::getIn($rate_line, 'usage_type_value', '_KEEP_SOURCE_USAGE_TYPE_');
			$plan_name = Billrun_Util::getIn($rate_line, 'price_plan', 'BASE');
			$rate_path = ['rates', $usage_type, $plan_name, 'percentage'];
			$price_percentage = floatval(Billrun_Util::getIn($rate_line, ['rates', 'percentage'], 1));
			Billrun_Util::setIn($combine_rate, ['rates', 'percentage'], $price_percentage);
		} else if ($is_price) {
			if (isset($rate_line['price_from'])
				&& isset($rate_line['price_to'])
				&& isset($rate_line['price_interval'])
				&& isset($rate_line['price_value'])
				&& ((isset($rate_line['usage_type_unit']) && isset($rate_line['usage_type_value']) && $operation === 'create')
					|| $operation !== 'create'
					)
			) {
				$usage_type = Billrun_Util::getIn($rate_line, 'usage_type_value', '_KEEP_SOURCE_USAGE_TYPE_');
				$plan_name = Billrun_Util::getIn($rate_line, 'price_plan', 'BASE');
				$rate_path = ['rates', $usage_type, $plan_name, 'rate'];
				$rates = Billrun_Util::getIn($combine_rate, $rate_path, []);
				$last_rate = end($rates);
				if ($index == 0 || $rate_line['price_from'] != $last_rate['from']) {
					$price_from = Billrun_Util::getIn($rate_line, 'price_from', '') !== '' ? floatval(Billrun_Util::getIn($rate_line, 'price_from', '')) : 0;
					$price_to = Billrun_Util::getIn($rate_line, 'price_to', '') !== '' ? Billrun_Util::getIn($rate_line, 'price_to', '') : 'UNLIMITED';
					$price_to = is_numeric($price_to) ? floatval($price_to) : $price_to;
					$price_interval = Billrun_Util::getIn($rate_line, 'price_interval', '') !== '' ? floatval(Billrun_Util::getIn($rate_line, 'price_interval', '')) : 0;
					$price_value = Billrun_Util::getIn($rate_line, 'price_value', '') !== '' ? floatval(Billrun_Util::getIn($rate_line, 'price_value', '')) : 0;
					$rates[] = [
						'from' => $price_from,
						'to' => $price_to,
						'interval' => $price_interval,
						'price' => $price_value,
						'uom_display' => [
							'range' => Billrun_Util::getIn($rate_line, 'usage_type_unit', '_KEEP_SOURCE_USAGE_TYPE_UNIT_'),
							'interval' => Billrun_Util::getIn($rate_line, 'usage_type_unit', '_KEEP_SOURCE_USAGE_TYPE_UNIT_'),
						],
					];
					Billrun_Util::setIn($combine_rate, $rate_path, $rates);
				}
			} else {
				$mandatory_price_fields = ['price_from', 'price_to', 'price_interval', 'price_value'];
				if ($operation === 'create') {
					$mandatory_price_fields[] = 'usage_type_value';
					$mandatory_price_fields[] = 'usage_type_unit';
				}
				foreach ($mandatory_price_fields as $mandatory_price_field) {
					if (!isset($rate_line[$mandatory_price_field])) {
						$errors[] = "missing {$mandatory_price_field} data";
						Billrun_Util::setIn($combine_rate, $erros_path, $errors);
					}
				}
			}
		}

		foreach ($rate_line as $field_name => $value) {
			// do not combine specical_field
			if (in_array($field_name, $this->specical_field)) {
				continue;
			}
			if (is_array($value)) {
				foreach ($value as $field_key => $field_val) {
					$combine_rate = $this->combileFieldsValues("{$field_name}.{$field_key}", $field_val, $index, $combine_rate, $multi_value_fields, $csv_row);
				}
			} else {
				$combine_rate = $this->combileFieldsValues($field_name, $value, $index, $combine_rate, $multi_value_fields, $csv_row);
			}
		}
			
		// convert Price and Interval by unit
		$converted_rates = $this->getItemConvertedRates($combine_rate);
		if (!empty($converted_rates)) {
			Billrun_Util::setIn($combine_rate, 'rates', $converted_rates);
		}

		// push all rows number that build combined revision
		$csv_rows = Billrun_Util::getIn($combine_rate, '__CSVROW__', []);
		if (!is_array($csv_rows)) {
			$csv_rows = [$csv_rows];
		}
		if ($index !== 0) {
			$csv_rows[] = $csv_row;
		}
		Billrun_Util::setIn($combine_rate, '__CSVROW__', $csv_rows);

		// Delete all help fields that was added by UI.
		unset($combine_rate['tax__custom_logic']);
		unset($combine_rate['tax__custom_tax']);
		unset($combine_rate['tax__taxation']);
		unset($combine_rate['rates.percentage']);
		unset($combine_rate['price_plan']);
		unset($combine_rate['price_from']);
		unset($combine_rate['price_to']);
		unset($combine_rate['price_interval']);
		unset($combine_rate['price_value']);
		unset($combine_rate['usage_type_value']);
		unset($combine_rate['usage_type_unit']);

		return $combine_rate;
	}
	
	protected function combileFieldsValues($field_name, $value, $index, $combine_rate, $multi_value_fields, $csv_row) {
		$erros_path = ['__ERRORS__', $csv_row];
		$errors = Billrun_Util::getIn($combine_rate, $erros_path, []);
		// Check all other fields field with same value
		if ($index !== 0
			&& !in_array($field_name, $this->tax_fields)
			&& !in_array($field_name, $multi_value_fields)
			&& $value !== Billrun_Util::getIn($combine_rate, $field_name, '')
		) {
			$errors[] = "different values for {$field_name} field";
			Billrun_Util::setIn($combine_rate, $erros_path, $errors);
		}

		// build multivalues field value
		if (in_array($field_name, $multi_value_fields)) {
			$prev = Billrun_Util::getIn($combine_rate, $field_name, []);
			if (!is_array($prev)) {
				$prev = array_map('trim', explode(",", $prev));
			}
			$new = array_map('trim', explode(",", $value));
			$prev_with_new = array_unique(array_merge($prev, $new));
			Billrun_Util::setIn($combine_rate, $field_name, $prev_with_new);
		}

		// build tax object
		if (in_array($field_name, $this->tax_fields)) {
			$tax_field_name_array = explode("__", $field_name);
			$tax_field_path = [$tax_field_name_array[0], 0, $tax_field_name_array[1]];
			$tax_value = Billrun_Util::getIn($combine_rate, $tax_field_path, '');
			if ($index !== 0 && $value !== $tax_value) {
				$errors[] = "different values for {$tax_field_name_array[0]} {$tax_field_name_array[1]} field";
				Billrun_Util::setIn($combine_rate, $erros_path, $errors);
			} else {
				$tax_value = Billrun_Util::getIn($rate_line, $field_name, '');
				Billrun_Util::setIn($combine_rate, [$tax_field_name_array[0], 0, $tax_field_name_array[1]], $tax_value);
			}
		}
		return $combine_rate;
	}

	protected function isTaxRateExists($key, $from) {
		$taxQuery = Billrun_Utils_Mongo::getDateBoundQuery(strtotime($from));
		$taxQuery['key'] = $key;
		$existingTax = Billrun_Factory::db()->taxesCollection()->query($taxQuery)->cursor()->current();
		if (!$existingTax || $existingTax->isEmpty()) {
			return false;
		}
		return true;
	}
	
	protected function getValueByUnit ($usaget, $unit, $value) {
		if ($value === 'UNLIMITED') {
			return 'UNLIMITED';
		}
		if (in_array($value, [0, '0'])) {
			return 0;
		}
		$uoms = Billrun_Utils_Units::getUnitsOfMeasure($usaget);
		$uoms = array_filter($uoms, function($uom) use ($unit) {
			return $uom['name'] == $unit;
		});
		$u =  Billrun_Util::getIn($uoms, [0, 'unit'], 1);
		return implode(",", array_map(function($val) use ($u){
			return $val * $u;
		}, explode(",", $value)));
	}
	
	protected function getItemConvertedRates($item) {
		$rate_rates = Billrun_Util::getIn($item, 'rates', []);
		foreach ($rate_rates as $usaget => $rates) {
			foreach ($rates as $plan => $rate_steps) {
				$rate = Billrun_Util::getIn($rate_steps, 'rate', []);
				foreach ($rate as $index => $rate_step) {
					$range_unit = Billrun_Util::getIn($rate_step, ['uom_display', 'range'], 'counter');
					$interval_unit = Billrun_Util::getIn($rate_step, ['uom_display', 'interval'], 'counter');
					$from = Billrun_Util::getIn($rate_step, 'from', '');
					$converted_from = $this->getValueByUnit($usaget, $range_unit,$from);
					$new_from = is_numeric($converted_from) ? floatval($converted_from) : $converted_from;
					$to = Billrun_Util::getIn($rate_step, 'to', '');
					$converted_to = $this->getValueByUnit($usaget, $range_unit, $to);
					$new_to = is_numeric($converted_to) ? floatval($converted_to) : $converted_to;
					$price = Billrun_Util::getIn($rate_step, 'price', '');
					$converted_price = is_numeric($price) ? floatval($price) : $price;
					$interval = Billrun_Util::getIn($rate_step, 'interval', '');
					$converted_interval = $this->getValueByUnit($usaget, $interval_unit, $interval);
					$new_interval = is_numeric($converted_interval) ? floatval($converted_interval) : $converted_interval;
					$rate_path =  "{$usaget}.{$plan}.rate.{$index}";
					
					Billrun_Util::setIn($rate_rates, $rate_path . '.from', $new_from);
					Billrun_Util::setIn($rate_rates, $rate_path . '.to', $new_to);
					Billrun_Util::setIn($rate_rates, $rate_path . '.price', $converted_price);
					Billrun_Util::setIn($rate_rates, $rate_path . '.interval', $new_interval);
				}
		
				$percentage = Billrun_Util::getIn($rate, ['percentage'], null);
				if (!is_null($percentage)) {
					$rate_path = "usaget.plan";
					$converted_percentage = $percentage / 100 ;
					Billrun_Util::setIn($rate_rates, $rate_path . '.percentage', $converted_percentage);
				}
			}
		}

		return [];
	}
	
	
	protected function importEntity($entity) {
		$keyField = !empty($entity['__UPDATER__']['value']) ? $entity['__UPDATER__']['value'] : 'key';
		$plans_names = isset($this->rates_by_plan[$entity[$keyField]]) ? $this->rates_by_plan[$entity[$keyField]] : [];
		$unique_plan_rates_count = count(array_unique($plans_names));
		$plan_rates_count = count($plans_names);
		if($this->getImportOperation() == 'create' && $unique_plan_rates_count != $plan_rates_count){
			return 'Create revision not allowd with import action Create, please use Update';
		}
		
		$existingRate = null;
		$key = $entity['key']; // try to get it, exists only in create
		$from = empty($entity['effective_date']) ? $entity['from'] : $entity['effective_date'];
		
		if($this->getImportOperation() == 'permanentchange') {
			if (empty($entity['__UPDATER__'])) {
				throw new Exception('Missing mandatory update parameter updater');
			}
			$from = empty($entity['effective_date']) ? $entity['from'] : $entity['effective_date'];
			$rateQuery = Billrun_Utils_Mongo::getDateBoundQuery(strtotime($from));
			$rateQuery[$entity['__UPDATER__']['field']] = $entity['__UPDATER__']['value'];
			$existingRate = Billrun_Factory::db()->ratesCollection()->query($rateQuery)->cursor()->current();
			if(!$existingRate || $existingRate->isEmpty()) {
				throw new Exception("Product {$entity['__UPDATER__']['value']} does not exist");
			}
			$key = $existingRate['key']; // get rate key in update
			
			if(!empty($entity['__MULTI_FIELD_ACTION__'])) {
				foreach ($entity['__MULTI_FIELD_ACTION__'] as $fieldName => $action) {
					$path = explode(".", $fieldName);
					switch ($action) {
						case 'append':
							$oldValues = Billrun_Util::getIn($existingRate, $path, []);
							$newValues = $entity[$fieldName];
							$entity[$fieldName] = array_unique(array_merge($oldValues, $newValues));
							break;
						case 'remove':
							$entity[$fieldName] = [];
							break;
						case 'replace':
						default:
							break;
					}
				}
			}
			
			if (!empty($entity['rates'])) {
				$usagetype = reset(array_keys($existingRate['rates']));
				foreach ($entity['rates'] as $usaget => $rates) {
					if($usaget == "_KEEP_SOURCE_USAGE_TYPE_") {
						$uom_display = $existingRate['rates'][$usagetype]['BASE']['rate'][0]['uom_display'];
						$entity['rates'][$usagetype] = $rates;
						foreach ($entity['rates'][$usagetype] as $plan_name => &$plan_rates) {
							foreach ($plan_rates['rate'] as $key => &$rate) {
								if ($rate['uom_display']['interval'] === '_KEEP_SOURCE_USAGE_TYPE_UNIT_') {
									$rate['uom_display']['interval'] = $uom_display['interval'];
								}
								if ($rate['uom_display']['range'] === '_KEEP_SOURCE_USAGE_TYPE_UNIT_') {
									$rate['uom_display']['range'] = $uom_display['range'];
								}
							}
						}
						unset($entity['rates']["_KEEP_SOURCE_USAGE_TYPE_"]);
					}
				}
			}
		}
		// check if need to create \ update TAX object
		if(!empty($entity['tax'])) {
			if ($this->getImportOperation() == 'create') {
				if ($entity['tariff_category'] === 'retail') {
					$entity['tax'][0]['type'] = "vat";
					if (!empty($entity['tax'][0]['taxation']) && $entity['tax'][0]['taxation'] === "custom") {
						$isTaxExists = $this->isTaxRateExists($entity['tax'][0]['custom_tax'], $entity['from']);
						if(!$isTaxExists) {
							return "Tax rate {$entity['tax'][0]['custom_tax']} does not exist";
						}
						// set default if not exists
						$entity['tax'][0]['custom_logic'] = !empty($entity['tax'][0]['custom_logic']) ? $entity['tax'][0]['custom_logic'] : "override";
					} else {
						if (empty($entity['tax'][0]['taxation'])) {
							// set default
							$entity['tax'][0]['taxation'] = 'global';
						}
						unset($entity['tax'][0]['custom_tax']);
						unset($entity['tax'][0]['custom_logic']);
					}
				} else {
					// fix by removing tax object if tariff_category is not retail
					unset($entity['tax']);
				}
			} else if ($this->getImportOperation() == 'permanentchange') {
				// get existing tax objet or use default
				$existingTax = !empty($existingRate['tax']) ? $existingRate['tax'] : ['type' => 'vat', 'taxation' => 'global']; 
				if (!empty($entity['tax'][0]['taxation'])) {
					$existingTax['taxation'] = $entity['tax'][0]['taxation'];
				}
				if (!empty($entity['tax'][0]['custom_tax'])) {
					$from = empty($entity['effective_date']) ? $entity['from'] : $entity['effective_date'];
					$isTaxExists = $this->isTaxRateExists($entity['tax'][0]['custom_tax'], $from);
					if(!$isTaxExists) {
						return "Tax rate {$entity['tax'][0]['custom_tax']} does not exist";
					}
					$existingTax['custom_tax'] = $entity['tax'][0]['custom_tax'];
				}
				if (!empty($entity['tax'][0]['custom_logic'])) {
					$existingTax['custom_logic'] = $entity['tax'][0]['custom_logic'];
				}
				$entity['tax'] = $existingTax;
			}
		}
		
		$plan_updates = true;
		foreach ($entity['rates'] as $usaget => $rates) {
			if($rates) {
				$plans = array_keys($rates);
				$withBase = in_array('BASE',$plans);
				if (count($plans) > 1 || !$withBase) {
					foreach ($plans as $plan_name) {
						if($plan_name !== 'BASE'){
							// Check if update Plan or Service by serching for plan if not exist update service
							$planQuery = Billrun_Utils_Mongo::getDateBoundQuery(strtotime($from));
							$planQuery['name'] = $plan_name;
							$existingPlan = Billrun_Factory::db()->plansCollection()->query($planQuery)->cursor()->current();
							$collection = (!$existingPlan || $existingPlan->isEmpty()) ? 'services' : 'plans' ;
							$rateKey = $existingRate['key'];
							$existingRateRates = $existingPlan['rates'];
							$query = array(
								'effective_date' => $from,
								'name' => $plan_name
							);
							$update = [
								'from' => $from,
								'rates' => $existingRateRates,
							];
							if (!empty($rates[$plan_name]['rate'])) {
								$update['rates'][$rateKey][$usaget] = ['rate' => $rates[$plan_name]['rate']];
							} else if (!empty($rates[$plan_name]['percentage'])) {
								$update['rates'][$rateKey][$usaget] = ['percentage' => (float)$rates[$plan_name]['percentage']];
							}
							$params = array(
								'collection' => $collection,
								'request' => array(
									'action' => 'permanentchange',
									'update' => json_encode($update),
									'query' => json_encode($query)
								)
							);
							$entityModel = $this->getEntityModel($params);
							try {
								$result = $entityModel->permanentchange();
								if($result !== true) {
									$plan_updates = $result;
								}	
							} catch (Exception $exc) {
								$plan_updates = $exc->getMessage();
							}
							// Remove plan rates
							unset($entity['rates'][$usaget][$plan_name]);
							if (empty($entity['rates'][$usaget])) {
								unset($entity['rates'][$usaget]);
							}
						}
					}
				}
			}
		}
		// case when request include only plan rates update
		if(isset($entity['rates']) && empty($entity['rates']) && !in_array('BASE',$plans)) {
			return $plan_updates;
		}
		return parent::importEntity($entity);
	}
	
}
