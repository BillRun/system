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
	 */
	public function finalizeInvoice($flatLines) {
		foreach ($this->records as $subscriber) {
			$subInvoice = $subscriber->getInvoice();
			$this->invoice->addSubscriber($subInvoice);
		}
		$this->invoice->updateTotals();
		$this->applyDiscounts($flatLines);

	}

	public function closeInvoice($min_id, $isFake = FALSE, $customCollName = FALSE) {
		$this->invoice->updateTotals();
		$this->invoice->close($min_id, $isFake, $customCollName);
	}
	
	public function addConfigurableData() {
		$this->invoice->addConfigurableData();
	}

	public function getInvoice() {
		return $this->invoice;
	}

	public function getAppliedDiscounts() {
		return $this->discounts;
	}

	public function &getSubscriber($sid) {
		foreach ($this->records as &$subscriber) {
			if($subscriber->getSid() == $sid) {
				return $subscriber;
			}
		}
		return null;
	}

	public function setUserFields(array $user_fields){
		$this->invoice->setUserFields($user_fields);
	}

	public function applyDiscounts($flatLines) {
		Billrun_Factory::log('Applying discounts.', Zend_Log::DEBUG);

		$subscribersRevisions= [];
		//Assuming this->records are sorted by 'from' field
		$accountRevs =[];
		foreach ($this->revisions as $revSid => $subscriberRevArr) {
			$subRevisions = [];
			foreach ($subscriberRevArr as $subscriberRev) {
				if($subscriberRev['sid'] == 0) {
					//No need to expand  account revisions (no dated sub-fields )
					$accountRevs[] = $subscriberRev;
					continue;
				}
				$subRevisions = array_merge( $subRevisions,
							$this->expandSubRevisions($subscriberRev,$this->cycleAggregator->getCycle()->start(),$this->cycleAggregator->getCycle()->end()) );
			}
			if(!empty($subRevisions)) {
				$subscribersRevisions[$revSid] = $subRevisions;
			}
		}
		$dm = new Billrun_DiscountManager($accountRevs, $subscribersRevisions, $this->cycleAggregator->getCycle());
		$this->discounts = $dm->generateCdrs($flatLines);
		foreach ($this->discounts as &$cdr) {
			$cdr = is_array($cdr) ? new Mongodloid_Entity($cdr) : $cdr;
		}
		$this->invoice->applyDiscounts($this->discounts);
	}

	//---------------------------------- Protected ------------------------------------
	/**
	 * Break revision to sub-revisions  based on changes in dated fields such as services / plans
	 */
	protected function expandSubRevisions($revision, $minFrom, $maxTo) {
		$retRevisions = [];
		$cutDates = [];
		$subRevCopyFields = Billrun_Factory::config()->getConfigValue('billrun.subscriber.sub_revision_fields_to_copy',['plan','plan_activation','plan_deactivation']);
		$revision['from'] = max($minFrom,$revision['from']);
		$revision['to'] = min($maxTo,$revision['to']);
		$subRevisionsFields = Billrun_Factory::config()->getConfigValue('billrun.subscriber.sub_revision_fields',['services','plans']);
		$mongoServices = $this->cycleAggregator->getServices();

		//Retrive all the relevent change dates
		foreach($subRevisionsFields as $fieldName) {
			if(empty($revision[$fieldName])) { continue; }
			foreach($revision[$fieldName] as $subRev) {
					 if($subRev['from']->sec >= $revision['to'] || $subRev['to']->sec < $revision['from'] ||
						$fieldName == 'services' && $this->isServiceTerminatedDueToConfig($subRev, $revision['from'],$revision['to']) ) { // TODO fix hard coding
						continue;
					 }
					 $customFieldCut = $subRev['to']->sec;
					 if(!empty($subRev['name']) && !empty($mongoServices[$subRev['name']])) {
							$creationTime = !empty($subRev['creation_time']) ? $subRev['creation_time']->sec : $subRev['from'];
							if(is_array($mongoServices[$subRev['name']]['price'])) {
								foreach($mongoServices[$subRev['name']]['price'] as $price) {
									if ($price['to'] == 'UNLIMITED') {
										$customFieldCut = $subRev['to']->sec;
										break ;
									}
									$customFieldCut = Billrun_Plan::monthDiffToDate($price['to'],$creationTime,true,false,true);
								}
							}
						}
					 $subRev['from'] = max($subRev['from']->sec,$revision['from']);
					 $subRev['to'] = min($subRev['to']->sec,$revision['to'],$customFieldCut);

					 $cutDates[$subRev['from']][$subRev['to']][$fieldName][] = $subRev;
			}
			unset($revision[$fieldName]);
		}


		if(empty($cutDates)) {
			// No changes? no problem! just adjust the current revison and return it
			$retRevisions[] = $this->cleanRevisionStructure($revision, $subRevisionsFields);
		} else  {
			$fieldsEnded = [];
			$fieldsAdded = [];
			// Sort the changes by "from" dates
			uksort($cutDates,function($a,$b){ return $a - $b; });
			$activeRev = $revision;

			foreach($cutDates as $from => $fromCuts) {
				// Sort the from dates changes by their erliest "to" date
				uksort($fromCuts,function($a,$b){ return $a - $b; });
				//Get all the ended services plan under the current "from" date
				foreach($fromCuts as $to => $toCuts) {
					foreach($toCuts as $fieldName => $fieldCuts) {
						foreach($fieldCuts as  $fieldCut) {
							//should we break the revision? (as the  cut  start after the  revisoin started)
							if($activeRev['from'] < $fieldCut['from'] ) {
								$revClosed = unserialize(serialize($activeRev));
								$revClosed['to'] = min($fieldCut['from'],$activeRev['to']);
								$activeRev['from'] =  $fieldCut['from'];
								$fieldsEnded[] = $revClosed;
							}
							//copy fields to the root of the revision if need
							foreach($subRevCopyFields as $subRevField) {
								if( empty($activeRev[$subRevField]) && !empty($fieldCut[$subRevField]) ) {
									$activeRev[$subRevField] = $fieldCut[$subRevField];
								}
							}
							if($activeRev['to'] > $fieldCut['to']) {
								//current revision is  ending  after the current cut save the cut and advance the  revision to the end of the cut
								$activeRev['from'] = $fieldCut['to'];
								$fieldsEnded[] = [
								'from' => $fieldCut['from'],
								'to' => $fieldCut['to'],
								$fieldName => [$fieldCut]];
							}
							//add the service/plan to the revision (was deleted when the changes were recoreded)
							$activeRev[$fieldName][] = $fieldCut;

						}
					}
				}
			}
			$revCuts =  array_merge($fieldsEnded,[$activeRev]);
			usort($revCuts,function($a,$b){ return $a['to'] - $b['to']; });
			usort($fieldsEnded,function($a,$b){ return $a['from'] - $b['from']; });
			if (is_array($fieldsEnded) || is_object($fieldsEnded)) {
				//Create revision for all the terminated services/plans under the current "from" date
			foreach($revCuts as $endedField) {
					//close the current revision if its "to" has changed and open a new one.
					if($endedField['to'] < $activeRev['to'] ) {
						$activeRev['to'] =  $endedField['to']; // change the saved (closed) revision from & to  fields to match the ended revision from &  to values
						$activeRev['from'] =  $endedField['from'];
						$saveRevision  = $this->cleanRevisionStructure($activeRev, $subRevisionsFields, $endedField);
						if( $saveRevision['from']->sec != $saveRevision['to']->sec ) {
							$retRevisions[] = $saveRevision;
						}

						$activeRev['from'] = $endedField['to'];
						$activeRev['to'] = $revision['to'];
						//should services/plans be removed from the revision?
						$activeRev = $this->clearInactiveEntries($activeRev, $subRevisionsFields,$endedField);
					}
				}
				//close the current revision and open a new revision if the last one had altered the 'to' date to be before the  end of the cycle
				if($activeRev['to'] < $revision['to']) {
					$saveRevision  = $this->cleanRevisionStructure($activeRev, $subRevisionsFields, $endedField);
					if( $saveRevision['from']->sec != $saveRevision['to']->sec ) {
						$retRevisions[] = $saveRevision;
					}
					$activeRev['from'] = $activeRev['to'];
					$activeRev['to'] = $revision['to'];
				}
			}
			//Save the last revision
			$retRevisions[] = $this->cleanRevisionStructure($activeRev, $subRevisionsFields);
		}
		usort($retRevisions,function($a,$b){ return $a['from']->sec - $b['from']->sec; });
		return $retRevisions;
	}

	/**
	 * Check is a service is not active in a given date span regardless of the service from/to fields.
	 * @returns FALSE if the  service is active
	 *			TRUE  if the service is terminated
	 */
	protected function isServiceTerminatedDueToConfig($subRev,$minFrom,$maxTo) {
		$mongoServices = $this->cycleAggregator->getServices();
		if( isset($mongoServices[$subRev['name']]) ) {
			$servicesArr = is_array($mongoServices[$subRev['name']]) ? $mongoServices[$subRev['name']]  :  [$mongoServices[$subRev['name']]];
			foreach($servicesArr as $service) {
				$creationTime = min((!empty($subRev['creation_time']) ? $subRev['creation_time'] : new Mongodloid_Date()), $subRev['from']);
				if( $maxTo > $service['from']->sec && $maxTo <= $service['to']->sec ) {
					if(Billrun_Plans_Util::hasPriceWithinDates($service,$creationTime->sec,$minFrom,$maxTo) &&
					   Billrun_Plans_Util::balancePeriodWithInDates($service,$creationTime->sec,$minFrom,$maxTo) ) {
						return FALSE;
					}
				}
			 }
		}

		return TRUE;
	}


	protected function clearInactiveEntries($activeRev,$subRevisionsFields,$endedField) {
		foreach($subRevisionsFields as $fieldName) {
			if(!empty($endedField[$fieldName]) || !empty($activeRev[$fieldName])) {
				$activeRev[$fieldName] =array_values (
						array_map(function($m1) { return unserialize($m1);},
						array_unique(
							array_map(function($m2) { return  serialize($m2);},
								array_filter(array_merge(!empty($activeRev[$fieldName]) ? $activeRev[$fieldName] : [],
														 !empty($endedField[$fieldName]) ? $endedField[$fieldName]:[]),
											 function($b) use ($activeRev) {
									return (!empty($b) && $b['to'] > $activeRev['from'] && $b['from'] <= $activeRev['from']);})
							)
						)
					)
				);
			}
		}
		return $activeRev;
	}

	/**
	 * Clean uneeded fields and copy fields to thier needed place to finalize the revision.
	 */
	 protected function cleanRevisionStructure($saveRevision,$subRevisionsFields, $endedField = null) {
		$endedField = empty($endedField) ? $saveRevision : $endedField;
		$saveRevision = $this->clearInactiveEntries($saveRevision, $subRevisionsFields, $endedField);
		$saveRevision['to'] = new Mongodloid_Date($endedField['to']);
		$saveRevision['from'] = new Mongodloid_Date($saveRevision['from']);
		foreach(Billrun_Factory::config()->getConfigValue('billrun.subscriber.sub_revision_fields_to_copy',['plan']) as $subRevField) {
			if( isset($saveRevision[$subRevField]) && !empty($endedField[$subRevField]) ) {
				$saveRevision[$subRevField] = $endedField[$subRevField];
			}
		}
		foreach($subRevisionsFields as $fieldName) {
			if(empty($saveRevision[$fieldName])) { continue; }
			foreach($saveRevision[$fieldName] as &$subRev) {
				unset($subRev['from'],$subRev['to']);
			}
		}
		return $saveRevision;
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
			usort($subscriberList,function($a,$b){ return  $a['from'] - $b['from'];});
			Billrun_Factory::log("Constructing records for sid " . $sid);
			Billrun_Factory::dispatcher()->trigger('beforeConstructSubscriber',[&$data, &$subscriberList, &$invoiceData, $subsCount, $this]);
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

		$data = [
			'rates' => $this->cycleAggregator->getRates(null,end($sorted)),
			'discounts' => $this->cycleAggregator->getDiscounts(null,end($sorted)),
                        'charges' => $this->cycleAggregator->getCharges(),
		];
		$invoice = new Billrun_Cycle_Subscriber_Invoice($data, $invoiceData);

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
			'urt' => new Mongodloid_Date($cycle->end()),
		);
		
		return $flatEntry;
	}

	//--------------------------------------------------
	
	public function save() {
		$this->invoice->save();
	}
		
	
}
