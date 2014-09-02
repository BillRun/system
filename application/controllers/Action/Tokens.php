<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
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
		$subscriberField = Billrun_Config::getInstance()->getConfigValue('googledcb.subscriberField');
		$identities = $subscriber->getSubscribersByParams(array($params), $subscriber->getAvailableFields());
		if (isset($identities[0]) && $identities[0]->{$subscriberField}) { // to do: change to $subscriber->isValid() once it works
			$sid = $identities[0]->{$subscriberField};
			if (is_numeric($identities[0]->{$subscriberField})) {
				$sid = intval($sid);
			}
		} else {
			Billrun_Factory::log()->log('Google dcb association failed. Unknown ndc sn ' . $ndcSn, Zend_Log::ALERT);
			return FALSE;
		}
		// Insert to DB
		$model = new TokensModel();
		$model->storeData($GUT, $OUT, $sid);

		// Send request to google
		$url = Billrun_Factory::config()->getConfigValue('googledcb.association.tokens.host') .
				Billrun_Factory::config()->getConfigValue('googledcb.association.tokens.post');
		$data = array(
			"kind" => 'carrierbilling#userToken',
			"GUT" => $GUT,
			"OUT" => $OUT,
		);

		$response = Billrun_Util::sendRequest($url, $data, array("onlyBody" => false));
		$status = $response->getStatus();

		// Verifies request success
		if ($status != 200) {
			Billrun_Factory::log()->log("No response from Google API.\nData sent: " . $data, Zend_Log::ALERT);
		}
	}

}
