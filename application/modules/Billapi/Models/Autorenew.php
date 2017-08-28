<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi Auto Renew model for autorenew entity
 *
 * @package  Billapi
 * @since    5.6
 */
class Models_Autorenew extends Models_Entity {

	public function create() {
		if (empty($this->update['period'])) {
			$this->update['interval'] = 'month';
		}
		$this->update['next_renew'] = Billrun_Utils_Autorenew::getNextRenewDate(time());
		$this->update['cycles_remaining'] = $this->update['cycles'];
		$this->validateBucketGroup();
		parent::create();
	}
	
	protected function validateBucketGroup() {
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$bucketGroup  = $this->update['bucket_group'];
		$query['name'] = $bucketGroup;
		if (Billrun_Factory::db()->prepaidgroupsCollection()->query($query)->count() == 0) {
			throw new Billrun_Exceptions_Api(0, array(), "Invalid bucket group '$bucketGroup'");
		}
		return true;
	}
}
