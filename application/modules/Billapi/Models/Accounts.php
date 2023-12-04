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
	
	public $invoicing_day = null;

	protected function init($params) {
		parent::init($params);
		$this->update['type'] = 'account';
		if(Billrun_Factory::config()->isMultiDayCycle()) {
			$this->invoicing_day = $this->getInvoicingDay();
		}
		Billrun_Utils_Mongo::convertQueryMongodloidDates($this->update);
		$this->verifyAllowances();
		$this->verifyServices();
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
		return !empty($this->originalUpdate['invoicing_day']) ? $this->originalUpdate['invoicing_day'] : Billrun_Factory::config()->getConfigChargingDay();
	}

	/**
	 * Verify services are correct before update is applied to the subscription
	 * and makes sure it matches his play
	 */
	protected function verifyServices() {
		$services_sources = array();
		if (!empty($this->update['services'])) {
			$services_sources[] = &$this->update['services'];
		}
		if (!empty($this->queryOptions['$push']['services']['$each'])){
			$services_sources[] = &$this->queryOptions['$push']['services']['$each'];
		}
		
		if (empty($services_sources)) {
			return FALSE;
		}
		foreach ($services_sources as &$services_source) {	
			foreach ($services_source as $key => &$service) {
				if (gettype($service) == 'string') {
					$service = array('name' => $service);
				}
				if (gettype($service['from']) == 'string') {
					$service['from'] = new Mongodloid_Date(strtotime($service['from']));
				}
				if (empty($this->before)) { // this is new subscriber
					$service['from'] = isset($service['from']) && $service['from'] >= $this->update['from'] ? $service['from'] : $this->update['from'];
				}
				if (!empty($service['to']) && gettype($service['to']) == 'string') {
					$service['to'] = new Mongodloid_Date(strtotime($service['to']));
				}
				// handle custom period service or limited cycles service
				$serviceTime = $service['from']->sec ?? time();
				$serviceRate = new Billrun_Service(array('name' => $service['name'], 'time' => $serviceTime));
				// if service not found, throw exception
				if (empty($serviceRate) || empty($serviceRate->get('_id'))) {
					throw new Billrun_Exceptions_Api(66601, array(), "Service was not found");
				}
				if (!empty($servicePeriod = @$serviceRate->get('balance_period')) && $servicePeriod !== "default") {
					$service['to'] = new Mongodloid_Date(strtotime($servicePeriod, $service['from']->sec));
				} else {
					// Handle limited cycle services
					$serviceAvailableCycles = $serviceRate->getServiceCyclesCount();
					if ($serviceAvailableCycles !== Billrun_Service::UNLIMITED_VALUE) {
						$vDate = date(Billrun_Base::base_datetimeformat, $service['from']->sec);
						$to = strtotime('+' . $serviceAvailableCycles . ' months', Billrun_Billingcycle::getBillrunStartTimeByDate($vDate));
						$service['to'] = new Mongodloid_Date($to);
					}
				}
				if (empty($service['to'])) {
					$service['to'] =  new Mongodloid_Date(strtotime('+149 years'));
				}
				if (!isset($service['service_id'])) {
					$service['service_id'] = hexdec(uniqid());
				}

				if (!isset($service['creation_time'])) {
					$service['creation_time'] = new Mongodloid_Date();
				}
				
			}
		}
	}
}
