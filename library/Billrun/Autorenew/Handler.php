<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 4; see LICENSE.txt
 */

/**
 * Handle the auto renew service process
 *
 */
class Billrun_Autorenew_Handler {
	
	protected $activeDate;

	public function __construct($params) {
		$this->activeDate = time();
		if (!empty($params['active_date'])) {
			$inputDate = $params['active_date'];
			if ($inputDate <= $this->activeDate) {
				$this->activeDate = $inputDate;
			}
		}
	}

	/**
	 * Get the query to return the monthly auto renew records.
	 * @return array - query
	 */
	protected function getMonthAutoRenewQuery() {
		$or = array();
		$or['interval'] = 'month';

		$monthLower = strtotime('midnight',  $this->activeDate);
		$monthUpper = strtotime('tomorrow',  $this->activeDate)-1;

		$or['next_renew_date'] = array('$gte' => new MongoDate($monthLower), '$lte' => new MongoDate($monthUpper));

		return $or;
	}

	/**
	 * Get the query to return the daily auto renew records.
	 * @return array - query
	 */
	protected function getDayAutoRenewQuery() {
		$dayLower = strtotime('midnight',  $this->activeDate);
		$dayUpper = strtotime('tomorrow',  $this->activeDate)-1;

		$or = array();
		$or['next_renew_date'] = array('$gte' => new MongoDate($dayLower), '$lte' => new MongoDate($dayUpper));
		$or['interval'] = 'day';

		return $or;
	}

	/**
	 * Get the auto renew services query.
	 * @return array - Query date.
	 */
	protected function getAutoRenewServicesQuery() {
		$orQuery = array();
		$dayQuery = $this->getDayAutoRenewQuery();
		$monthQuery = $this->getMonthAutoRenewQuery();

		$orQuery[] = $dayQuery;
		$orQuery[] = $monthQuery;
		$queryDate = array('$or' => $orQuery);
		$queryDate['remain'] = array('$gt' => 0);
		return $queryDate;
	}

	public function autoRenewServices() {
		$queryDate =array();// $this->getAutoRenewServicesQuery();
		$collection = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
		$autoRenewCursor = $collection->query($queryDate)->cursor();
		Billrun_Factory::log("Autorenew handler load " . $autoRenewCursor->count() . " records", Zend_Log::INFO);
		$manager = new Billrun_Autorenew_Manager();

		$counter = 0;
		$counterDone = 0;
		
		// Go through the records.
		foreach ($autoRenewCursor as $autoRenewRecord) {
			$counter++;
			// Check if the record is invalid.
			// TODO: This validation is also done inside the autorenew action manager,
			// we should consider this check for debug purposes, or create a proper validatio
			// for each record (which is probably an overkill - corrupted records are a rare incident
			// which is already supposed to be reported in the autorenew action manager).
			if(!isset($autoRenewRecord['sid'])) {
				Billrun_Factory::log("Invalid autorenew record: " . print_r($autoRenewRecord,1), Zend_Log::ERR);
				continue;
			}
			
			try {				
				$record = $manager->getAction($autoRenewRecord);
				if (!$record) {
					Billrun_Factory::log("Auto renew services failed to create record handler", Zend_Log::ALERT);
					continue;
				}
				Billrun_Factory::log("Updating autorenew record for sid: " . $autoRenewRecord['sid']);
				$record->update();
				Billrun_Factory::dispatcher()->trigger('afterSubscriberBalanceAutoRenewUpdate', array($autoRenewRecord));
				$counterDone++;
			} catch (Exception $ex) {
				Billrun_Factory::log("Error on autorenew handler. " . $ex->getCode() . ": " . $ex->getMessage(), Zend_Log::ERR);
			}
		}
		
		Billrun_Factory::log("Finished autorenew handler | Counted: " . $counter . " Done: " . $counterDone);
	}

	/**
	 * Check if we are in 'dead' days
	 * @return boolean
	 */
	protected function areDeadDays() {
		$lastDayLastMonth = date('d', strtotime('last day of previous month'));
		$today = date('d');

		if ($lastDayLastMonth <= $today) {
			$lastDay = date('t');
			if ($today != $lastDay) {
				return true;
			}
		}
		return false;
	}

}
