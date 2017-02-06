<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Recreate invoices action class
 *
 * @package  Action
 * @since    5.3
 */
class CycleConfirmationAction extends ApiAction {

	public function execute() {
		$request = $this->getRequest();
		$action = $request->get('action');
		$invoices = $request->get('invoices');
		$invoicesId = json_decode($invoices);
		$billrunKey = $request->get('stamp');
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			return $this->setError("stamp is in incorrect format or missing ", $request);
		}
		
		if ($action == 'confirm') {
			$options = array (
				'type' => "BillrunToBill",
				'stamp' => $billrunKey,
			);
			$success = $this->confirmCycle($invoicesId, $options);
		}
		
		$output = array (
			'status' => $success ? 1 : 0,
			'desc' => $success ? 'success' : 'error',
			'details' => array(),
		);
		$this->getController()->setOutput(array($output));
	}

	protected function confirmCycle($invoicesId, $options) {	
		if (!empty($invoicesId)) {
			$options['invoices'] = $invoicesId;	
		}
		$generator = Billrun_Generator::getInstance($options);
		if (!$generator) {
			throw new Exception("Failure to create bills, try again");
		}
		$generator->load();
		$generator->generate();
		return true;
	}
	
}
