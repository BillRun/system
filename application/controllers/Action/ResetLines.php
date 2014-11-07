<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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

	public function execute() {
		Billrun_Factory::log()->log("Execute reset", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		if (empty($request['sid'])) {
			return $this->setError('Please supply at least one sid', $request);
		}
		
		// remove the aids from current balance cache - on next current balance it will be recalculated and avoid to take it from cache
		if (isset($request['aid'])) {
			$aids = array_unique(array_diff(Billrun_Util::verify_array($request['aid'], 'int'), array(0)));
			$stamp = Billrun_Util::getBillrunKey(time());
			foreach ($aids as $aid) {
				Billrun_Factory::cache()->remove(Billrun_Util::generateArrayStamp(array_values(array('aid' => $aid, 'subscribers' => null, 'stamp' => $stamp))), 'balance');
				Billrun_Factory::cache()->remove(Billrun_Util::generateArrayStamp(array_values(array('aid' => $aid, 'subscribers' => "", 'stamp' => $stamp))), 'balance');
				Billrun_Factory::cache()->remove(Billrun_Util::generateArrayStamp(array_values(array('aid' => $aid, 'subscribers' => 0, 'stamp' => $stamp))), 'balance');
			}
		}

		$billrun_key = Billrun_Util::getBillrunKey(time());

		// Warning: will convert half numeric strings / floats to integers
		$sids = array_unique(array_diff(Billrun_Util::verify_array($request['sid'], 'int'), array(0)));

		if ($sids) {
			try {
				$rebalance_queue = Billrun_Factory::db()->rebalance_queueCollection();
				foreach ($sids as $sid) {
					$rebalance_queue->insert(array('sid' => $sid, 'billrun_key' => $billrun_key, 'creation_date' => new MongoDate()));
				}
			} catch (Exception $exc) {
				Billrun_Util::logFailedResetLines($sids, $billrun_key);
				return FALSE;
			}
		} else {
			return $this->setError('Illegal sid', $request);
		}
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request,
		)));
		return TRUE;
	}

}