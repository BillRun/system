<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

class PaymentHistoryAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;

	public function execute() {
		$this->allowed();
		$request = $this->getRequest();
		try {
			switch ($request->get('action')) {
				case 'search' :
				default :
					$payments = $this->searchPayments($request);
			}

			if ($payments !== FALSE) {
				$this->getController()->setOutput(array(array(
						'status' => 1,
						'desc' => 'success',
						'input' => $request->getPost(),
						'details' => array(
							'payments' => $payments,
						)
				)));
			}
		} catch (Exception $ex) {
			$this->setError($ex->getMessage(), $request->getPost());
			return;
		}
	}
	
	protected function searchPayments($request) {
		$aid = filter_var($request->get('aid'), FILTER_VALIDATE_INT);
		$aid = $aid === FALSE ? NULL : $aid;

		//$months_back = $request->get('months_back');
		$to = $request->get('to');
		$from = $request->get('from');
		return Billrun_Bill_Payment::getPayments($aid, array(), array(), date('Y/m/d',  strtotime($to)), date('Y/m/d',  strtotime($from)), null, true, true);
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}
}