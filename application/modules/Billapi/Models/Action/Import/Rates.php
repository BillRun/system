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
	
	protected function importEntity($entity) {
		// TODO: in update key not exist
		$unique_plan_rates = count(array_unique($this->rates_by_plan[$entity['key']]));
		$plan_rates = count($this->rates_by_plan[$entity['key']]);
		if($this->getImportOperation() == 'create' && $unique_plan_rates != $plan_rates){
			return 'Create revision not allowd with import action Create, please use Update';
		}
		
		$existingRate = null;
		$key = $entity['key'];
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
			$key = $existingRate['key'];
			
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
							$key = $existingRate['key'];
							$query = array(
								'effective_date' => $from,
								'name' => $plan_name
							);
							$update = [];
							$update['from'] = $from;
							if (isset($rates[$plan_name]['rate'])) {
								$update["rates"][$key][$usaget] = [
									"rate" => $rates[$plan_name]['rate']
								];
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