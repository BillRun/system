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
		foreach ($this->revisions as $subscriberRevArr) {
			foreach ($subscriberRevArr as $subscriberRev) {
				Billrun_Factory::log(json_encode($subscriberRev));
				if($subscriberRev['sid'] == 0) {
					//No need to expand  account revisions (no dated sub-fields )
					$accountRevs[] = $subscriberRev;
					continue;
				}
				$subscribersRevisions = array_merge( $subscribersRevisions,
							$this->expandSubRevisions($subscriberRev,$this->cycleAggregator->getCycle()->start(),$this->cycleAggregator->getCycle()->end()) );
			}
		}
		Billrun_Factory::log(json_encode($subscribersRevisions,JSON_PRETTY_PRINT));
		Billrun_Factory::log(json_encode($accountRevs));

		$this->discounts = $dm->getEligibleDiscounts($this->invoice,$subscribersRevisions,$this->cycleAggregator->getCycle());
		$this->invoice->applyDiscounts($this->discounts);
	}

	//---------------------------------- Protected ------------------------------------

	protected function expandSubRevisions($revision, $minFrom, $maxTo) {
		$retRevisions = [];
		$cutDates = [];
		$revision['from'] = max($minFrom,$revision['from']);
		$revision['to'] = min($maxTo,$revision['to']);

		$subRevisionsFields = Billrun_Factory::config()->getConfigValue('billrun.subscriber.sub_revision_fields',['services','plans']);
		//Retrive all the relevent change dates
		foreach($subRevisionsFields as $fieldName) {
			if(empty($revision[$fieldName])) { continue; }
			foreach($revision[$fieldName] as $subRev) {
					 if($subRev['from']->sec > $maxTo || $subRev['to']->sec < $minFrom ||
						$fieldName == 'services' && $this->isServiceTerminatedDueToConfig($subRev,$minFrom,$maxTo) ) { // TODO fix hard coding
						continue;
					 }
					 $subRev['from'] = max($subRev['from']->sec,$revision['from']);
					 $subRev['to'] = min($subRev['to']->sec,$revision['to']);
					 $cutDates[$subRev['from']][$subRev['to']][$fieldName][] = $subRev;
			}
			unset($revision[$fieldName]);
		}
		// No changes? no problem! just adjust th  current revison
		if(empty($cutDates)) {
			foreach($subRevisionsFields as $fieldName) {
				if(empty($revision[$fieldName])) { continue; }
				foreach($revision[$fieldName] as &$subRev) {
					unset($subRev['from'],$subRev['to']);
				}
			}
			$retRevisions[] = $revision;
		} else  {
			$fieldsEnded = [];
			// sort  the changes by from dates
			uksort($cutDates,function($a,$b){ return $a - $b; });
			$activeRev = $revision;
			foreach($cutDates as $from => $fromCuts) {
				// sort the from dates changes by  their  erliest to date
				uksort($fromCuts,function($a,$b){ return $a - $b; });
				foreach($fromCuts as $toCuts) {
					foreach($toCuts as $fieldName => $fieldCuts) {
						foreach($fieldCuts as  $fieldCut) {
							//should  we  break the  revision?
							if($activeRev['from'] < $fieldCut['from'] ) {
								$activeRev['to'] = min($fieldCut['from'],$activeRev['to']);
							}
							if($activeRev['to'] > $fieldCut['to']) {
								$fieldsEnded[] = $fieldCut;
							}
							foreach(Billrun_Factory::config()->getConfigValue('billrun.subscriber.sub_revision_fields_to_copy',['plan']) as $subRevField) {
								if( isset($activeRev[$subRevField]) &&	!empty($fieldCut[$subRevField]) ) {
									$activeRev[$subRevField] = $fieldCut[$subRevField];
								}
							}
							//add the service/plan to the revision (was deleted when the changes were recoreded)
							//unset($fieldDataToSave['from'],$fieldDataToSave['to']);
							$activeRev[$fieldName][] = $fieldCut;


						}
					}
				}
				$fieldsEnded =  array_merge([$activeRev],$fieldsEnded);
				foreach($fieldsEnded as $endedField) {
					//close  the current  revision and open a new one.
					if($endedField['to'] < $activeRev['to'] ) {
						$saveRevision  = $activeRev;
						$saveRevision['to'] = new MongoDate($endedField['to']);
						$saveRevision['from'] = new MongoDate($saveRevision['from']);
						foreach(Billrun_Factory::config()->getConfigValue('billrun.subscriber.sub_revision_fields_to_copy',['plan']) as $subRevField) {
							if( isset($saveRevision[$subRevField]) && !empty($endedField[$subRevField]) ) {
								$saveRevision[$subRevField] = $endedField[$subRevField];
							}
						}
						if( $saveRevision['from']->sec != $saveRevision['to']->sec ) {
							$retRevisions[] = $saveRevision;
						}
						$activeRev['from'] = $endedField['to'];
						$activeRev['to'] = $revision['to'];
						//should services/plans be removed from the revision?
						foreach($subRevisionsFields as $fieldName) {
							if(!empty($fieldsEnded) && !empty($activeRev[$fieldName])) {
								$activeRev[$fieldName] = array_udiff($activeRev[$fieldName],$fieldsEnded,function($a,$b) use ($saveRevision) { return $b['to'] > $saveRevision['to']->sec && $b['from'] <= $saveRevision['to']->sec ? 1 : strcmp(json_encode($a) ,json_encode($b));});
							}
						}
					}
				}
			}
			$activeRev['to'] = new MongoDate($activeRev['to']);
			$activeRev['from'] = new MongoDate($activeRev['from']);
			$retRevisions[] = $activeRev;
		}
		usort($retRevisions,function($a,$b){ return $a['from']->sec - $b['from']->sec; });
		return $retRevisions;
	}

	protected function isServiceTerminatedDueToConfig($subRev,$minFrom,$maxTo) {
		$mongoServices = $this->cycleAggregator->getServices();
		if( isset($mongoServices[$subRev['name']]) ) {
			$servicesArr = is_array($mongoServices[$subRev['name']]) ? $mongoServices[$subRev['name']]  :  [$mongoServices[$subRev['name']]];
			foreach($servicesArr as $service) {
				if( $subRev['from'] >= $service['from'] && $maxTo < $service['to']->sec ) {
					if(Billrun_Plans_Util::hasPriceWithinDates($service,$subRev['creation_time']->sec,$minFrom,$maxTo) &&
					   Billrun_Plans_Util::balancePeriodWithInDates($service,$subRev['creation_time']->sec,$minFrom,$maxTo) ) {
						return FALSE;
					}
				}
			 }
		}

		return TRUE;

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
