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
	 * Hold all the discounts that are  applied to the account.
	 * @var array
	 */
	protected $discounts= array();


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
	public function writeInvoice($min_id, $isFake = FALSE, $customCollName = FALSE) {
		foreach ($this->records as $subscriber) {
			$subInvoice = $subscriber->getInvoice();
			$this->invoice->addSubscriber($subInvoice);
		}
		$this->invoice->updateTotals();
		$this->applyDiscounts();
		$this->invoice->close($min_id, $isFake, $customCollName);
	}

	public function getInvoice() {
		return $this->invoice;
	}

	public function getAppliedDiscounts() {
		return $this->discounts;
	}

	public function applyDiscounts() {
		Billrun_Factory::log('Applying discounts.', Zend_Log::DEBUG);
		$dm = new Billrun_DiscountManager();

		$subscribersRevisions= [];
		Billrun_Factory::log(json_encode($this->records));
		//Assuming this->records are sorted by 'from' field
		$accountRevs =[];
		foreach ($this->revisions as $subscriberRev) {
			if($subscriberRev['sid'] == 0) {
				$accountRevs[] = $subscriberRev;
				continue;
			}
			array_merge( $subscribersRevisions,
						$this->expandSubRevisions($subscriberRev,$this->cycleAggregator->getCycle()->start(),$this->cycleAggregator->getCycle()->end()) );
		}
		Billrun_Factory::log(json_encode($subscribersRevisions));

		//$this->discounts = $dm->getEligibleDiscounts($this->invoice,$subscribersRevisions,$this->cycleAggregator->getCycle());
		$this->invoice->applyDiscounts();
	}

	//---------------------------------- Protected ------------------------------------

	protected function expandSubRevisions($revision, $minFrom, $maxTo) {
		$retRevisions = [];
		$cutDates = [];
		$revision['from'] = max(new MongoDate($minFrom),$revision['from']);
		$revision['to'] = min(new MongoDate($maxTo),$revision['to']);

		$subRevisionsFields = Billrun_Factory::config()->getConfigValue('billrun.subscriber.sub_revision_fields',['services']);
		foreach($subRevisionsFields as $fielName) {
			foreach($revision[$fieldName] as $subRev) {
					 if($subRev['from'] > $maxTo || $subRev['to'] < $minFrom) {
						continue;
					 }
					 $subRev['from'] = max($subRev['from'],$revision['from']);
					 $subRev['to'] = min($subRev['to'],$revision['to']);
					 $cutDates[$subRev['from']][$subRev['to']][$fieldName][] = $subRev;
			}
			unset($revision[$fieldName]);
		}

		if(empty($cutDates)) {
			$retRevisions[] = $revision;
		} else  {
			$sortedDates =  usort($fieldCuts,function($a,$b){ return $a['from']->sec - $b['from']->sec; });
			$activeRev = $revision;
			foreach($sortedDates as $from => $fromCuts) {
				$sortedToDates =  usort($fromCuts,function($a,$b){ return $a['to']->sec - $b['to']->sec; });
				foreach($sortedToDates as  $toCuts) {
					foreach($toCuts as $fieldName => $fieldCuts) {
						//should  we  breate the  revision
						if($activeRev['to'] < $fieldCuts['from'] ) {
							$activeRev['to'] = $fieldCuts['from'];
						}
						if($activeRev['to'] < $fieldCuts['to']) {
							$activeRev['to'] = $fieldCuts['to'];
							$fieldToUnset = true;
						}
						//add the service
						unset($fieldCuts['from'],$fieldCuts['to']);
						$activeRev[$fieldName][] = $fieldCuts;
						//
						if($activeRev['to'] < $revision['to']) {
							$saveRevision  = $activeRev;
							$retRevisions[] = $saveRevision;
							$activeRev['from'] = $activeRev['to'];
							$activeRev['to'] = $revision['to'];
						}
						if(!empty($fieldToUnset)) {
							array_pop($activeRev[$fieldName]);
						}
					}
				}
			}
		}
		return $retRevisions;
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
		$this->revisions = $data['subscribers'];
		$subscribers = $data['subscribers'];
		$subsCount = count($subscribers);
		$cycle = $this->cycleAggregator->getCycle();

		// Subscriber invoice
		$invoiceData = array();
		$invoiceData['key'] = $cycle->key();

		$aggregatableRecords = array();
		foreach ($subscribers as $sid => $subscriberList) {
			Billrun_Factory::log("Constructing records for sid " . $sid);
			$aggregatableRecords[] = $this->constructSubscriber($subscriberList, $invoiceData, $subsCount);
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
	protected function constructSubscriber($sorted, $invoiceData, $subsCount = 0 ) {

		$invoice = new Billrun_Cycle_Subscriber_Invoice($this->cycleAggregator->getRates(), $invoiceData);

		$invoice->setShouldKeepLinesinMemory($this->invoice->shouldKeepLinesinMemory($subsCount));
		$invoice->setShouldAggregateUsage( $subsCount < Billrun_Factory::config()->getConfigValue('billrun.max_subscribers_to_aggregate',500) );
		$subConstratorData['history'] = $sorted;
		$subConstratorData['subscriber_info'] = end($sorted);
		$subConstratorData['subscriber_info']['invoice'] = &$invoice;
		$subConstratorData['subscriber_info']['line_stump'] = $this->getLineStump(end($sorted), $this->cycleAggregator->getCycle());
		$cycleSub =  new Billrun_Cycle_Subscriber($subConstratorData, $this->cycleAggregator);

		return $cycleSub;
	}
	
	protected function getLineStump(array $subscriber, Billrun_DataTypes_CycleTime $cycle) {
		$flatEntry = array(
			'aid' => intval($subscriber['aid']),
			'sid' => intval($subscriber['sid']),
			'source' => 'billrun',
			'billrun' => $cycle->key(),
			'type' => 'flat',
			'usaget' => 'flat',
			'urt' => new MongoDate($cycle->end()),
		);
		
		return $flatEntry;
	}

	//--------------------------------------------------
	
	public function save() {
		$this->invoice->save();
	}
		
	
}
