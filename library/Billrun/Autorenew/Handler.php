<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
		$queryDate = $this->getAutoRenewServicesQuery();
		$collection = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
		$autoRenewCursor = $collection->query($queryDate)->cursor();
		Billrun_Factory::log("Autorenew handler load " . $autoRenewCursor->count() . " records", Zend_Log::INFO);
		$manager = new Billrun_Autorenew_Manager();

		// Go through the records.
		foreach ($autoRenewCursor as $autoRenewRecord) {
			try {				
				$record = $manager->getAction($autoRenewRecord);
				if (!$record) {
					Billrun_Factory::log("Auto renew services failed to create record handler", Zend_Log::ALERT);
					continue;
				}
				$record->update();
				Billrun_Factory::dispatcher()->trigger('afterSubscriberBalanceAutoRenewUpdate', array($autoRenewRecord));
			} catch (Exception $ex) {
				Billrun_Factory::log("Error on autorenew handler. " . $ex->getCode() . ": " . $ex->getMessage(), Zend_Log::ERR);
			}
		}
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
