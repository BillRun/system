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
			$settings = $this->confirmCycle($invoicesId, $options);
		} else if ($action == 'getcycles') {
			$settings = $this->getCycles();
		}
		
		$output = array (
			'status' => $settings ? 1 : 0,
			'desc' => $settings ? 'success' : 'error',
			'details' => empty($settings) ? array() : $settings,
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
	
	protected function getCycles() {
        $billrunKeys = array();
        $currentStamp = Billrun_Billingcycle::getBillrunKeyByTimestamp();
        array_push($billrunKeys, $currentStamp);
        $rangeOfCycles = Billrun_Factory::config()->getConfigValue('cyclemanagement.previous_cycles');
        while ($rangeOfCycles) {
            array_push($billrunKeys, Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime("$rangeOfCycles months ago")));
            $rangeOfCycles--;
        }
        return $billrunKeys;
    }
	
}
