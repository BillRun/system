<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi export rate operation
 *
 * @package  Billapi
 * @since    5.5
 */
class Models_Action_Export_Rates extends Models_Action_Export {
	public function execute() {
		return parent::execute();
	}
	
	protected function setCsvOrder($mapper) {
		return $mapper;
	}
	
	/**
	 * @overide parent function to add rate price from plans
	 * @param type $query
	 * @return type
	 */
	protected function getDataToExport($query) {
		$results = parent::getDataToExport($query);
		$alreadyAddedPlanServiceRates = []; // to avoid multiple add the same plan \ service because of different rate revisions
		$plansCollection = Billrun_Factory::db()->plansCollection();
		$serviceCollection = Billrun_Factory::db()->servicesCollection();
		$records = [];
		foreach ($results as $record) {
			$records[] = $record;
			$usaget = $this->getValueUsaget($record, [], []);
			$rateKey = $record['key'];
			$query = [
				"rates.{$rateKey}" => [
					'$exists' => true
				],
			];
			if (!in_array($rateKey, $alreadyAddedPlanServiceRates)) {
				$plansResults = $plansCollection->query($query)->cursor();
				foreach ($plansResults as $plansResult) {
					$plan = $plansResult->getRawData();
					$planKey = $plan['name'];
					$record['rates'][$usaget] = [];
					$record['rates'][$usaget][$planKey]['rate'] = $plan['rates'][$rateKey][$usaget]['rate'];
					$record['from'] = $plan['from'];
					$record['to'] = $plan['to'];
					$records[] = $record;
				}
				$servicesResults = $serviceCollection->query($query)->cursor();
				foreach ($servicesResults as $serviceResult) {
					$service = $serviceResult->getRawData();
					$serviceKey = $service['name'];
					$record['rates'][$usaget] = [];
					$record['rates'][$usaget][$serviceKey]['rate'] = $service['rates'][$rateKey][$usaget]['rate'];
					$record['from'] = $service['from'];
					$record['to'] = $service['to'];
					$records[] = $record;
				}
				
			}
			$alreadyAddedPlanServiceRates[] = $rateKey;
		}
		return $records;
	}

	protected function getValueUsaget($data, $path, $params) {
		$usaget = reset(array_keys(Billrun_Util::getIn($data, 'rates', [])));
		return empty($usaget) ? '' : $usaget;
	}
	
	protected function getValuePlan($data, $path, $params) {
		$usaget = $this->getValueUsaget($data, $path, $params);
		$plan = reset(array_keys(Billrun_Util::getIn($data, ['rates', $usaget], [])));
		return empty($plan) ? '' : $plan;
	}

	protected function getValuePrice($data, $path, $params) {
		$usaget = $this->getValueUsaget($data, $path, $params);
		$plan = $this->getValuePlan($data, $path, $params);
		$pathArray = explode(".", $params['field_name']);
		array_shift($pathArray); // remove the 'price' key from config

		$rates = Billrun_Util::getIn($data, ['rates', $usaget, $plan, 'rate'], []);
		if (empty($rates)) {
			return '';
		}
		$values = array_reduce($rates, function($acc, $rate) use ($pathArray) {
			$acc[] = Billrun_Util::getIn($rate, $pathArray, '');
			return $acc;
		}, []);
		return empty($values) ? '' : implode(",", $values);
	}
	
}
