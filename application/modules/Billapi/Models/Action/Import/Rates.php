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
	
	protected function runQuery() {
		$plan_rates = array();
		$service_rates = array();
		foreach ($this->update as $rate) {
			$keyField = !empty($rate['__UPDATER__']['value']) ? $rate['__UPDATER__']['value'] : 'key';
			$rates[$keyField] = array();
			if(!empty($rate['rates'])) {
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
		$plans_names = isset($this->rates_by_plan[$keyField]) ? $this->rates_by_plan[$keyField] : [];
		$unique_plan_rates_count = count(array_unique($plans_names));
		$plan_rates_count = count($plans_names);
		$services_names = isset($this->rates_by_service[$keyField]) ? $this->rates_by_service[$keyField] : [];
		$unique_service_rates_count = count(array_unique($services_names));
		$service_rates_count = count($services_names);
		if($this->getImportOperation() == 'create' && ($unique_plan_rates_count != $plan_rates_count || $unique_service_rates_count != $service_rates_count)){
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
			$existingRate = $existingRate->getRawData();
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
								foreach ($service_rates['rate'] as $key => &$rate) {
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
								foreach ($product_rates['rate'] as $key => &$rate) {
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
				$plans = !empty($rates['plans']) ? array_keys($rates['plans']) : [];// to do add plans or servives
				$services = !empty($rates['services']) ? array_keys($rates['services']) : [];
				$withBase = in_array('BASE', $plans) ||  in_array('BASE', $services);
				if (count($plans) > 1 || count($services) > 1 || !$withBase) {
					$entities_to_update = [
						'services' => $services,
						'plans' => $plans,
					];
					foreach ($entities_to_update as $collection => $entity_names) {
						foreach ($entity_names as $entity_name) {
							if($entity_name !== 'BASE'){
								// Check if update Plan or Service by serching for plan if not exist update service
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
									if($result !== true) {
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
		foreach ($entity['rates'] as $usaget => $rates) {
			if (isset($entity['rates'][$usaget]['product'])) {
				$entity['rates'][$usaget] = $entity['rates'][$usaget]['product'];
			} else {
				unset($entity['rates'][$usaget]);
			}
		}
		if (empty($entity['rates'])) {
			unset($entity['rates']);
		}
		return parent::importEntity($entity);
	}
	
}