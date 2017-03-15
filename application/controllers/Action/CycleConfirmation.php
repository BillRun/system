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
		$billrunKey = $request->get('stamp');
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			return $this->setError("stamp is in incorrect format or missing ", $request);
		}
		
		if ($action == 'confirm') {
			$success = $this->processConfirmCycle($billrunKey, $invoices);
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

		return true;
	}

	protected function processConfirmCycle($billrunKey, $invoicesId) {
		$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --generate --type billrunToBill --stamp ' . $billrunKey . ' invoices=' . $invoicesId;
		return Billrun_Util::forkProcessCli($cmd);
	}
	
	

}
