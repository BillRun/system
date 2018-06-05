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
			if (empty($rates[$rate['key']])) {
				$rates[$rate['key']] = array();
			}
			if(!empty($rate['rates'])) {
				foreach ($rate['rates'] as $usaget => $pricing) {
					$rates[$rate['key']] = array_merge($rates[$rate['key']], array_keys($pricing));
				}
			}
		}
		$this->rates_by_plan = $rates;
		return parent::runQuery();
	}
	
	protected function importEntity($entity) {
		$unique_plan_rates = count(array_unique($this->rates_by_plan[$entity['key']]));
		$plan_rates = count($this->rates_by_plan[$entity['key']]);
		if($this->getImportOperation() == 'create' && $unique_plan_rates != $plan_rates){
			return 'Create revision not allowd with import action Create, please use Update';
		}
		foreach ($entity['rates'] as $usaget => $rates) {
			if($rates) {
				$plans = array_keys($rates);
				$withBase = in_array('BASE',$plans);
				if (count($plans) > 1 || !$withBase) {
					foreach ($plans as $plan_name) {
						if($plan_name !== 'BASE'){
							$query = array(
								'effective_date' => empty($entity['effective_date']) ? $entity['from'] : $entity['effective_date'],
								'name' => $plan_name
							);
							$update = array(
								"rates.{$entity['key']}" => array(
									$usaget => $rates[$plan_name]
								),
								'from' => $entity['from']
							);
							$params = array(
								'collection' => 'plans',
								'request' => array(
									'action' => 'permanentchange',
									'update' => json_encode($update),
									'query' => json_encode($query)
								)
							);
							$entityModel = $this->getEntityModel($params);
							$entityModel->permanentchange();
							
							// Remove plan rates
							unset($entity['rates'][$usaget][$plan_name]);
							if (empty($entity['rates'][$usaget])) {
								unset($entity['rates'][$usaget]);
							}
							if(empty($entity['rates'])) {
								unset($entity['rates']);
							}
						}
					}
				}
			}
		}
		return parent::importEntity($entity);
	}
	
}
