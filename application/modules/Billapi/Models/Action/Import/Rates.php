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
	
	protected function runQuery() {
		$rates = array();
		foreach ($this->update as $rate) {
			$keyField = !empty($rate['__UPDATER__']['value']) ? $rate['__UPDATER__']['value'] : 'key';
			$rates[$keyField] = array();
			if(!empty($rate['rates'])) {
				foreach ($rate['rates'] as $usaget => $pricing) {
					$rates[$keyField] = array_merge($rates[$keyField], array_keys($pricing));
				}
			}
		}
		$this->rates_by_plan = $rates;
		return parent::runQuery();
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