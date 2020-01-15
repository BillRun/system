<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Pay action class of version 3
 *
 * @package  Action
 * @since    0.5
 */
class V3_billAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;

	public function execute() {
		$request = $this->getRequest();
		try {
			switch ($request->get('action')) {
				case 'query_bills_invoices' :
					$response = $this->queryBillsInvoices($request->get('query'));
					break;
				case 'get_over_due' :
					$response = $this->getOverDueBalances($request);
					break;
				case 'get_balances' :
					$response = $this->getBalances($request);
					break;
				case 'collection_debt' :
					$response = $this->getCollectionDebt($request);
					break;
				case 'search_invoice' :
				default :
					$response = $this->getBalanceFor($request);
			}

			if ($response !== FALSE) {
				$this->getController()->setOutput(array(array(
						'status' => 1,
						'desc' => 'success',
						'input' => $request->getPost(),
						'details' => $response,
				)));
			}
		} catch (Exception $ex) {
			$this->setError($ex->getMessage(), $request->getPost());
			return;
		}
	}

	protected function getBalanceFor($request) {
		$aid = $request->get('aid');
		$id = $request->get('id');
		$unpaidInvoices = filter_var($request->get('unpaid_invoices', FALSE), FILTER_VALIDATE_BOOLEAN);

		if (!is_numeric($aid) || $aid != (int) $aid) {
			if (!is_numeric($id) || $id != (int) $id) {
				$this->setError('Illegal invoice id', $request->getPost());
				return FALSE;
			}
			$id = intval($id);
			$bill = Billrun_Factory::db()->billsCollection()->query('invoice_id', $id)->cursor()->limit(1)->current();
			if (!$bill->isEmpty()) {
				$aid = $bill['aid'];
			} else {
				$this->setError('Bill not found', $request->getPost());
				return FALSE;
			}
		} else {
			$aid = intval($aid);
			$bill = new Mongodloid_Entity;
		}
		$ret = array(
			'balance' => Billrun_Bill::getTotalDueForAccount($aid),
			'bill' => $bill->getRawData(),
		);
		if ($unpaidInvoices) {
			$pastOnly = filter_var($request->get('past_only', FALSE), FILTER_VALIDATE_BOOLEAN);
			$query = array('aid' => $aid);
			if ($pastOnly) {
				$query['charge.not_before'] = array('$lt' => new MongoDate());
			}
			$ret['unpaid_invoices'] = Billrun_Bill_Invoice::getUnpaidInvoices($query);
		}
		return $ret;
	}

	/**
	 * 
	 * @param Yaf_Request_Abstract $request
	 * @return array
	 * @todo make it more efficient (with 1 query)
	 */
	protected function getBalances($request) {
		$aids = explode(',', $request->get('aids'));

		if (empty($aids)) {
			$this->setError('Illegal aids arguments', $request->getPost());
			return FALSE;
		}
		$balances = array();
		foreach ($aids as $aid) {
			$balances[$aid] = Billrun_Bill::getTotalDueForAccount(intval($aid));
		}

		return $balances;
	}

	protected function getOverDueBalances($request) {
		//TODO
		return FALSE;
	}

	protected function queryBillsInvoices($query) {
		if (empty($query)) {
			return FALSE;
		}

		Billrun_Factory::log('queryBillsInvoices query  : ' . print_r($query, 1));
		return Billrun_Bill_Invoice::getInvoices(json_decode($query, JSON_OBJECT_AS_ARRAY));
	}

	protected function getCollectionDebt($request) {
		$jsonAids = $request->getPost('aids', '[]');
		$aids = json_decode($jsonAids, TRUE);
		if (!is_array($aids) || json_last_error()) {
			$this->setError('Illegal account ids', $request->getPost());
			return FALSE;
		}
		if (empty($aids)) {
			$this->setError('Must supply at least one aid', $request->getPost());
			return FALSE;
		}
		return Billrun_Bill::getContractorsInCollection($aids);
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
