<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

require_once 'Google/Client.php';

class TokensAction extends Action_Base {

	public function execute() {
		//wget -qO /dev/null 'http://billrun/api/tokens?XDEBUG_SESSION_START=netbeans-xdebug&fork=1' --post-data 'sms_content=djfgbv%3A875a978e9f79d9c&ndc_sn=972547655380' > /dev/null
		$request = $this->getRequest();
		$smsContent = $request->get("sms_content");
		$ndcSn = $request->get("ndc_sn");

		$smsContentArr = explode(':', $smsContent);

		if (is_null($ndcSn) || !isset($smsContentArr[1])) {
			Billrun_Factory::log()->log('Google dcb association bad input. sms content: ' . $smsContent . '. ndc sn: ' . $ndcSn, Zend_Log::ALERT);
			return FALSE;
		}
		$GUT = $smsContentArr[1];
		$OUT = Billrun_Util::hash($GUT);

		$params['time'] = date(Billrun_Base::base_dateformat);
		$callingNumberCRMConfig = Billrun_Config::getInstance()->getConfigValue('customer.calculator.customer_identification_translation.caller.calling_number', array('toKey' => 'NDC_SN', 'clearRegex' => '/^0*\+{0,1}972/'));
		$params[$callingNumberCRMConfig['toKey']] = preg_replace($callingNumberCRMConfig['clearRegex'], '', $ndcSn);

		$subscriber = Billrun_Factory::subscriber();
		$subscriberFields = Billrun_Config::getInstance()->getConfigValue('googledcb.subscriberFields', array());
		$subscriberField = $subscriberFields['subscriberField'];
		$accountField = $subscriberFields['accountField'];
		$planField = $subscriberFields['planField'];
		$identities = $subscriber->getSubscribersByParams(array($params), $subscriber->getAvailableFields());

		if (isset($identities[0]) && $identities[0]->{$subscriberField}) { // to do: change to $subscriber->isValid() once it works
			$sid = $identities[0]->{$subscriberField};
			$aid = $identities[0]->{$accountField};
			$plan = $identities[0]->{$planField};

			if (is_numeric($identities[0]->{$subscriberField})) {
				$sid = intval($sid);
			}
			if (is_numeric($identities[0]->{$accountField})) {
				$aid = intval($aid);
			}
		} else {
			Billrun_Factory::log()->log('Google dcb association failed. Unknown ndc sn ' . $ndcSn, Zend_Log::ALERT);
			return FALSE;
		}
		// Insert to DB
		$model = new TokensModel();
		$model->storeData($GUT, $OUT, $sid, $aid, $plan, $ndcSn);

		// Send request to google
		$this->tokenConfig = Billrun_Factory::config()->getConfigValue('googledcb.association.tokens', array());
		$access_token = $this->getAccessToken();
		$url = $this->tokenConfig['host'] . $this->tokenConfig['post'];
		$data = json_encode(array(
			"kind" => "carrierbilling#userToken",
			"googleToken" => $GUT,
			"operatorToken" => $OUT
		));

		$requestToGoogle = new Google_Http_Request($url, 'POST');
		$headers = array(
			'Content-Type' => 'application/json; charset=UTF-8',
			'Content-Length' => strlen($data),
			'Authorization' => 'Bearer ' . $access_token,
		);
		$requestToGoogle->setRequestHeaders($headers);
		$requestToGoogle->setPostBody($data);
		$client = new Google_Client();
		$response = $client->getIo()->executeRequest($requestToGoogle);
		$status = $response[2];

		// Verifies request success
		if ($status != 200) {
			$responseObj = json_decode($response[0]);
			$errorMsg = $responseObj->error->message;
			Billrun_Factory::log()->log("No response from Google API.\nError message: "
				. $errorMsg . "\nData sent: " . $data, Zend_Log::ALERT);
		}
		else {
			Billrun_Factory::log()->log('Received status OK from Google for OUT ' . $OUT, Zend_Log::INFO);
		}
	}

	protected function getAccessToken() {
		$service_account_name = $this->tokenConfig['client_name'];
		$key_file_location = $this->tokenConfig['private_key'];

		$client = new Google_Client();
		$key = file_get_contents($key_file_location);
		$cred = new Google_Auth_AssertionCredentials(
			$service_account_name, 
			'https://www.googleapis.com/auth/carrierbilling', 
			$key
		);
		$client->setAssertionCredentials($cred);
		if ($client->getAuth()->isAccessTokenExpired()) {
			$client->getAuth()->refreshTokenWithAssertion($cred);
		}

		$res = json_decode($client->getAccessToken());
		return $res->access_token;
	}
}
