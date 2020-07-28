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