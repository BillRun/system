<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a payment gateway
 *
 * @since    5.2
 */
class Billrun_PaymentGateway_PayPal_ExpressCheckout extends Billrun_PaymentGateway {

	protected $omnipayName = 'PayPal_Express';
	protected $conf;
	protected $EndpointUrl = "https://api-3t.sandbox.paypal.com/nvp";
	protected $billrunName = "PayPal_ExpressCheckout";

	public function updateSessionTransactionId() {
		$url_array = parse_url($this->redirectUrl);
		$str_response = array();
		parse_str($url_array['query'], $str_response);
		$this->transactionId = $str_response['token'];
	}

	protected function buildPostArray($aid, $returnUrl, $okPage) {
		$this->conf['user'] = "shani.dalal_api1.billrun.com";
		$this->conf['password'] = "RRM2W92HC9VTPV3Y";
		$this->conf['signature'] = "AiPC9BjkCyDFQXbSkoZcgqH3hpacA3CKMEmo7jRUKaB3pfQ8x5mChgoR";
		$this->conf['return_url'] = $okPage;
		$this->conf['redirect_url'] = "https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";

		return $post_array = array(
			'USER' => $this->conf['user'],
			'PWD' => $this->conf['password'],
			'SIGNATURE' => $this->conf['signature'],
			'METHOD' => "SetExpressCheckout",
			'VERSION' => "95",
			'AMT' => 0,
			'returnUrl' => $this->conf['return_url'],
			'cancelUrl' => $this->conf['return_url'],
			'L_BILLINGTYPE0' => "MerchantInitiatedBilling",
		);
	}

	protected function updateRedirectUrl($result) {
		parse_str($result, $resultArray);
		if ($resultArray['ACK'] != "Success") {
			throw new Exception($resultArray['L_LONGMESSAGE0']);
		}

		$this->redirectUrl = $this->conf['redirect_url'] . $resultArray['TOKEN'];
	}

	protected function buildTransactionPost($txId) {
		$this->conf['user'] = "shani.dalal_api1.billrun.com";
		$this->conf['password'] = "RRM2W92HC9VTPV3Y";
		$this->conf['signature'] = "AiPC9BjkCyDFQXbSkoZcgqH3hpacA3CKMEmo7jRUKaB3pfQ8x5mChgoR";
		//$this->conf['return_url'] = $returnUrl;	
		$this->conf['redirect_url'] = "https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";

		return $post_array = array(
			'USER' => $this->conf['user'],
			'PWD' => $this->conf['password'],
			'SIGNATURE' => $this->conf['signature'],
			'METHOD' => "CreateBillingAgreement",
			'VERSION' => "95",
			'TOKEN' => $txId,
		);
	}

	public function getTransactionIdName() {
		return "token";
	}

	protected function getResponseDetails($result) {
		$resultArray = array();
		parse_str($result, $resultArray);
		if (!isset($resultArray['ACK']) || $resultArray['ACK'] != "Success") {
			throw new Exception($resultArray['L_LONGMESSAGE0']);
		}
		$this->saveDetails['billing_agreement_id'] = $resultArray['BILLINGAGREEMENTID'];
		// need to pass return url of the customer
	}

	protected function buildSetQuery() {
		return array(
			'payment_gateway' => array(
				'name' => $this->billrunName,
				'card_token' => (string) $this->saveDetails['billing_agreement_id'],
				'transaction_exhausted' => true
			)
		);
	}

}
