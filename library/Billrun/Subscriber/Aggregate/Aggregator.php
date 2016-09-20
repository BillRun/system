<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a record aggregator
 *
 * @package  Subscriber Aggregate
 * @since    5.2
 */
abstract class Billrun_Subscriber_Aggregate_Aggregator {
	
	/**
	 * Array of records to be aggregated
	 * @var Billrun_Subscriber_Aggregate_Base
	 * @todo Use data table here when the code is merged
	 */
	protected $records = array();
	
	/**
	 * Aggregate records with the current billrun key
	 * @param array Array of subscriber objects.
	 * @return array of aggregated records.
	 */
	public function aggregate($subscribers, $billrunKey) {
		$aggregated = array();
		
		$records = $this->getRecords($subscribers);
		
		// Charge
		foreach ($records as $aggreateRecord) {
			$values = $aggreateRecord->getValues();
			$charge = $this->getCharge($values, $billrunKey);
			$aggregated[$values['key']] = $charge;
		}
		return $aggregated;
	}

	abstract protected function getCharge(array $values, string $billrunKey);

	/**
	 * 
	 * @param array $output - The aggregated record output.
	 */
	protected function aggregateRecord(array $output) {
		$charge = $this->getCharge($output['key'], $output['dates']);
		return array("key" => $output['key'], "charge" => $charge);
	}
	
	/**
	 * Get all the records to be aggregateds.
	 * @param array $subscribers - Array of active subscribers.
	 * @return Billrun_Subscriber_Aggregate_Base array record type
	 * @todo Should return data table when the code is merged
	 */
	protected abstract function getRecords($subscribers);
}
