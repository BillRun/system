<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Pay action class
 *
 * @package  Action
 * @since    5.0
 */
class PaymentsAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	public function execute() {
		$this->allowed();
		$request = $this->getRequest();
		try {
			switch ($request->get('action')) {
				case 'query' :
						$payments = $this->queryPayments($request->get('query'));	
					break;
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

	/**
	 * Find invoices by  the amount and date
	 * @param type $request the  http rerquest containing  the amount and the date.
	 * @return type
	 */
	protected function searchPayments($request) {
		$aid = filter_var($request->get('aid'), FILTER_VALIDATE_INT);
		$aid = $aid === FALSE ? NULL : $aid;
		$dirs = array($request->get('dir'));
		$methods = $request->get('methods');
		if ((!$methods = json_decode($request->get('methods'), TRUE)) || (json_last_error() != JSON_ERROR_NONE) || !is_array($methods)) {
			$methods = array();
		}
		$paymentDate = $request->get('payment_date');
		$to = $from = $paymentDate;
		if ($paymentDate && date('Y-m-d', strtotime($paymentDate)) != $paymentDate) {
			$this->setError('Date should be in \'yyyy-mm-dd\' format, ' . $paymentDate . ' given');
			return FALSE;
		}
		$amount = filter_var($request->get('amount'), FILTER_VALIDATE_FLOAT);
		$amount = $amount === FALSE ? NULL : $amount;
		return Billrun_Bill_Payment::getPayments($aid, $dirs, $methods, $to, $from, $amount);
	}
	
	protected function queryPayments($query) {
		if(empty($query) ) { return FALSE;	}
		if(is_string($query)) {
			$query = json_decode($query,true);
		}
		
		if (is_array($query['urt'])) {
			$query['urt'] = Billrun_Util::intToMongodloidDate($query['urt']);
		}
		
		return Billrun_Bill_Payment::queryPayments($query);
		
		
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
