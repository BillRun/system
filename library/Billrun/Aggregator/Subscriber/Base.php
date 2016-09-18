<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * base subscriberaggregator.
 *
 * @package  Aggregator
 * @since    5.1
 */
abstract class Billrun_Aggregator_Subscriber_Base {

	/**
	 * Instance of the lines collection.
	 * @var Mongodloid_Collection
	 */
	protected $lines;
	
	public function __construct() {
		$this->lines = Billrun_Factory::db()->linesCollection;
	}
	
	/**
	 * Create the lines array to be saved.
	 * @param Billrun_Subscriber $subscriber
	 * @param string $billrunKey the billrun key
	 * @return array of inserted lines
	 */	
	public function save(Billrun_Subscriber $subscriber, $billrunKey) {
		$data = $this->getData($billrunKey, $subscriber);
		$ret = array();
		foreach ($data as $record) {
			$rawData = $record->getRawData();
			try {
				$this->lines->insert($rawData, array("w" => 1));
			} catch (Exception $e) {
				$this->handleException($e, $subscriber, $billrunKey, $rawData);
			}
			$ret[$record['stamp']] = $record;
		}
		return $ret;
	}
	
	/**
	 * Get the data to be aggregated 
	 * @param string $billrunKey - The billrun key.
	 * @param Billrun_Subscriber $subscriber - Subscriber to get the data by.
	 */
	protected abstract function getData($billrunKey, Billrun_Subscriber $subscriber);
	
	/**
	 * Handle an exception in the save function.
	 * @param Exception $e
	 * @param type $subscriber
	 * @param type $billrunKey
	 * @param type $rawData
	 * @return boolean true if should log the failure.
	 */
	 protected function handleException(Exception $e, $subscriber, $billrunKey, $rawData) {
		if ($e->getCode() == Mongodloid_General::DUPLICATE_UNIQUE_INDEX_ERROR) {
			Billrun_Factory::log("Record already exists for subscriber " . $subscriber->sid . " for billrun " . $billrunKey . " details: " . print_R($rawData, 1), Zend_Log::ALERT);
			return false;
		}
		
		return true;
	 }
}
