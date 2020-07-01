<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi accounts model for subscribers entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Accounts extends Models_Entity {

	protected function init($params) {
		parent::init($params);
		$this->update['type'] = 'account';
		Billrun_Utils_Mongo::convertQueryMongoDates($this->update);
		$this->verifyAllowances();
	}

	public function get() {
		$this->query['type'] = 'account';
		return parent::get();
	}

	/**
	 * method to add entity custom fields values from request
	 * 
	 * @param array $fields array of field settings
	 */
	protected function getCustomFields($update = array()) {
		$customFields = parent::getCustomFields();
		$accountFields = Billrun_Factory::config()->getConfigValue($this->collectionName . ".account.fields", array());
		return array_merge($accountFields, $customFields);
	}
	
	public function getCustomFieldsPath() {
		return $this->collectionName . ".account.fields";
	}
	
	/**
	 * Return the key field
	 * 
	 * @return String
	 */
	protected function getKeyField() {
		return 'aid';
	}

	/**
	 * validates that the allowances added to the account not added to other account
	 */
	protected function verifyAllowances() {
		$allowances = isset($this->update['allowances']) ? $this->update['allowances'] : [];
		if (empty($allowances)) {
			return true;
		}
		$sids = [];
		foreach ($allowances as $allowance) {
			$sid = $allowance['sid'];
			if (floatval($allowance['allowance']) <= 0) {
				throw new Billrun_Exceptions_Api(0, array(), "Allowance value for SID {$sid} must be a positive number greater than 0.");
			}
			if (in_array($sid, $sids)) {
				throw new Billrun_Exceptions_Api(0, array(), "Subscriber ID {$sid} could have only one allowance");
			}
			$sids[] = $sid;
		}
		$query = ["allowances.sid" => ["\$in" => $sids]];
		if (!empty($this->query['_id'])) {
			$query["_id"] = ["\$ne" => $this->query['_id']];
		} else if (!empty($this->update['aid'])) {
			$query["aid"] = ["\$ne" => $this->update['aid']];
		}

		$account = new Billrun_Account_Db();
		$account->loadAccountForQuery($query);
		if (!$account->getCustomerData()->isEmpty()) {
			$account_sids = array_reduce($account->allowances, function($acc, $allowance) {
				$acc[] = $allowance['sid'];
				return $acc;
			}, []);
			$sid_duplicate = implode(" ,", array_intersect($account_sids, $sids));
			throw new Billrun_Exceptions_Api(0, array(), "Allowances for subscriber IDs {$sid_duplicate} belong to another account.");
		}
		return true;
	}
	
	public function getInvoicingDay () {
		return !empty($additional['invoicing_day']) ? $additional['invoicing_day'] : null;
	}
}
