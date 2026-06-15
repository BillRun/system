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
	protected $constructOptions = [];
	/**
	 * Array of [invoice_id => amount] arrays, in order to force adjusted invoices and amount
	 * @array of arrays
	 */
	protected $adjustments = [];
	
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
		if (isset($options['adjusts'])) {
			$this->adjustments = $options['adjusts'];
		}
		$this->constructOptions = $options;
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
				//Add note
				$this->addNote($aggregatedEntity);
				//Add adjustments
				$this->addAdjustments($aggregatedEntity);
				//Close & Save the billrun document
				$aggregatedEntity->closeInvoice($this->min_invoice_id, FALSE, $customCollName);
				//Save configurable/aggretaion data
				$aggregatedEntity->addConfigurableData();
				$aggregatedEntity->save();
			} else {
				$this->addExternalCharges($aggregatedEntity);
				$aggregatedEntity->finalizeInvoice( $aggregatedResults );
				//Add adjustments
				$this->addAdjustments($aggregatedEntity);
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

		// BRCD-2837: in multi-currency mode tag external charges with the account currency.
		// External charges are operator-entered in the account's currency, so they are
		// tagged (not converted) for consistency with the rest of the invoice lines.
		$accountCurrency = Billrun_CurrencyConvert_Manager::isMultiCurrencyEnabled() ? $aggregatedEntity->getCurrency() : null;

		foreach($externalCharges as &$externalCharge) {
			$externalCharge['billrun'] = $this->getCycle()->key();
			$externalCharge['source'] = 'billrun';
			$externalCharge['billrun_cycle_credit'] = true;
			if (!is_null($accountCurrency) && empty($externalCharge['currency'])) {
				$externalCharge['currency'] = $accountCurrency;
			}
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
			'autoload' => !empty($this->overrideMode),
			'force_active' => !empty($this->constructOptions['force_active_invoice'])
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

	public function shouldLoadSubscriberLines($sid){
 		return in_array($sid, $this->affectedSids) ;
	}

	protected function addNote(Billrun_Cycle_Account $aggregatedEntity) {
		$note = Billrun_Util::getFieldVal($this->constructOptions['note'], null);
		if (!empty($note)) {
			$aggregatedEntity->getInvoice()->setNote($note);
		}
	}

	protected function validateAdjustments(Billrun_Cycle_Account $aggregatedEntity) {
		Billrun_Factory::log("Found " . count($this->adjustments) . " adjustments of invoice . Pulling invoices according to the adjustments list", Zend_Log::DEBUG);
		$adj_total_amount = 0;
		foreach ($this->adjustments as $index => $adjust) {
			$adj_total_amount += $adjust['amount'];
			Billrun_Factory::log("Adjustment index " . $index . " - checking if invoice " . $adjust['invoice_id'] . " exists", Zend_Log::DEBUG);
			$invoice = null;
			$invoice = Billrun_Bill_Invoice::getInvoices(['invoice_id' => $adjust['invoice_id']]);
			if (!empty($invoice)) {
				$invoice_adjusted_amount = isset($invoice['adjusted_by_invoices']) ? abs(array_sum(array_column($invoice['adjusted_by_invoices'], "amount"))) : 0;
				Billrun_Factory::log("Invoice " . $invoice['invoice_id'] . " current adjusted amount is " . $invoice_adjusted_amount, Zend_Log::DEBUG);
			} else {
				Billrun_Factory::log("Couldn't find bill with invoice id " . $adjust['invoice_id'] . " to adjust to the immediate invoice. No invoice was created", Zend_Log::ALERT);
				return false;
			}
		}
		$invoice_amount = $this->isFakeCycle() ? $this->getLastBillrunObj()->getInvoice()->getRawData()['totals']['after_vat_rounded'] : $aggregatedEntity->getInvoice()->getRawData()['totals']['after_vat_rounded'];
		if (($invoice_amount * $adj_total_amount) <= 0) {
			Billrun_Factory::log("Invoice amount and adjustments amount need to be with the same sign. Immediate invoice total amount is " . $invoice_amount . ", while adjusted total amount is " . $adj_total_amount, Zend_Log::ALERT);
			return false;
		}
		if (abs($adj_total_amount) > abs($invoice_amount)) {
			Billrun_Factory::log("Adjusted total amount " . $adj_total_amount . " is bigger than immediate invoice total amount " . $invoice_amount, Zend_Log::ALERT);
			return false;
		}
		return true;
	}

	protected function addAdjustments(Billrun_Cycle_Account $aggregatedEntity) {
		if (empty($this->adjustments)) {
			return;
		}
		if(!$this->validateAdjustments($aggregatedEntity)) {
			throw new Exception("Adjustments validation test didn't pass. No billrun was created");
		}
		$this->isFakeCycle() ? $this->getLastBillrunObj()->getInvoice()->setdAdjustments($this->adjustments) : $aggregatedEntity->getInvoice()->setdAdjustments($this->adjustments);
	}
}