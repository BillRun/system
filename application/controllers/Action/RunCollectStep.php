<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Collect action class
 *
 * @package  Action
 * 
 * @since    2.6
 */
class  Run_collect_stepAction extends ApiAction {

	public function execute() {
		Billrun_Factory::log()->log("Execute collect api call", Zend_Log::INFO);
		$request = $this->getRequest();

		try {
			$jsonAids = $request->getPost('aids', '[]');
			$aids = json_decode($jsonAids, TRUE);
			if (!is_array($aids) || json_last_error()) {
				return $this->setError('Illegal account ids', $request->getPost());
			}
			$result = static::runCollectStep($aids);
			if(php_sapi_name() != "cli") {
			$this->getController()->setOutput(array(array(
					'status' => 1,
					'desc' => 'success',
					'details' => $result,
					'input' => $request->getRequest(),
			)));
			} else {
				foreach ($result as $status => $aids) {
					foreach ($aids as $aid => $steps) {
						$this->getController()->addOutput("Collection step run status '" . $status . "', for AID " . $aid . " run steps : " . implode(", ", $steps));
					}
				}
			}
		} catch (Exception $e) {
			$this->setError($e->getMessage(), $request->getRequest());
		}
	}

	public static function runCollectStep($aids = array()) {
		$collectionSteps = Billrun_Factory::collectionSteps();
		$result = $collectionSteps->runCollectStep($aids);
		return $result;
	}

}
