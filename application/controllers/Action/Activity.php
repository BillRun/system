<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Activity action class
 *
 * @package  Action
 * 
 * @since    2.6
 */
class ActivityAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	public function execute() {
		$this->allowed();
		Billrun_Factory::log("Execute activity call", Zend_Log::INFO);
		$request = $this->getRequest();
		$sid = (int) $request->get('sid', 0);

		if (empty($sid)) {
			return $this->setError('Subscriber does not exist', $request);
		}

		$include_incoming = (int) $request->get('include_incoming', 0);
		$include_outgoing = (int) $request->get('include_outgoing', 0);
		$include_sms = (int) $request->get('include_sms', 0);

		$from_date = $request->get('from_date', time() - 30 * 3600);
		$to_date = $request->get('to_date', time());

		if (!is_numeric($from_date)) {
			$from_date = strtotime($from_date);
		}

		if (!is_numeric($to_date)) {
			$to_date = strtotime($to_date);
		}

		$model = new LinesModel();
		$results = $model->getActivity($sid, $from_date, $to_date, $include_outgoing, $include_incoming, $include_sms);

		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'details' => $results,
				'input' => $request,
		)));
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
