<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Reset lines action class
 *
 * @package  Action
 * @since    0.5
 */
class ResetLinesAction extends ApiAction {

	use Billrun_Traits_Api_UserPermissions;

	
	public function execute() {
		$this->allowed();
		Billrun_Factory::log("Execute reset", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		if (empty($request['aid']) && empty($request['sid'])) {
			return $this->setError('Please supply at least one sid or aid', $request);
		}

		// remove the aids from current balance cache - on next current balance it will be recalculated and avoid to take it from cache
		if (isset($request['aid'])) {
			$this->cleanAccountCache($request['aid']);
		}

		$billrun_key = Billrun_Billingcycle::getBillrunKeyByTimestamp();

		// Warning: will convert half numeric strings / floats to integers
		$sids = $this->getRequestSids($request);

		if (!$sids) {
			return $this->setError('Illegal sid', $request);
		}

		try {
			$rebalance_queue = Billrun_Factory::db()->rebalance_queueCollection();
			foreach ($sids as $sid) {
				$rebalanceLine = array( 'sid' => $sid,
										'billrun_key' => $billrun_key,
										'creation_date' => new MongoDate());
				$rebalance_queue->insert($rebalanceLine);
			}
		} catch (Exception $exc) {
			Billrun_Util::logFailedResetLines($sids, $billrun_key);
			return FALSE;
		}

		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request,
		)));
		return TRUE;
	}
	
	/**
	 * Gets sids from the request.
	 * If sid (list or string) received - returns it as array of integers.
	 * If aid (list or string) received - gets all sids from the db
	 * 
	 * @param type $request
	 * @return array
	 */
	protected function getRequestSids($request) {
		if (isset($request['sid'])) {
			return array_unique(array_diff(Billrun_Util::verify_array($request['sid'], 'int'), array(0)));
		}
		
		$query = $this->getSidsByAidsQuery(Billrun_Util::verify_array($request['aid'], 'int'));
		return Billrun_Util::verify_array(Billrun_Factory::db()->subscribersCollection()->distinct('sid', $query), 'int');
	}
	
	/**
	 * Gets the query to get sids by aids list.
	 * gets all subscribers from past 3 months - for late lines.
	 * only active cycles will be handled because of the billrun key
	 * 
	 * @param array $aids
	 * @return query
	 */
	protected function getSidsByAidsQuery($aids) {
		$time = date(strtotime('-3 months'));
		return array(
			'to' => array(
				'$gt' => new MongoDate($time),
			),
			'from' => array(
				'$lte' => new MongoDate(),
			),
			'aid' => array(
				'$in' => $aids,
			),
		);
	}

	/**
	 * Clean cache from account.
	 * @param type $aid - Account ID
	 * @param type $cache - Cache to be cleaned
	 * @param type $billrunKey - Current billrun key.
	 * @param type $cachePrefix - Prefix name of cach record to remove.
	 */
	protected function cleanSingleAccountCache($aid, $cache, $billrunKey, $cachePrefix) {
		$cleanCacheKeys = array(
			Billrun_Util::generateArrayStamp(array_values(array('aid' => $aid, 'subscribers' => array(), 'stamp' => $billrunKey))),
			Billrun_Util::generateArrayStamp(array_values(array('aid' => $aid, 'subscribers' => null, 'stamp' => (int) $billrunKey))),
			Billrun_Util::generateArrayStamp(array_values(array('aid' => $aid, 'subscribers' => "", 'stamp' => (int) $billrunKey))),
			Billrun_Util::generateArrayStamp(array_values(array('aid' => $aid, 'subscribers' => 0, 'stamp' => (int) $billrunKey))),
		);
		foreach ($cleanCacheKeys as $cacheKey) {
			$cache->remove($cacheKey, $cachePrefix);
		}
	}

	/**
	 * method to clean account cache
	 * 
	 * @param int $aid
	 * 
	 * @return true on success, else false
	 */
	protected function cleanAccountCache($aid) {
		$cache = Billrun_Factory::cache();
		if (empty($cache)) {
			return false;
		}
		$aids = array_unique(array_diff(Billrun_Util::verify_array(explode(',', $aid), 'int'), array(0)));
		$billrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp();
		$cachePrefix = 'balance_'; // this is not the action name because it's clear the balance cache
		foreach ($aids as $aid) {
			$this->cleanSingleAccountCache($aid, $cache, $billrunKey, $cachePrefix);
		}
		return true;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
