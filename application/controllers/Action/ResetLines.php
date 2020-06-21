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
		if (empty($request['aid'])) {
			return $this->setError('Please supply at least one aid', $request);
		}
		
		// remove the aids from current balance cache - on next current balance it will be recalculated and avoid to take it from cache
		if (isset($request['aid'])) {
			$this->cleanAccountCache($request['aid']);
		}
		$invoicing_day = !empty($request['invoicing_day']) ? $request['invoicing_day'] : null;
		if (Billrun_Factory::config()->isMultiDayCycle() && empty($invoicing_day)) {
			Billrun_Factory::log("Multi day cycle system's mode on, but no invoicing day was sent. Default one was taken.", Zend_Log::ALERT);
			$request['invoicing_day'] = Billrun_Factory::config()->getConfigChargingDay();
		}
		if (!is_null($invoicing_day)) {
			$billrun_key = empty($request['billrun_key'])  ? Billrun_Billingcycle::getBillrunKeyByTimestamp(time(), $invoicing_day) : $request['billrun_key'];
		} else {
			$billrun_key = empty($request['billrun_key'])  ? Billrun_Billingcycle::getBillrunKeyByTimestamp() : $request['billrun_key'];
		}

		if(!Billrun_Util::isBillrunKey($billrun_key)) {
			return $this->setError('Illegal billrun key', $request);
		}
		if($billrun_key <= Billrun_Billingcycle::getLastClosedBillingCycle($invoicing_day)) {
			if (!is_null($invoicing_day)) {
				return $this->setError("Billrun {$billrun_key} , with invoicing day {$invoicing_day},  already closed", $request);
			} else {
				return $this->setError("Billrun {$billrun_key} already closed", $request);
			}
		}
		
		// Warning: will convert half numeric strings / floats to integers
		$aids = $this->getRequestAids($request);

		if (!$aids) {
			return $this->setError('Illegal aid', $request);
		}
		if (!empty($request['query'])) {
			$conditions = json_decode($request['query'], true);
			if (json_last_error()) {
				return $this->setError("Illegal query", $request);
			}
		}

		try {
			$rebalance_queue = Billrun_Factory::db()->rebalance_queueCollection();
			foreach ($aids as $aid) {
				$rebalanceLine = array(
					'aid' => $aid,
					'billrun_key' => $billrun_key,
					'conditions' => !empty($conditions) ? $conditions : array(),
					'conditions_hash' => md5(serialize($conditions)),
					'creation_date' => new MongoDate()
				);
				$query = array(
					'aid' => $aid,
					'billrun_key' => $billrun_key,
				);
				if (!is_null($invoicing_day)) {
					$query['foreign']['account']['invoicing_day'] = $invoicing_day;
				}
				$options = array('upsert' => true);
				$rebalance_queue->update($query, array('$set' => $rebalanceLine), $options);
			}
		} catch (Exception $exc) {
			Billrun_Util::logFailedResetLines($aids, $billrun_key);
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
	 * Gets aids from the request.
	 * If aid (list or string) received - returns it as array of integers.
	 * 
	 * @param type $request
	 * @return array
	 */
	protected function getRequestAids($request) {
		return array_unique(array_diff(Billrun_Util::verify_array($request['aid'], 'int'), array(0)));
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
