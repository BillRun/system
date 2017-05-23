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
	 * 
	 * @var Billrun_Cycle_Account_Invoice
	 */
	protected $invoice;
	
	/**
	 * Aggregate the data, store the results in the billrun container.
	 * @return array - Array of aggregated results
	 */
	public function aggregate($data = array()) {
		Billrun_Factory::log("Subscriber records to aggregate: " . count($this->records));
		
		$results = parent::aggregate();
		
		Billrun_Factory::log("Account aggregated " . count($results) . ' subscriber records.');
		
		return $results;
	}
	
	/**
	 * Write the invoice to the Billrun collection
	 * @param int $min_id minimum invoice id to start from
	 */
	public function writeInvoice($min_id) {
		foreach ($this->records as $subscriber) {
			$subInvoice = $subscriber->getInvoice();
			$this->invoice->addSubscriber($subInvoice);
		}
		$this->invoice->updateTotals();
		$this->invoice->applyDiscounts();
		$this->invoice->close($min_id);
	}
	
	/**
	 * Validate the input
	 * @param type $input
	 * @return type
	 */
	protected function validate($input) {
		// TODO: Complete
		return isset($input['subscribers']) && is_array($input['subscribers']) &&
			   isset($input['invoice']) && is_a($input['invoice'], 'Billrun_Cycle_Account_Invoice');
	}

	/**
	 * Construct the subscriber records
	 * @param type $data
	 */
	protected function constructRecords($data) {
		$this->invoice = $data['invoice'];
		$this->records = array();
		$subscribers = $data['subscribers'];
		$cycle = $this->cycleAggregator->getCycle();

		$sorted = $this->sortSubscribers($subscribers, $cycle->end());
	

		// Subscriber invoice
		$invoiceData = array();
		$invoiceData['key'] = $cycle->key();

		$aggregatableRecords = array();
		foreach ($sorted as $sid => $subscriberList) {
			Billrun_Factory::log("Constructing records for sid " . $sid);
			$aggregatableRecords[] = $this->constructSubscriber($subscriberList, $invoiceData);;
		}
		Billrun_Factory::log("Constructed: " . count($aggregatableRecords));
		$this->records = $aggregatableRecords;
	}

	/**
	 * Construct the subscriber records for an sid
	 * @param array $sorted - Sorted subscribers by sid
	 * @param array $filtered - Filtered plans ans services
	 * @param array $plans - Raw plan data from the mongo
	 * @param array $rates - Raw rate data from the mongo
	 * @param Billrun_DataTypes_CycleTime $cycle - Current cycle time.
	 * @param array $invoiceData Invoice
	 * @return Billrun_Cycle_Subscriber Aggregateable subscriber
	 */
	protected function constructSubscriber($sorted, $invoiceData) {

		$invoice = new Billrun_Cycle_Subscriber_Invoice($this->cycleAggregator->getRates(), $invoiceData);
		
		$subConstratorData['history'] = $sorted;
		$subConstratorData['subscriber_info'] = reset($sorted);
		$subConstratorData['subscriber_info']['invoice'] = &$invoice;
		$subConstratorData['subscriber_info']['line_stump'] = $this->getLineStump(end($sorted), $this->cycleAggregator->getCycle());
		$cycleSub =  new Billrun_Cycle_Subscriber($subConstratorData, $this->cycleAggregator);

		return $cycleSub;
	}
	
	protected function getLineStump(array $subscriber, Billrun_DataTypes_CycleTime $cycle) {
		$flatEntry = array(
			'aid' => $subscriber['aid'],
			'sid' => $subscriber['sid'],
			'source' => 'billrun',
			'billrun' => $cycle->key(),
			'type' => 'flat',
			'usaget' => 'flat',
			'urt' => new MongoDate($cycle->end()),
		);
		
		return $flatEntry;
	}
	
	/**
	 * 
	 * @param type $subscribers
	 * @param type $endTime
	 * @return type
	 */
	protected function sortSubscribers($subscribers, $endTime) {
//		$sorted = $subscribers;
		
		foreach ($subscribers as &$subscriberRecs) {
			foreach ($subscriberRecs as &$subscriberRecord) {
				$subscriberRecord = $this->handleSubscriberDates($subscriberRecord, $endTime);
			}
		}
		//sort each of the subscriber histort from past to present
//		foreach($subscribers as  &$subHistory) {			
//			usort($subHistory, function($a, $b){ return $a['sto'] - $b['sto'];});
//		}
		return $subscribers;
	}
	
	protected function handleSubscriberDates($subscriber, $endTime) {
		$to = $subscriber['to'];
		$from = $subscriber['from'];

		if($to > $endTime) {
			$to = $endTime;
			Billrun_Factory::log("Taking the end time! " . $endTime);
		}
		
		$subscriber['firstname'] = $subscriber['first_name'];
		$subscriber['lastname'] = $subscriber['last_name'];
		$subscriber['sfrom'] = $from;
		$subscriber['sto'] = $to;
		$subscriber['from'] = date(Billrun_Base::base_datetimeformat, $from);
		$subscriber['to'] = date(Billrun_Base::base_datetimeformat, $to);
		
		return $subscriber;
	}
	
	
	public function getInvoice() {
		return $this->invoice;
	}
	
	public function getAppliedDiscounts() {
		return $this->invoice->getAppliedDiscounts();
	}
	
	//--------------------------------------------------
	
	public function save() {
		$this->invoice->save();
	}
		
	
}
