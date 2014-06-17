<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Credit action class
 *
 * @package  Action
 * @since    0.5
 */
class RemoveAction extends ApiAction {

	/**
	 * method to execute the refund
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute remove", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		Billrun_Factory::log()->log("Input: " . print_R($request, 1), Zend_Log::INFO);

		$stamps = array();
		foreach ($request['stamps'] as $line_stamp) {
			$clear_stamp = Billrun_Util::filter_var($line_stamp, FILTER_SANITIZE_STRING, FILTER_FLAG_ALLOW_HEX);
			if (!empty($clear_stamp)) {
				$stamps[] = $clear_stamp;
			}
		}

		if (empty($stamps)) {
			Billrun_Factory::log()->log("remove action failed; no correct stamps", Zend_Log::INFO);
			$this->getController()->setOutput(array(array(
					'status' => false,
					'desc' => 'failed - invalid stamps input',
					'input' => $request,
			)));
			return true;
		}
		
		$model = new LinesModel();
		$query = array(
			'source' => 'api',
			'stamp' => array('$in' => $stamps),
			'$or' => array(
				array('billrun' => Billrun_Billrun::getActiveBillrun()),
				array('billrun' => array('$exists' => false)),
			)
		);
		$ret = $model->remove($query);
		
		if (!isset($ret['ok']) || !$ret['ok'] || !isset($ret['n'])) {
			Billrun_Factory::log()->log("remove action failed pr miscomplete", Zend_Log::INFO);
			$this->getController()->setOutput(array(array(
					'status' => false,
					'desc' => 'remove failed',
					'input' => $request,
			)));
			return true;
		}
		
		Billrun_Factory::log()->log("remove success", Zend_Log::INFO);
		$this->getController()->setOutput(array(array(
				'status' => $ret['n'],
				'desc' => 'success',
				'input' => $request,
		)));
		
	}

}
