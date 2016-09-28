<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Represents an aggregatble account
 *
 * @package  Cycle
 * @since    5.2
 */
class Billrun_Cycle_Account extends Billrun_Cycle_Common {
	
	/**
	 * Array of account attributes
	 * @var array 
	 */
	protected $attributes = array();
	
	public function __construct($data) {
		parent::__construct($data);
	}
	
	/**
	 * Validate the input
	 * @param type $input
	 * @return type
	 */
	protected function validate($input) {
		// TODO: Complete
		return isset($input['subscribers']) && is_array($input['subscribers']);
	}

	/**
	 * Construct the subscriber records
	 * @param type $data
	 */
	protected function constructRecords($data) {
		$this->populateBillrunWithAccountData($data);
		$subscribers = $data['subscribers'];
		foreach ($subscribers as $subscriber) {
			$this->records[] = new Billrun_Cycle_Subscriber($subscriber);
		}
	}

	/**
	 * Get an empty billrun account entry structure.
	 * @param int $aid the account id of the billrun document
	 * @param string $billrun_key the billrun key of the billrun document
	 * @return array an empty billrun document
	 */
	protected function populateBillrunWithAccountData($account) {
		$attr = array();
		foreach (Billrun_Factory::config()->getConfigValue('billrun.passthrough_data', array()) as $key => $remoteKey) {
			if (isset($account['attributes'][$remoteKey])) {
				$attr[$key] = $account['attributes'][$remoteKey];
			}
		}
		if (isset($account['attributes']['first_name']) && isset($account['attributes']['last_name'])) {
			$attr['full_name'] = $account['attributes']['first_name'] . ' ' . $account['attributes']['last_name'];
		}

		$this->attributes = $attr;
	}
}
