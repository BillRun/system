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
	protected $rates_by_service = array();
	
	
	protected $special_field = [
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
     * @param $combine_rate
     * @param  array  $rate_path
     * @param $index
     * @param $rate_line
     * @return array|mixed
     */
    public function addRatesToRateLine(&$combine_rate, array $rate_path, $index, $rate_line)
    {
        $rates = Billrun_Util::getIn($combine_rate, $rate_path, []);
        $last_rate = end($rates);
        if ($index == 0 || $rate_line['price_from'] != $last_rate['from']) {
            $price_from = Billrun_Util::getIn($rate_line, 'price_from', '') !== '' ? floatval(
                Billrun_Util::getIn($rate_line, 'price_from', '')
            ) : 0;
            $price_to = Billrun_Util::getIn($rate_line, 'price_to', '') !== '' ? Billrun_Util::getIn(
                $rate_line,
                'price_to',
                ''
            ) : 'UNLIMITED';
            $price_to = is_numeric($price_to) ? floatval($price_to) : $price_to;
            $price_interval = Billrun_Util::getIn($rate_line, 'price_interval', '') !== '' ? floatval(
                Billrun_Util::getIn($rate_line, 'price_interval', '')
            ) : 0;
            $price_value = Billrun_Util::getIn($rate_line, 'price_value', '') !== '' ? floatval(
                Billrun_Util::getIn($rate_line, 'price_value', '')
            ) : 0;
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
        }
        Billrun_Util::setIn($combine_rate, $rate_path, $rates);
        return $rates;
    }

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
		$this->setRatePlans($entities);
		return parent::runManualMappingQuery($entities);
	}

	protected function setTaxValue($data, $params) {
		$export_config = $this->getExportMapper();
		$type = $data[$export_config['tax.0.type']['title']];
		$taxation = $data[$export_config['tax.0.taxation']['title']];
		$custom_logic = $data[$export_config['tax.0.custom_logic']['title']];
		$custom_tax = $data[$export_config['tax.0.custom_tax']['title']];
		return [[
			"type" => $type,
			"taxation" => $taxation,
			"custom_logic" => $custom_logic,
			"custom_tax" => $custom_tax,
		]];
	}
	
	protected function setPriceValue($data, $params) {
		$export_config = $this->getExportMapper();
		$from = $this->fromArray($data[$export_config['price.from']['title']]);
		$to = $this->fromArray($data[$export_config['price.to']['title']]);
		$interval = $this->fromArray($data[$export_config['price.interval']['title']]);
		$price = $this->fromArray($data[$export_config['price.price']['title']]);
		$uom_range = $this->fromArray($data[$export_config['price.uom_display.range']['title']]);
		$uom_interval = $this->fromArray($data[$export_config['price.uom_display.interval']['title']]);

		$rate = [];
		foreach ($from as $idx => $value) {
			$rate[] = [
				'from' => floatval($value),
				'to' => is_numeric($to[$idx]) ? floatval($to[$idx]) : $to[$idx],
				'interval' => floatval($interval[$idx]),
				'price' => floatval($price[$idx]),
				'uom_display' => [
					'range' => $uom_range[$idx],
					'interval' => $uom_interval[$idx],
				]
			];
		}
		return [
			$data[$export_config['usaget']['title']] => [
				$data[$export_config['plan']['title']] => [
					'rate' => $rate,
				]
			],
		];
	}

	protected function setRatePlans($entities) {
		$plan_rates = array();
		$service_rates = array();
		foreach ($entities as $rate) {
			$keyField = !empty($rate['__UPDATER__']['value']) ? $rate['__UPDATER__']['value'] : 'key';
			$rates[$keyField] = array();
			if (!empty($rate['rates'])) {
				foreach ($rate['rates'] as $usaget => $pricing) {
					if (!isset($plan_rates[$keyField])) {
						$plan_rates[$keyField] = [];
					}
					$plan_rates[$keyField] = array_merge($plan_rates[$keyField], array_keys($pricing['plans']));
					if (!isset($service_rates[$keyField])) {
						$service_rates[$keyField] = [];
					}
					$service_rates[$keyField] = array_merge($service_rates[$keyField], array_keys($pricing['services']));
				}
			}
		}
		$this->rates_by_plan = $plan_rates;
		$this->rates_by_service = $service_rates;
	}

	protected function combineRateLines($entities) {
		$operation = $this->operation;
		$import_fields = $this->update['import_fields'];
		$multi_value_fields = array_column(array_filter($import_fields, function ($field) {
			return $field['multiple'] === true;
		}), 'value');
		$revision_date_field = ($operation === 'create') ? 'from' : 'effective_date';
		$entity_key = Billrun_Util::getIn($entities, [0, '__UPDATER__', 'value'], Billrun_Util::getIn($entities, 'key', 'key'));
		if ($operation == 'permanentchange') {
			$entities = array_map(function ($entity) {
				$field = Billrun_Util::getIn($entity, ['__UPDATER__', 'field'], '');
				$value = Billrun_Util::getIn($entity, ['__UPDATER__', 'value'], '');
				if (empty(Billrun_Util::getIn($entity, $field, ''))) {
					Billrun_Util::setIn($entity, $field, $value);
				}
				return $entity;
			}, $entities);
		}
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

	protected function  combineRate($combine_rate, $rate_line, $index, $operation, $multi_value_fields) {
		$is_percentage = Billrun_Util::getIn($rate_line, ['rates', 'percentage'], '') !== '';
		$is_price = Billrun_Util::getIn($rate_line, 'price_value', '') !== '';
		$is_base = Billrun_Util::getIn($rate_line, 'price_plan', 'BASE') === 'BASE';

		$csv_row = Billrun_Util::getIn($rate_line, '__CSVROW__', 'unknown');
		$error_path = ['__ERRORS__', $csv_row];
		$errors = Billrun_Util::getIn($combine_rate, $error_path, []);

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
                // plans
                $plan_name = Billrun_Util::getIn($rate_line, 'price_plan', 'BASE');
                $this->addRatesToRateLine($combine_rate, ['rates', $usage_type, 'plans', $plan_name, 'rate'], $index, $rate_line);
                
                // services
                $service_name = Billrun_Util::getIn($rate_line, 'price_service', 'BASE');
                $this->addRatesToRateLine($combine_rate, ['rates', $usage_type, 'services', $service_name, 'rate'], $index, $rate_line);
            } else {
				$mandatory_price_fields = ['price_from', 'price_to', 'price_interval', 'price_value'];
				if ($operation === 'create') {
					$mandatory_price_fields[] = 'usage_type_value';
					$mandatory_price_fields[] = 'usage_type_unit';
				}
				foreach ($mandatory_price_fields as $mandatory_price_field) {
					if (!isset($rate_line[$mandatory_price_field])) {
						$errors[] = "missing {$mandatory_price_field} data";
						Billrun_Util::setIn($combine_rate, $error_path, $errors);
					}
				}
			}
		}

		foreach ($rate_line as $field_name => $value) {
			// do not combine special_field
			if (in_array($field_name, $this->special_field)) {
				continue;
			}
			if (is_array($value)) {
				foreach ($value as $field_key => $field_val) {
					$combine_rate = $this->combineFieldsValues("{$field_name}.{$field_key}", $field_val, $index, $combine_rate, $multi_value_fields, $csv_row);
				}
			} else {
				$combine_rate = $this->combineFieldsValues($field_name, $value, $index, $combine_rate, $multi_value_fields, $csv_row);
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
		unset($combine_rate['rates.services.percentage']);
		unset($combine_rate['rates.plans.percentage']);
		unset($combine_rate['price_service']);
		unset($combine_rate['price_plan']);
		unset($combine_rate['price_from']);
		unset($combine_rate['price_to']);
		unset($combine_rate['price_interval']);
		unset($combine_rate['price_value']);
		unset($combine_rate['usage_type_value']);
		unset($combine_rate['usage_type_unit']);

		return $combine_rate;
	}
	
	protected function combineFieldsValues($field_name, $value, $index, $combine_rate, $multi_value_fields, $csv_row) {
		$error_path = ['__ERRORS__', $csv_row];
		$errors = Billrun_Util::getIn($combine_rate, $error_path, []);
		// Check all other fields field with same value
		if ($index !== 0
			&& !in_array($field_name, $this->tax_fields)
			&& !in_array($field_name, $multi_value_fields)
			&& $value !== Billrun_Util::getIn($combine_rate, $field_name, '')
		) {
			$errors[] = "different values for {$field_name} field";
			Billrun_Util::setIn($combine_rate, $error_path, $errors);
		}

		// build multi field values field value
		if (in_array($field_name, $multi_value_fields)) {
			$prev = Billrun_Util::getIn($combine_rate, $field_name, []);
			if (!is_array($prev)) {
				$prev = array_map('trim', array_filter(explode(",", $prev), 'strlen'));
			}
			$new = array_map('trim', array_filter(explode(",", $value), 'strlen'));
			$prev_with_new = array_unique(array_merge($prev, $new));
			$prev_with_new = implode(",", $prev_with_new);
			Billrun_Util::setIn($combine_rate, $field_name, $prev_with_new);
		}

		// build tax object
		if (in_array($field_name, $this->tax_fields)) {
			$tax_field_name_array = explode("__", $field_name);
			$tax_field_path = [$tax_field_name_array[0], 0, $tax_field_name_array[1]];
			$tax_value = Billrun_Util::getIn($combine_rate, $tax_field_path, '');
			if ($index !== 0 && $value !== $tax_value) {
				$errors[] = "different values for {$tax_field_name_array[0]} {$tax_field_name_array[1]} field";
				Billrun_Util::setIn($combine_rate, $error_path, $errors);
			} else {
				$tax_value = Billrun_Util::getIn($combine_rate, $field_name, '');
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
	
	protected function getValueByUnit($usaget, $unit, $value) {
		if ($value === 'UNLIMITED') {
			return 'UNLIMITED';
		}
		if (in_array($value, [0, '0'])) {
			return 0;
		}
		$uom = Billrun_Utils_Units::getUnitsOfMeasure($usaget);
		$uom = array_filter($uom, function($uom) use ($unit) {
			return $uom['name'] == $unit;
		});
		$u =  Billrun_Util::getIn($uom, [0, 'unit'], 1);
		return implode(",", array_map(function($val) use ($u){
			return $val * $u;
		}, explode(",", $value)));
	}
	
	protected function getItemConvertedRates($item) {
		$rate_rates = Billrun_Util::getIn($item, 'rates', []);
		foreach ($rate_rates as $usaget => $rates) {
            
			foreach ($rates['plans'] as $plan => $rate_steps) {
				$rate = Billrun_Util::getIn($rate_steps, 'rate', []);
				foreach ($rate as $index => $rate_step) {
					$range_unit = Billrun_Util::getIn($rate_step, ['uom_display', 'range'], 'counter');
					$interval_unit = Billrun_Util::getIn($rate_step, ['uom_display', 'interval'], 'counter');
					$from = Billrun_Util::getIn($rate_step, 'from', '');
					$converted_from = $this->getValueByUnit($usaget, $range_unit, $from);
					$new_from = is_numeric($converted_from) ? floatval($converted_from) : $converted_from;
					$to = Billrun_Util::getIn($rate_step, 'to', '');
					$converted_to = $this->getValueByUnit($usaget, $range_unit, $to);
					$new_to = is_numeric($converted_to) ? floatval($converted_to) : $converted_to;
					$price = Billrun_Util::getIn($rate_step, 'price', '');
					$converted_price = is_numeric($price) ? floatval($price) : $price;
					$interval = Billrun_Util::getIn($rate_step, 'interval', '');
					$converted_interval = $this->getValueByUnit($usaget, $interval_unit, $interval);
					$new_interval = is_numeric($converted_interval) ? floatval($converted_interval) : $converted_interval;
					$rate_path =  "{$usaget}.plans.{$plan}.rate.{$index}";
					
					Billrun_Util::setIn($rate_rates, $rate_path . '.from', $new_from);
					Billrun_Util::setIn($rate_rates, $rate_path . '.to', $new_to);
					Billrun_Util::setIn($rate_rates, $rate_path . '.price', $converted_price);
					Billrun_Util::setIn($rate_rates, $rate_path . '.interval', $new_interval);
				}
		
				$percentage = Billrun_Util::getIn($rate, ['percentage'], null);
				if (!is_null($percentage)) {
					$rate_path = "usaget.plan";
					$converted_percentage = $percentage / 100;
					Billrun_Util::setIn($rate_rates, $rate_path . '.percentage', $converted_percentage);
				}
			}
            // services
			foreach ($rates['services'] as $service => $rate_steps) {
				$rate = Billrun_Util::getIn($rate_steps, 'rate', []);
				foreach ($rate as $index => $rate_step) {
					$range_unit = Billrun_Util::getIn($rate_step, ['uom_display', 'range'], 'counter');
					$interval_unit = Billrun_Util::getIn($rate_step, ['uom_display', 'interval'], 'counter');
					$from = Billrun_Util::getIn($rate_step, 'from', '');
					$converted_from = $this->getValueByUnit($usaget, $range_unit, $from);
					$new_from = is_numeric($converted_from) ? floatval($converted_from) : $converted_from;
					$to = Billrun_Util::getIn($rate_step, 'to', '');
					$converted_to = $this->getValueByUnit($usaget, $range_unit, $to);
					$new_to = is_numeric($converted_to) ? floatval($converted_to) : $converted_to;
					$price = Billrun_Util::getIn($rate_step, 'price', '');
					$converted_price = is_numeric($price) ? floatval($price) : $price;
					$interval = Billrun_Util::getIn($rate_step, 'interval', '');
					$converted_interval = $this->getValueByUnit($usaget, $interval_unit, $interval);
					$new_interval = is_numeric($converted_interval) ? floatval($converted_interval) : $converted_interval;
					$rate_path =  "{$usaget}.services.{$service}.rate.{$index}";
					
					Billrun_Util::setIn($rate_rates, $rate_path . '.from', $new_from);
					Billrun_Util::setIn($rate_rates, $rate_path . '.to', $new_to);
					Billrun_Util::setIn($rate_rates, $rate_path . '.price', $converted_price);
					Billrun_Util::setIn($rate_rates, $rate_path . '.interval', $new_interval);
				}
		
				$percentage = Billrun_Util::getIn($rate, ['percentage'], null);
				if (!is_null($percentage)) {
					$rate_path = "usaget.plan";
					$converted_percentage = $percentage / 100;
					Billrun_Util::setIn($rate_rates, $rate_path . '.percentage', $converted_percentage);
				}
			}
		}

		return [];
	}
	
	protected function importEntity($entity) {
		$keyField = !empty($entity['__UPDATER__']['value']) ? $entity['__UPDATER__']['value'] : 'key';
		$plans_names = isset($this->rates_by_plan[$keyField]) ? $this->rates_by_plan[$keyField] : [];
		$unique_plan_rates_count = count(array_unique($plans_names));
		$plan_rates_count = count($plans_names);
		$services_names = isset($this->rates_by_service[$keyField]) ? $this->rates_by_service[$keyField] : [];
		$unique_service_rates_count = count(array_unique($services_names));
		$service_rates_count = count($services_names);
		if ($this->getImportOperation() == 'create' && ($unique_plan_rates_count != $plan_rates_count || $unique_service_rates_count != $service_rates_count)) {
			return 'Create revision not allowed with import action Create, please use Update';
		}

		$existingRate = null;
		$from = empty($entity['effective_date']) ? $entity['from'] : $entity['effective_date'];

		if ($this->getImportOperation() == 'permanentchange') {
			if (empty($entity['__UPDATER__'])) {
				throw new Exception('Missing mandatory update parameter updater');
			}
			$from = empty($entity['effective_date']) ? $entity['from'] : $entity['effective_date'];
			$rateQuery = Billrun_Utils_Mongo::getDateBoundQuery(strtotime($from));
			$rateQuery[$entity['__UPDATER__']['field']] = $entity['__UPDATER__']['value'];
			$existingRate = Billrun_Factory::db()->ratesCollection()->query($rateQuery)->cursor()->current();
			if (!$existingRate || $existingRate->isEmpty()) {
				throw new Exception("Product {$entity['__UPDATER__']['value']} does not exist");
			}
			$existingRate = $existingRate->getRawData();
			
			if (!empty($entity['__MULTI_FIELD_ACTION__'])) {
				foreach ($entity['__MULTI_FIELD_ACTION__'] as $fieldName => $action) {
					$path = explode(".", $fieldName);
					switch ($action) {
						case 'append':
							$oldValues = Billrun_Util::getIn($existingRate, $path, []);
							$newValues = Billrun_Util::getIn($entity, $path, []);
							$mergedValues = array_unique(array_merge($oldValues, $newValues));
							Billrun_Util::setIn($entity, $path, $mergedValues);
							break;
						case 'remove':
							Billrun_Util::setIn($entity, $path, []);
							break;
						case 'replace': // use new values as is
						default:
							break;
					}
				}
			}
			
			if (!empty($entity['rates'])) {
				$usagetype = reset(array_keys($existingRate['rates']));
				foreach ($entity['rates'] as $usaget => $rates) {
					if ($usaget == "_KEEP_SOURCE_USAGE_TYPE_") {
						$uom_display = $existingRate['rates'][$usagetype]['BASE']['rate'][0]['uom_display'];
						$entity['rates'][$usagetype] = $rates;
						if (isset($entity['rates'][$usaget]['plans'])) {
							foreach ($entity['rates'][$usaget]['plans']  as $plan_name => &$plan_rates) {
								foreach ($plan_rates['rate'] as $key => &$rate) {
									if ($rate['uom_display']['interval'] === '_KEEP_SOURCE_USAGE_TYPE_UNIT_') {
										$rate['uom_display']['interval'] = $uom_display['interval'];
									}
									if ($rate['uom_display']['range'] === '_KEEP_SOURCE_USAGE_TYPE_UNIT_') {
										$rate['uom_display']['range'] = $uom_display['range'];
									}
								}
							}
						}
						if (isset($entity['rates'][$usaget]['services'])) {
							foreach ($entity['rates'][$usaget]['services']  as $service_name => &$service_rates) {
								foreach ($service_rates['rate'] as &$rate) {
									if ($rate['uom_display']['interval'] === '_KEEP_SOURCE_USAGE_TYPE_UNIT_') {
										$rate['uom_display']['interval'] = $uom_display['interval'];
									}
									if ($rate['uom_display']['range'] === '_KEEP_SOURCE_USAGE_TYPE_UNIT_') {
										$rate['uom_display']['range'] = $uom_display['range'];
									}
								}
							}
						}
						if (isset($entity['rates'][$usaget]['product'])) {
							foreach ($entity['rates'][$usaget]['product']  as $product_name => &$product_rates) {
								foreach ($product_rates['rate'] as &$rate) {
									if ($rate['uom_display']['interval'] === '_KEEP_SOURCE_USAGE_TYPE_UNIT_') {
										$rate['uom_display']['interval'] = $uom_display['interval'];
									}
									if ($rate['uom_display']['range'] === '_KEEP_SOURCE_USAGE_TYPE_UNIT_') {
										$rate['uom_display']['range'] = $uom_display['range'];
									}
								}
							}
						}
						unset($entity['rates']["_KEEP_SOURCE_USAGE_TYPE_"]);
					}
				}
			}
		}
		// check if need to create \ update TAX object
		if (!empty($entity['tax'])) {
			if ($this->getImportOperation() == 'create') {
				if ($entity['tariff_category'] === 'retail') {
					$entity['tax'][0]['type'] = "vat";
					if (!empty($entity['tax'][0]['taxation']) && $entity['tax'][0]['taxation'] === "custom") {
						$isTaxExists = $this->isTaxRateExists($entity['tax'][0]['custom_tax'], $entity['from']);
						if (!$isTaxExists) {
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
					if (!$isTaxExists) {
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
			if ($rates) {
				$plans = !empty($rates['plans']) ? array_keys($rates['plans']) : []; // to do add plans or services
				$services = !empty($rates['services']) ? array_keys($rates['services']) : [];
				$withBase = in_array('BASE', $plans) ||  in_array('BASE', $services);
				if (count($plans) >= 1 || count($services) >= 1 || !$withBase) {
					$entities_to_update = [
						'services' => $services,
						'plans' => $plans,
					];
					foreach ($entities_to_update as $collection => $entity_names) {
						foreach ($entity_names as $entity_name) {
							if ($entity_name !== 'BASE') {
								// Check if update Plan or Service by searching for plan if not exist update service
								$entityQuery = Billrun_Utils_Mongo::getDateBoundQuery(strtotime($from));
								$entityQuery['name'] = $entity_name;
								$collectionName = "{$collection}Collection";
								$existingEntity = Billrun_Factory::db()->{$collectionName}()->query($entityQuery)->cursor()->current();
								$rateKey = $existingRate['key'];
								$existingRateRates = isset($existingEntity['rates']) ? $existingEntity['rates'] : [];
								$query = array(
									'effective_date' => $from,
									'name' => $entity_name
								);
								$update = [
									'from' => $from,
									'rates' => $existingRateRates,
								];
								if (!empty($rates[$collection][$entity_name]['rate'])) {
									$update['rates'][$rateKey][$usaget] = ['rate' => $rates[$collection][$entity_name]['rate']];
								} else if (!empty($rates[$collection][$entity_name]['percentage'])) {
									$update['rates'][$rateKey][$usaget] = ['percentage' => (float)$rates[$collection][$entity_name]['percentage']];
								}
                                
                                // add recurrence to update from existing entity
                                if (!empty($existingEntity['recurrence'])) {
                                    $update['recurrence'] = $existingEntity['recurrence'];
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
									error_log("permanentchange : " . print_r(json_encode($params), 1));
									$result = $entityModel->permanentchange();
									if ($result !== true) {
										return $result;
									}
								} catch (Exception $exc) {
									return $exc->getMessage();
								}
								// Remove plan rates
								unset($entity['rates'][$usaget]['services']);
								unset($entity['rates'][$usaget]['plan']);
								if (empty($entity['rates'][$usaget])) {
									unset($entity['rates'][$usaget]);
								}
							}
						}
					}
				}
			}
		}
        
        // Remove plans and services from rate entity if import operation is create
        if ($this->getImportOperation() == 'create') {
            $entityRates = $entity['rates'];
            unset($entity['rates']);

            $_rates = [];
            foreach ($entityRates as $usaget => $entityRate) {
                if (!isset($_rates[$usaget])) {
                    $_rates[$usaget] = [];
                }
                foreach ($entityRate as $rate) {
                    foreach ($rate as $rateName => $rateData) {
                        if (!isset($_rates[$usaget][$rateName])) {
                            $_rates[$usaget][$rateName] = $rateData;
                        }
                    }
                }
            }

            if (!empty($_rates)) {
                $entity['rates'] = $_rates;
            }
        }

		return parent::importEntity($entity);
	}

	/**
	 * Override the parent method because price can be related to Plan \ Service 
	 * in this case we need to update Plan \ Service entity and not the Rate itself
	 * @param type $row
	 * @param type $mapping
	 * @return boolean
	 */
	protected function importPredefinedMappingEntity($row, $mapping) {
		$entityData = $this->getPredefinedMappingEntityData($row, $mapping);
		$usaget = reset(array_keys(Billrun_Util::getIn($entityData, 'rates', [])));
		$usaget = empty($usaget) ? '' : $usaget;
		$planName = reset(array_keys(Billrun_Util::getIn($entityData, ['rates', $usaget], [])));
		if ($planName !== 'BASE') {
			$rateKey = Billrun_Util::getIn($entityData, 'key', '');
			$from = Billrun_Util::getIn($entityData, 'from', '');
			// Check if update Plan or Service by searching for plan if not exist update service
			$planQuery = Billrun_Utils_Mongo::getDateBoundQuery(strtotime($from));
			$planQuery['name'] = $planName;
			$existingPlan = Billrun_Factory::db()->plansCollection()->query($planQuery)->cursor()->current();
			$collection = 'plans';
			if (!$existingPlan || $existingPlan->isEmpty()) {
				$collection = 'services';
				$existingPlan = Billrun_Factory::db()->servicesCollection()->query($planQuery)->cursor()->current();
			}
			if (!$existingPlan || $existingPlan->isEmpty()) {
				return "Not found Plan \ Service {$planName} to override price";
			}
			$existingRateRates = $existingPlan['rates'];
			$query = [
				'effective_date' => $from,
				'name' => $planName
			];
			$update = [
				'from' => $from,
				'rates' => $existingRateRates,
			];
			$rates = Billrun_Util::getIn($entityData, ['rates', $usaget]);
			if (!empty($rates[$planName]['rate'])) {
				$update['rates'][$rateKey][$usaget]['rate'] = $rates[$planName]['rate'];
			} else if (!empty($rates[$planName]['percentage'])) {
				$update['rates'][$rateKey][$usaget]['percentage'] = (float)$rates[$planName]['percentage'];
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
				if ($result !== true) {
					return $result;
				}
				return true;
			} catch (Exception $exc) {
				return $exc->getMessage();
			}
		} else {
			return parent::importPredefinedMappingEntity($row, $mapping);
		}
	}
	
}
	