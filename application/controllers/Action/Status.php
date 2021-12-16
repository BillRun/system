<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * OkPage action class
 *
 * @package  Action
 * @since    5.0
 * 
 */

class StatusAction extends ApiAction {

	protected $card_token;
	protected $card_expiration;
	protected $subscribers;
	protected $aid;
	protected $personal_id;

	protected function getStatus($tx, $aid) {
		$cgColl = Billrun_Factory::db()->creditproxyCollection();
		
		// Get is started
		$query = array("tx" => (string) $tx, "aid" => $aid);
		$cgRow = $cgColl->query($query)->cursor()->current();
		if($cgRow->isEmpty()) {
			// Received message for completed charge, 
			// but no indication for charge start
			return "Did not start.";
		}
		
		if($cgRow['done'] === true) {
			return "Done.";
		} 
		
		return "Started.";
	}
	
	public function execute() {
		$request = $this->getRequest();

		$output = array(
			'status' => 1,
		);
				
		$tx = $request->get("txId");
		$aid = $request->get("aid");
		
		// TODO: Validate signature?
		if (is_null($tx) || is_null($aid)) {
			return $this->setError("Operation Failed. Try Again...", $request);
		}
		
		$message = $this->getStatus($tx, $aid);
		$output['desc'] = $message;
		
		$this->getController()->setOutput(array($output));
	}
}
