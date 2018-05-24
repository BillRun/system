<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Customeronetime
 *
 * @author eran
 */
class Billrun_Aggregator_Customeronetime  extends Billrun_Aggregator_Customer {
	public function __construct($options = array()) {
		parent::__construct($options);
		$aggregateOptions = array(
			'passthrough_fields' => Billrun_Factory::config()->getConfigValue(static::$type . '.aggregator.passthrough_data', array()),
			'subs_passthrough_fields' => Billrun_Factory::config()->getConfigValue(static::$type . '.aggregator.subscriber.passthrough_data', array())
		);
		// If the accounts should not be overriden, filter the existing ones before.
		if (!$this->overrideMode) {
			// Get the aid exclusion query
			$aggregateOptions['exclusion_query'] = $this->billrun->existingAccountsQuery();
		}
		$this->aggregationLogic = new Billrun_Cycle_Onetime_AggregatePipeline($aggregateOptions);
	}
	
	public static function removeBeforeAggregate($billrunKey, $aids = array()) {
		Billrun_Factory::log("Doesn't  remove anything  in one time invoice");
		return ;
	}
	
	/**
	 *
	 * @param type $outputArr
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @param array $plans
	 * @param array $rates
	 * @return \Billrun_Cycle_Account
	 */
	protected function parseToAccounts($outputArr) {
		$accounts = array();
		$billrunData = array(
			'billrun_key' => $this->getCycle()->key(),
			'autoload' => !empty($this->overrideMode)
			);

		foreach ($outputArr as $subscriberPlan) {
			$aid = (string)$subscriberPlan['id']['aid'];
			$type = $subscriberPlan['id']['type'];

			if ($type === 'account') {
				$accounts[$aid]['attributes'] = $this->constructAccountAttributes($subscriberPlan);
				$raw = $subscriberPlan['id'];
				foreach(Billrun_Factory::config()->getConfigValue('customer.aggregator.subscriber.passthrough_data',array()) as $dstField => $srcField) {
					if(is_array($srcField) && method_exists($this, $srcField['func'])) {
						$raw[$dstField] = $this->{$srcField['func']}($subscriberPlan[$srcField['value']]);
					} else if(!empty($subscriberPlan['passthrough'][$srcField])) {
						$raw[$srcField] = $subscriberPlan['passthrough'][$srcField];
					}
				}
				$raw['sid']=0;
				$accounts[$aid]['subscribers'][$raw['sid']][] = $raw;
			} else if (($type === 'subscriber')) {
				$raw = $subscriberPlan['id'];
				foreach(Billrun_Factory::config()->getConfigValue('customer.aggregator.subscriber.passthrough_data',array()) as $dstField => $srcField) {
					if(is_array($srcField) && method_exists($this, $srcField['func'])) {
						$raw[$dstField] = $this->{$srcField['func']}($subscriberPlan[$srcField['value']]);
					} else if(!empty($subscriberPlan['passthrough'][$srcField])) {
						$raw[$srcField] = $subscriberPlan['passthrough'][$srcField];
					}
				}
				$raw['plans'] = $subscriberPlan['plan_dates'];
				foreach($subscriberPlan['plan_dates'] as $dates) {
					$raw['from'] = min($dates['from']->sec,  Billrun_Util::getFieldVal($raw['from'],PHP_INT_MAX) );
					$raw['to'] = max($dates['to']->sec,Billrun_Util::getFieldVal($raw['to'],0) );
				}
				$accounts[$aid]['subscribers'][$raw['sid']][] = $raw;
			} else {
				Billrun_Factory::log('Recevied a record form cycle aggregate with unknown type.',Zend_Log::ERR);
			}
		}

		$accountsToRet = array();
		foreach($accounts as $aid => $accountData) {
			$accountData['attributes']['invoice_type'] = 'immediate';
			$accountToAdd = $this->getAccount($billrunData, $accountData, intval($aid));
			if($accountToAdd) {
				$accountsToRet[] = $accountToAdd;
			}
		}

		return $accountsToRet;
	}
	
	public function isOneTime() {
		return true;
	}
}
