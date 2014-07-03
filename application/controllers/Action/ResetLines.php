<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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

		$rebalance_queue = Billrun_Factory::db()->rebalance_queueCollection();
		$limit = Billrun_Config::getInstance()->getConfigValue('resetlines.limit', 10);
		$offset = Billrun_Config::getInstance()->getConfigValue('resetlines.offset', '1 hour');
		$query = array(
			'creation_date' => array(
				'$lt' => new MongoDate(strtotime($offset . ' ago')),
			),
		);
		$sort = array(
			'creation_date' => 1,
		);
		$results = $rebalance_queue->find($query)->sort($sort)->limit($limit);

		$billruns = array();
		$all_sids = array();
		foreach ($results as $result) {
			$billruns[$result['billrun_key']][] = $result['sid'];
			$all_sids[] = $result['sid'];
		}

		foreach ($billruns as $billrun_key => $sids) {
			$model = new ResetLinesModel($sids, $billrun_key);
			try {
				$model->reset();
				$rebalance_queue->remove(array('sid' => array('$in' => $sids)));
			} catch (Exception $exc) {
				return $this->setError($exc->getTraceAsString(), array('sids' => $sids, 'billrun_key' => $billrun_key));
			}
		}
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'sids' => $all_sids,
		)));
		return true;
	}

}