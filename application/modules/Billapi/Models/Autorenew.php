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
		if (empty($this->update['next_renew'])) {
			$this->update['next_renew'] = Billrun_Utils_Autorenew::getNextRenewDate(time());
		}
		$this->update['cycles_remaining'] = $this->update['cycles'];
		$this->validate();
		parent::create();
		if ($this->isImmediateAutoRenew()) {
			$this->autoRenewImmediate();
		}
	}
	
	protected function validate() {
		$this->validateBucketGroup();
		$this->validateNextRenewDate();
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
	
	protected function validateNextRenewDate() {
		if (!$this->isImmediateAutoRenew() && !$this->isValidNextRenewDate()) {
			throw new Billrun_Exceptions_Api(0, array(), "Invalid next renew date");
		}
		return true;
	}
	
	protected function isValidNextRenewDate() {
		return $this->update['next_renew']->sec >= strtotime("tomorrow");
	}
	
	protected function isImmediateAutoRenew() {
		return Billrun_Util::getIn($this->update, 'immediate', false);
	}
	
	protected function autoRenewImmediate() {
		Billrun_Autorenew_Manager::autoRenewRecord($this->after);
	}
}
