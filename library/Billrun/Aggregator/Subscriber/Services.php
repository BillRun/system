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
	
	/**
	 * Get the services
	 * @param type $billrunKey
	 * @param type $subscriber
	 * @return type
	 */
	protected function getData($billrunKey, Billrun_Subscriber $subscriber) {
		$serviceEntries = array();
		$services = $subscriber->getServices($billrunKey);
		foreach ($services as $service) {
			$servicesChargeEntries = array_merge($serviceEntries, $this->getChargeServicesEntries($subscriber, $billrunKey, $service));
			$serviceEntries = $servicesChargeEntries;
		}

		return $serviceEntries;
	}
	
	protected function getChargeServicesEntries($subscriber, $billrunKey, $service) {
		$charge = $service->getPrice($billrunKey);
		if (isset($charge)) {
			$flatEntries = array($this->getServiceEntry($subscriber, $billrunKey, $service, $charge));
		} else {
			$flatEntries = array();
		}
		return $flatEntries;
	}
	
	protected function getServiceEntry(Billrun_Subscriber $subscriber, $billrunKey, $service, $charge) {
		$start = Billrun_Billrun::getStartTime($billrunKey);
		$startTimestamp = strtotime($start);
		$flatEntry = new Mongodloid_Entity(array(
			'aid' => $subscriber->aid,
			'sid' => $subscriber->sid,
			'source' => 'billrun',
			'billrun' => $billrunKey,
			'type' => 'service',
			'usaget' => 'service',
			'urt' => new MongoDate($startTimestamp),
			'aprice' => $charge,
			'service' => $service->getName(),
//			'plan_ref' => $plan->createRef(),
			'process_time' => new MongoDate(),
		));
		$stamp = md5($subscriber->aid . '_' . $subscriber->sid . $service->getName() . '_' . $start . $billrunKey);
		$flatEntry['stamp'] = $stamp;
		return $flatEntry;
	}
}
