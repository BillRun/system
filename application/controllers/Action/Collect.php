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
class CollectAction extends ApiAction {

	public function execute() {
		Billrun_Factory::log()->log("Execute collect api call", Zend_Log::INFO);
		$request = $this->getRequest();

		try {
			$jsonAids = $request->getPost('aids', '[]');
			$aids = json_decode($jsonAids, TRUE);
			if (!is_array($aids) || json_last_error()) {
				return $this->setError('Illegal account ids', $request->getPost());
			}
			$result = static::collect($aids);	
			if(php_sapi_name() != "cli") {
				$this->getController()->setOutput(array(array(
					'status' => 1,
					'desc' => 'success',
					'details' => $result,
					'input' => $request->getRequest(),
				)));
			} else {
				foreach ($result as $colection_state => $aids) {
					$this->getController()->addOutput("aids " . $colection_state . " : " . implode(", ", $aids));
				}
			}
		} catch (Exception $e) {
			$this->setError($e->getMessage(), $request->getRequest());
		}
	}

	public static function collect($aids = array()) {
		$account = Billrun_Factory::account();
		$markedAsInCollection = $account->getInCollection($aids);
		$reallyInCollection = Billrun_Bill::getContractorsInCollection($aids);
		$updateCollectionStateChanged = array('in_collection' => array_diff_key($reallyInCollection, $markedAsInCollection), 'out_of_collection' => array_diff_key($markedAsInCollection, $reallyInCollection));
		$result = $account->updateCrmInCollection($updateCollectionStateChanged);
//		$subscriber->markCollectionStepsCompleted($aids);
		return $result;
	}

}
