<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi get Reports operation
 * Retrieve list of entities
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Get_Rates extends Models_Action_Get {

	public function getConfigParams($params) {
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
