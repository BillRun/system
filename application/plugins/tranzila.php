<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2025 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Plugin to trigger Tranzila operations by API
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.16
 */
class tranzilaPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'tranzila';

	/**
	 * method to response to plugin api
	 * 
	 * @param mixed $ret
	 * @param Yaf_response $response
	 * 
	 * @return bool
	 */
	protected function apiResponse($ret, $response) {
		$body = json_encode($ret);
		Billrun_Factory::log()->debug('api response with: ' . $body);
		$response->setBody($body);
		$response->setHeader('Content-Type', 'application/json');
		return true;
	}

	/**
	 * method to make j5 on new card token
	 * 
	 * @param type $params
	 * @param type $request required: aid, new_token, new_auth, new_expiration
	 * @param type $response
	 * 
	 * @return bool
	 */
	public function apiReplacetoken($params, $request, $response) {
		if ($params['plugin'] != $this->getName()) {
			return true;
		}
		$aid = $request->get('aid');
		$new_token = $request->get('new_token');
//		$new_auth = $request->get('new_auth');
		$new_expiration = $request->get('new_expiration');

		if (empty($aid) || empty($new_token) || /* empty($new_auth) || */ empty($new_expiration)) {
			$errorMsg = "Tranzila replace plugin: missing input";
			$ret = array(
				'status' => 0,
				'description' => $errorMsg,
				'request' => $request->getRequest(),
			);
			$this->apiResponse($ret, $response);
			return true;
		}
		$tz = Billrun_PaymentGateway::getInstance('Tranzila');
		
		$expire_year = substr($new_expiration, -2);
		$expire_month = substr($new_expiration, 0, 2);
		$cc_details = array(
			'aid' => $aid,
			'card_token' => $new_token,
//			'auth_number' => $new_auth,
			'expire_year' => $expire_year,
			'expire_month' => $expire_month,
		);
		$tzResponse = $tz->tokenTransaction($cc_details, []);

		if ($tzResponse != '000') {
			$errorMsg = 'Error on Tranzila retokenize';
			$ret = array(
				'status' => 0,
				'description' => $errorMsg
			);
		} else {
			$ret = array(
				'status' => 1,
				'auth' => $tzResponse,
			);
		}

		$this->apiResponse($ret, $response);
		return true;
	}

	/**
	 * api to cancel transaction that triggered today (before sending to SHVA)
	 * 
	 * @param array $params
	 * @param array $request required: billrun_txid; optional: allow_cancel (default: false); terminal (default: empty)
	 * @param array $response
	 * @return void
	 */
	public function apiCanceltx($params, $request, $response) {
		if ($params['plugin'] != $this->getName()) {
			return true;
		}
		$txid = $request->get('billrun_txid');
		if (empty($txid)) {
			$this->apiResponse(array('status' => 0, 'desc' => 'ERROR! no txid'), $response);
			return;
		}

		$query = array(
			'type' => 'rec',
			'txid' => $txid,
		);

		if (empty($request->get('allow_cancel'))) {
			$query['cancel'] = array('$exists' => 0);
			$query['cancelled'] = array('$exists' => 0);
		}


		$rec = Billrun_Factory::db()->billsCollection()->query($query)->cursor()->current();
		if (empty($rec) || $rec->isEmpty()) {
			$this->apiResponse(array('status' => 0, 'desc' => 'rec did not found'), $response);
			return;
		}

		$terminal = $request->get('terminal');
		if (!empty($terminal)) {
			$rec->set('gateway_details.terminal_number', $terminal);
		}

		$pg = Billrun_Factory::paymentGateway('Tranzila');

		$tzResponse = $pg->cancelTransaction($rec);

		if ($tzResponse !== '000') {
			$this->apiResponse(array('status' => 0, 'desc' => 'cancel transaction failed with code: ' . ($tzResponse ?? '-')), $response);
			return;
		}

		Billrun_Factory::log('[Tranzila PLUGIN] Canceltx ret details: ' . print_R($tzResponse, 1));

		$this->apiResponse($tzResponse, $response);
		return true;
	}

	/**
	 * api to refund historic transaction with transaction relationship
	 * 
	 * @param array $params
	 * @param array $request required: billrun_txid; optional: allow_cancel (default: false); terminal (default: empty); amount (default: empty)
	 * @param array $response
	 * @return void
	 */
	public function apiRefundtx($params, $request, $response) {
		if ($params['plugin'] != $this->getName()) {
			return true;
		}
		$txid = $request->get('billrun_txid');
		if (empty($txid)) {
			$this->apiResponse(array('status' => 0, 'desc' => 'ERROR! no txid'), $response);
			return;
		}

		$query = array(
			'type' => 'rec',
			'txid' => $txid,
		);

		if (empty($request->get('allow_cancel'))) {
			$query['cancel'] = array('$exists' => 0);
			$query['cancelled'] = array('$exists' => 0);
		}

		$rec = Billrun_Factory::db()->billsCollection()->query($query)->cursor()->current();
		if (empty($rec) || $rec->isEmpty()) {
			$this->apiResponse(array('status' => 0, 'desc' => 'rec did not found'), $response);
			return;
		}

		$terminal = $request->get('terminal');
		if (!empty($terminal)) {
			$rec->set('gateway_details.terminal_number', $terminal);
		}

		$pg = Billrun_Factory::paymentGateway('Tranzila');

		$amount = $request->get('amount');
		if (!empty($amount) && is_numeric($amount)) {
			$tzResponse = $pg->refundTransaction($rec, $amount);
		} else {
			$tzResponse = $pg->refundTransaction($rec);
		}

		if ($tzResponse !== '000') {
			$this->apiResponse(array('status' => 0, 'desc' => 'cancel transaction failed with code: ' . ($tzResponse ?? '-')), $response);
			return;
		}

		Billrun_Factory::log('[Tranzila PLUGIN] Canceltx ret details: ' . print_R($tzResponse, 1));

		$this->apiResponse($$tzResponse, $response);
		return true;
	}

	/**
	 * api to credit historic transaction without relationship
	 * 
	 * @param array $params
	 * @param array $request required: billrun_txid; optional: allow_cancel (default: false); terminal (default: empty); amount (default: empty)
	 * @param array $response
	 * @return void
	 */
	public function apiCredittx($params, $request, $response) {
		if ($params['plugin'] != $this->getName()) {
			return true;
		}
		$txid = $request->get('billrun_txid');
		if (empty($txid)) {
			$this->apiResponse(array('status' => 0, 'desc' => 'ERROR! no txid'), $response);
			return;
		}

		$query = array(
			'type' => 'rec',
			'txid' => $txid,
		);

		if (empty($request->get('allow_cancel'))) {
			$query['cancel'] = array('$exists' => 0);
			$query['cancelled'] = array('$exists' => 0);
		}

		$rec = Billrun_Factory::db()->billsCollection()->query($query)->cursor()->current();
		if (empty($rec) || $rec->isEmpty()) {
			$this->apiResponse(array('status' => 0, 'desc' => 'rec did not found'), $response);
			return;
		}

		$terminal = $request->get('terminal');
		if (!empty($terminal)) {
			$rec->set('gateway_details.terminal_number', $terminal);
		}

		$pg = Billrun_Factory::paymentGateway('Tranzila');

		$gateway_details = $rec['gateway_details'];
		$gateway_details['amount'] = $gateway_details['amount'] * (-1);

		$ret = $pg->makeOnlineTransaction($gateway_details, $rec);

		if (!isset($ret['status']) || (string) $ret['status'] !== '000') {
			$this->apiResponse(array('status' => 0, 'desc' => 'credit transaction failed with code: ' . ($ret['status'] ?? '-')), $response);
			return;
		}

		Billrun_Factory::log('[Tranzila PLUGIN] Credittx ret details: ' . print_R($ret, 1));

		$this->apiResponse($ret, $response);
		return true;
	}

	/**
	 * api to query historic transaction
	 * 
	 * @param array $params
	 * @param array $request required: billrun_txid; optional: allow_cancel (default: false); terminal (default: empty)
	 * @param array $response
	 * @return void
	 */
	public function apiQuerytx($params, $request, $response) {
		if ($params['plugin'] != $this->getName()) {
			return true;
		}
		$txid = $request->get('billrun_txid');
		if (empty($txid)) {
			$this->apiResponse(array('status' => 0, 'desc' => 'ERROR! no txid'), $response);
			return;
		}

		$query = array(
			'type' => 'rec',
			'txid' => $txid,
		);

		if (empty($request->get('allow_cancel'))) {
			$query['cancel'] = array('$exists' => 0);
			$query['cancelled'] = array('$exists' => 0);
		}


		$rec = Billrun_Factory::db()->billsCollection()->query($query)->cursor()->current();
		if (empty($rec) || $rec->isEmpty()) {
			$this->apiResponse(array('status' => 0, 'desc' => 'rec did not found'), $response);
			return;
		}

		$terminal = $request->get('terminal');

		$pg = Billrun_Factory::paymentGateway('Tranzila');

		$ret = $pg->queryTransaction($rec, ['terminal' => $terminal, 'returnRawData' => true]);

		Billrun_Factory::log('[Tranzila PLUGIN] Querytx ret details: ' . print_R($ret, 1));

		$this->apiResponse($ret, $response);
		return true;
	}
}
