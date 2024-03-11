<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2024 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Collect.php';

/**
 * Reject action class
 *
 * @package  Action
 * @since    0.5.13
 */
class RejectAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	public function execute() {
		$request = $this->getRequest();
		Billrun_Factory::log()->log('Reject API call with params: ' . print_r($request, 1), Zend_Log::INFO);
		try {
        	$jsonRejections = $request->get('rejections');
			if (($rejectionsArr = json_decode($jsonRejections, TRUE)) && (json_last_error() == JSON_ERROR_NONE) && is_array($rejectionsArr)) {
				Billrun_Utils_Mongo::convertQueryMongoDates($rejectionsArr);
				$errorRejections = array();
				foreach ($rejectionsArr as $rawRejection) {
					if (!empty($rawRejection['id'])) {
						if (isset($rawRejection['rejection']['code'])) {
							if (isset($rejections[$rawRejection['id']])) {
								return $this->setError('Duplicate id ' . $rawRejection['id'], $request->getPost());
							}
							$rejections[$rawRejection['id']] = [ 'status' => $rawRejection['rejection']['code']];
							if (isset($rawRejection['urt'])) {
								$rejectUrt =  $rawRejection['urt'];
								if ($rejectUrt instanceof MongoDate) {
									$rejections[$rawRejection['id']]['urt'] = $rejectUrt;
								} else {
									Billrun_Factory::log("Got invalid urt for rejection id " . $rawRejection['id'] . ", continuing without it.");
								}
							}
							$payment = Billrun_Bill_Payment::getInstanceByid($rawRejection['id']);
							if ($payment) {
								if (!$payment->isRejected()) {
										$payments[] = $payment;
								} else {
									return $this->setError('Payment ' . $payment->getId() . ' already rejected', $request->getPost());
								}
							} else {
								return $this->setError('Unknown id', $request->getPost());
							}
						} else {
							return $this->setError('Missing rejection params', $request->getPost());
						}
					} else {
						return $this->setError('Missing id', $request->getPost());
					}
				}
				$newPayments = array();
				foreach ($payments as $payment) {
					$id = $payment->getId();
					$newPayments[] = $payment->getRejectionPayment($rejections[$id]);
				}
				if ($newPayments) {
					$res = Billrun_Bill_Payment::savePayments($newPayments);
					if (!isset($res['ok']) || !$res['ok']) {
						return $this->setError('An error occurred while rejecting the payment(s)', $request->getPost());
					}
				}
				foreach ($payments as $payment) {
					$payment->markRejected();
					$payment->updatePastRejectionsOnProcessingFiles();
				}
				$this->getController()->setOutput(array(array(
						'status' => 1,
						'desc' => 'success',
						'input' => $request->getPost(),
						'details' => array(
							'rejections_received' => count($payments),
							'rejections_saved' => $res['nInserted'],
						),
				)));
				if ($newPayments) {
					foreach ($newPayments as $newPayment) {
						$entity = array(
							'aid' => $newPayment->getAccountNo(),
							'amount' => $newPayment->getAmount(),
							'date' => date(Billrun_Base::base_datetimeformat, $newPayment->getTime()->sec),
						);
						if ($newPayment instanceof Billrun_Bill_Payment_Cheque || $newPayment instanceof Billrun_Bill_Payment_Debit) {
							$entity['code'] = $newPayment->getRejectionCode();
						}
					}
				}
			} else {
				return $this->setError('No rejections found', $request->getPost());
			}
		} catch (Exception $ex) {
			return $this->setError($ex->getMessage(), $request->getPost());
		}
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}
}
