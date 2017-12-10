<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Field validator for charging plan
 *
 */
trait Billrun_FieldValidator_ServiceProvider {

	/**
	 * Check with the mongo that the service provider is trusted.
	 * If the service provider is empty, return true.
	 * @param string $serviceProvider - Service provider to test.
	 * @return boolean true if trusted.
	 */
	protected function validateServiceProvider($serviceProvider) {
		if (!isset($serviceProvider)) {
			return true;
		}

		$collection = Billrun_Factory::db()->serviceprovidersCollection();
		$query = array('name' => $serviceProvider);
		return $collection->exists($query);
	}

}
