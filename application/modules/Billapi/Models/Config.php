<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi verification trait model for 
 *
 * @package  Billapi
 * @since    5.3
 */
trait Models_Config {

	
	public function getRatesConfigParams($params) {
		$settings = parent::getConfigParams($params);
		if (in_array('rates.*', array_column($settings['query_parameters'], 'name'))) {
			$usage_types = Billrun_Factory::config()->getConfigValue('usage_types');
			$usage_types_names = array_column($usage_types, 'usage_type');
			foreach ($usage_types_names as $usage_type_name) {
				$settings['query_parameters'][] = [
					'name' => "rates.$usage_type_name",
					'type' => 'array'
				];
			}
		}
		return $settings;
	}

}
