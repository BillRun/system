<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Service providers model class
 *
 * @package  Models
 * @subpackage Table
 * @since    4.1
 */
class ServiceprovidersModel extends TabledateModel {

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->serviceproviders;
		parent::__construct($params);
		$this->service_providers_coll = Billrun_Factory::db()->serviceprovidersCollection();
	}
	
	public function hasEntityWithOverlappingDates($entity, $new = true) {
		return false;
	}

}
