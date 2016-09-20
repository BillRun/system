<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Subscriber services aggregator.
 *
 * @package  Aggregator
 * @since    5.1
 */
class Billrun_Aggregator_Subscriber_Services extends Billrun_Aggregator_Subscriber_Base {
	
	/**
	 * Handle an exception in the save function.
	 * @param Exception $e
	 * @param type $subscriber
	 * @param type $billrunKey
	 * @param type $rawData
	 * @return type
	 */
	protected function handleException(Exception $e, $subscriber, $billrunKey, $rawData) {
		if(!parent::handleException($e, $subscriber, $billrunKey, $rawData)) {
			return false;
		}
		
		Billrun_Factory::log("Problem inserting service for subscriber " . $subscriber->sid . " for billrun " . $billrunKey
			. ". error message: " . $e->getMessage() . ". error code: " . $e->getCode() . ". service details:" . print_R($rawData, 1), Zend_Log::ALERT);
		Billrun_Util::logFailedServiceRow($rawData);
	}
	
	protected function getSubscribersInCycle($sid, $billrunKey) {
		$query = array();
		$query['sid'] = $sid;
		$query['type'] = "subscriber";
		$start = Billrun_Billrun::getStartTime($billrunKey);
		$query['to'] = array('$gte' => $start);
		$end = Billrun_Billrun::getEndTime($billrunKey);
		$query['from'] = array('$lt' => $end);
		
		$subColl = Billrun_Factory::db()->subscribersColection();
		$cursor = $subColl->query($subColl)->cursor();
		
		$subscribers = array();
		foreach ($cursor as $subscriberRecord) {
			$subscribers[] = $subscriberRecord->getRawData();
		}
		return $subscribers;
	}
	
	/**
	 * Get the services
	 * @param type $billrunKey
	 * @param type $subscriber
	 * @return type
	 */
	protected function getData($billrunKey, Billrun_Subscriber $subscriber) {
		// Get all the active subscriber records for the current cycle with the input sid.
		$subscribers = $this->getSubscribersInCycle($subscriber->getId(), $billrunKey);
		$servicesAggregator = new Billrun_Subscriber_Aggregate_Services();
		$aggregated = $servicesAggregator->aggregate($subscribers, $billrunKey);
		
		$serviceEntries = array();
		foreach ($aggregated as $name => $charge) {
			if(!$charge) {
				continue;
			}
			$serviceEntry = $this->getServiceEntry($subscriber, $billrunKey, $name, $charge);
			$serviceEntries[] = $serviceEntry;
		}

		return $serviceEntries;
	}
	
	protected function getServiceEntry(Billrun_Subscriber $subscriber, $billrunKey, $serviceName , $charge) {
		$start = Billrun_Billrun::getStartTime($billrunKey);
		$startTimestamp = strtotime($start);
		$entry = new Mongodloid_Entity(array(
			'aid' => $subscriber->aid,
			'sid' => $subscriber->sid,
			'source' => 'billrun',
			'billrun' => $billrunKey,
			'type' => 'service',
			'usaget' => 'service',
			'urt' => new MongoDate($startTimestamp),
			'aprice' => $charge,
			'service' => $serviceName,
//			'plan_ref' => $plan->createRef(),
			'process_time' => new MongoDate(),
		));
		$stamp = md5($subscriber->aid . '_' . $subscriber->sid . $serviceName . '_' . $start . $billrunKey);
		$entry['stamp'] = $stamp;
		return $entry;
	}
}
