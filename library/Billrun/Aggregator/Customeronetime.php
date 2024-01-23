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
	
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'customeronetime';

	protected $subInvoiceType = 'regular';
	protected $invoicingConfig = array();
	protected $customer_uf = array();
	protected $lastAggregatedEntity = null;
	
	public function __construct($options = array()) {
		parent::__construct($options);
		$aggregateOptions = array(
			'passthrough_fields' => $this->getAggregatorConfig('passthrough_data', array()),
			'subs_passthrough_fields' => $this->getAggregatorConfig('subscriber.passthrough_data', array()),
			'is_onetime_invoice'=> true
		);
		// If the accounts should not be overriden, filter the existing ones before.
		if (!$this->overrideMode) {
			// Get the aid exclusion query
			$aggregateOptions['exclusion_query'] = $this->billrun->existingAccountsQuery();
		}
		if(!empty($options['invoice_subtype'])) {
			$this->subInvoiceType = Billrun_Util::getFieldVal($options['invoice_subtype'], 'regular'); 
		}
		if(!empty($options['uf'])){
			$this->customer_uf = $options['uf'];
		}
		$this->invoicingConfig = Billrun_Factory::config()->getConfigValue('onetimeinvoice.invoice_type_config', array());
		$this->min_invoice_id = intval(Billrun_Util::getFieldVal($this->invoicingConfig[$this->subInvoiceType]['min_invoice_id'], $this->min_invoice_id ));
		//This class will define the account/subscriber/plans aggregation logic for the cycle
		$this->aggregationLogic = Billrun_Account::getAccountAggregationLogic($aggregateOptions);

		$this->affectedSids = Billrun_Util::getFieldVal($options['affected_sids'],[]);
	}
	
	public static function removeBeforeAggregate($billrunKey, $aids = array()) {
		Billrun_Factory::log("Doesn't remove anything in one time invoice");
		return ;
	}
	
	public function setExternalChargesForAID($aid, $externalCharges)  {
		return $this->externalCharges[$aid] =$externalCharges;
	}
	
	protected function aggregatedEntity($aggregatedResults, $aggregatedEntity) {
			Billrun_Factory::dispatcher()->trigger('beforeAggregateAccount', array($aggregatedEntity));
			$customCollName = Billrun_Util::getFieldVal($this->invoicingConfig[$this->subInvoiceType]['collection_name'], 'billrun');
			$this->lastAggregatedEntity = $aggregatedEntity;
			if(!$this->isFakeCycle()) {
				$externalCharges = $this->addExternalCharges($aggregatedEntity);
				$aggregatedEntity->finalizeInvoice( $aggregatedResults );
				Billrun_Factory::log('Writing the invoice data to DB for AID : '.$aggregatedEntity->getInvoice()->getAid());
				//Save Account services / plans
				$this->saveLines($aggregatedResults);
				//Save external charges
				$this->saveLines($externalCharges);
				//Save Account discounts.
				$this->saveLines($aggregatedEntity->getAppliedDiscounts());
				//Save Customer user fields
				$aggregatedEntity->setUserFields($this->customer_uf);
				//Close & Save the billrun document
				$aggregatedEntity->closeInvoice($this->min_invoice_id, FALSE, $customCollName);
				//Save configurable/aggretaion data
				$aggregatedEntity->addConfigurableData();
				$aggregatedEntity->save();
			} else {
				$this->addExternalCharges($aggregatedEntity);
				$aggregatedEntity->finalizeInvoice( $aggregatedResults );
				$aggregatedEntity->closeInvoice(str_pad('0', strlen($this->min_invoice_id), '0') , $this->isFakeCycle() , $customCollName );
				//Save configurable/aggretaion data
				$aggregatedEntity->addConfigurableData();
			}
			Billrun_Factory::dispatcher()->trigger('afterAggregateAccount', array($aggregatedEntity, $aggregatedResults, $this));
			return $aggregatedResults;
	}

	public function getLastBillrunObj() {
		return $this->lastAggregatedEntity;
	}


	protected function addExternalCharges(&$aggregatedEntity) {
		$externalCharges = $this->getExternalChargesForAID($aggregatedEntity->getInvoice()->getAid());

		foreach($externalCharges as &$externalCharge) {
			$externalCharge['billrun'] = $this->getCycle()->key();
			$externalCharge['source'] = 'billrun';
			$externalCharge['billrun_cycle_credit'] = true;
			$sub = $aggregatedEntity->getSubscriber($externalCharge['sid']);
			if(!empty($sub)) {
				$sub->getInvoice()->addLines([$externalCharge]);
			} else {
				Billrun_Factory::log("Cloud not  find subscriber for external charge with stamp {$externalCharge['stamp']}, check the plugin logic!",Zend_Log::ERR);
			}
		}
		return $externalCharges;
	}

	protected function getExternalChargesForAID($aid)  {
		return empty($this->externalCharges[$aid]) ?  [] : $this->externalCharges[$aid];
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
			$invalidAccountFunctions = Billrun_Factory::config()->getConfigValue('customeronetime.aggregate.invalid_account_functions',['getActivePlan','getPlanNextTeirDate','getPlay']);
			$invalidFields = Billrun_Factory::config()->getConfigValue('customeronetime.aggregate.invalid_fields',['services']);
			if ($type === 'account') {
				$accounts[$aid]['attributes'] = $this->constructAccountAttributes($subscriberPlan);
				$raw = array_diff_key($subscriberPlan['id'] ,array_flip($invalidFields));
				foreach($this->getAggregatorConfig('subscriber.passthrough_data', array()) as $dstField => $srcField) {
					if(is_array($srcField) && method_exists($this, $srcField['func']) && !in_array($srcField['func'],$invalidAccountFunctions)) {
						$raw[$dstField] = $this->{$srcField['func']}($subscriberPlan[$srcField['value']]);
					} else if(!empty($subscriberPlan['passthrough'][$srcField]) && !in_array($srcField, $invalidFields)) {
						$raw[$srcField] = $subscriberPlan['passthrough'][$srcField];
					}
				}
				$raw['sid'] = 0;
				$accounts[$aid]['subscribers'][$raw['sid']][] = $raw;
			} else if (($type === 'subscriber')) {
				if( !empty($this->affectedSids) && !in_array($subscriberPlan['id']['sid'],$this->affectedSids) ) { 
					continue;
				}

				$raw = array_diff_key($subscriberPlan['id'] ,array_flip($invalidFields));
				foreach($this->getAggregatorConfig('subscriber.passthrough_data', array()) as $dstField => $srcField) {
					if(is_array($srcField) && method_exists($this, $srcField['func'])) {
						$raw[$dstField] = $this->{$srcField['func']}($subscriberPlan[$srcField['value']]);
					} else if(!empty($subscriberPlan['passthrough'][$srcField]) && !in_array($srcField, $invalidFields)) {
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
			$accountData['attributes']['invoice_subtype'] = Billrun_Util::getFieldVal($this->invoicingConfig[$this->subInvoiceType]['name'],'regular');;
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
