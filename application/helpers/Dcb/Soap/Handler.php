<?php

/**
 * Dcb Soap Handler Class 
 * 
 * 
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero Public License version 3 or later; see LICENSE.txt
 */

/**
 * Dcb_Soap_Handler Class Definition
 */
class Dcb_Soap_Handler {

	const GOOGLE_RESULT_CODE_SUCCESS = 'SUCCESS';
	const GOOGLE_RESULT_CODE_GENERAL_FAILURE = 'GENERAL_FAILURE';
	const GOOGLE_RESULT_CODE_RETRIABLE_ERROR = 'RETRIABLE_ERROR';
	const GOOGLE_RESULT_CODE_INVALID_USER = 'INVALID_USER';
	const GOOGLE_RESULT_CODE_NO_LONGER_PROVISIONED = 'NO_LONGER_PROVISIONED';
	const GOOGLE_RESULT_CODE_INVALID_CURRENCY = 'INVALID_CURRENCY';
	const GOOGLE_RESULT_CODE_CHARGE_EXCEEDS_LIMIT = 'CHARGE_EXCEEDS_LIMIT';

	/**
	 * The subscriber associated with the request
	 * @var Billrun_Subscriber
	 */
	protected $subscriber;

	/**
	 *
	 * @var array
	 */
	protected $config;
	protected $model;

	public function __construct() {
		$this->config = Billrun_Factory::config()->getConfigValue('googledcb');
		$this->subscriber = Billrun_Factory::subscriber();
		$this->model = new FundsModel();
		if (!isset($this->config['transaction_limit'])) {
			$this->config['transaction_limit'] = self::toMicros(90);
		} else {
			$this->config['transaction_limit'] = self::toMicros($this->config['transaction_limit']);
		}
		if (!isset($this->config['transactions_per_month'])) {
			$this->config['transactions_per_month'] = 30;
		}
		if (!isset($this->config['total_per_month'])) {
			$this->config['total_per_month'] = self::toMicros(350);
		} else {
			$this->config['total_per_month'] = self::toMicros($this->config['total_per_month']);
		}
	}

	public function __call($name, $arguments) {
		if (method_exists($this, 'do' . $name)) {
			return call_user_func(array($this, 'do' . $name), $arguments[0]);
		}
		throw new Exception('Unknown method ' . $name);
	}

	public function doEcho($request) {
		$response = new stdclass;
		$response->Version = $request->Version;
		$response->CorrelationId = $request->CorrelationId;
		$response->Result = self::GOOGLE_RESULT_CODE_SUCCESS;
		$response->OriginalMessage = $request->Message;
		return $response;
	}

	public function GetProvisioning($request) {
		$response = new stdclass;
		$response->Version = $request->Version;
		$response->CorrelationId = $request->CorrelationId;
		$subscriberDetails = $this->getSubscriberDetails($request->UserIdentifier->OperatorUserToken);
		if ($subscriberDetails) {
			$ndc_sn = $subscriberDetails['ndc_sn'];
			$identityParams = $this->getIdentityParams($ndc_sn);
			$this->subscriber->load($identityParams);
			if (!$this->subscriber->isValid()) {
				$response->Result = self::GOOGLE_RESULT_CODE_INVALID_USER;
			} else {
				$response->Result = self::GOOGLE_RESULT_CODE_SUCCESS;
				if ($this->isDcbProvisioned($this->subscriber)) {
					$response->IsProvisioned = TRUE;
					$response->SubscriberCurrency = $this->config['currency'];
					$response->TransactionLimit = $this->config['transaction_limit'];
					$response->AccountType = $this->config['account_type'];
				} else {
					$response->IsProvisioned = FALSE;
				}
			}
		} else {
			$response->Result = self::GOOGLE_RESULT_CODE_INVALID_USER;
		}
		return $response;
	}

	public function Auth($request) {
		$response = new stdclass;
		$response->Version = $request->Version;
		$response->CorrelationId = $request->CorrelationId;
		$subscriberDetails = $this->getSubscriberDetails($request->OperatorUserToken);
		$sid = $subscriberDetails['sid'];
		$aid = $subscriberDetails['aid'];
		$plan = $subscriberDetails['plan'];
		$ndcSn = $subscriberDetails['ndc_sn'];
		if ($request->Currency != $this->config['currency']) {
			$response->Result = self::GOOGLE_RESULT_CODE_INVALID_CURRENCY;
		} else if ($sid) {
			$identityParams = $this->getIdentityParams($ndcSn);
			$this->subscriber->load($identityParams);
			if (!$this->subscriber->isValid()) {
				$response->Result = self::GOOGLE_RESULT_CODE_INVALID_USER;
			} else {
				if ($this->isDcbProvisioned($this->subscriber)) {
					$response->Result = self::GOOGLE_RESULT_CODE_SUCCESS;
				} else {
					$response->Result = self::GOOGLE_RESULT_CODE_NO_LONGER_PROVISIONED;
				}
			}
		} else {
			$response->Result = self::GOOGLE_RESULT_CODE_INVALID_USER;
		}

		$data = (array) $request;
		$status = $this->model->getNotificationStatus($data['CorrelationId']);

		if (!$status) {
			$billrunMonth = Billrun_Util::getBillrunKey(time());
			if ($request->Total > $this->config['transaction_limit']) {
				$response->Result = self::GOOGLE_RESULT_CODE_CHARGE_EXCEEDS_LIMIT;
			} else if ($this->config['transactions_per_month'] != -1 || $this->config['total_per_month'] != -1) {
				$fundsStats = $this->model->getFundsStats($sid, $billrunMonth);
				if ($fundsStats) {
					$fundsStats = current($fundsStats);
					if ($this->config['transactions_per_month'] != -1 && $fundsStats['count'] >= $this->config['transactions_per_month']) {
						$response->Result = self::GOOGLE_RESULT_CODE_CHARGE_EXCEEDS_LIMIT;
					} else if ($this->config['total_per_month'] != -1 && (($fundsStats['sum'] + $request->Total) > $this->config['total_per_month'])) {
						$response->Result = self::GOOGLE_RESULT_CODE_CHARGE_EXCEEDS_LIMIT;
					}
				}
			}
			$data['billrunMonth'] = $billrunMonth;
			$data['sid'] = $sid;
			$data['aid'] = $aid;
			$data['plan'] = $plan;
			$data['responseResult'] = $response->Result;
			$ret = $this->model->storeData($data);

			if (is_null($ret)) {
				$response->Result = self::GOOGLE_RESULT_CODE_GENERAL_FAILURE;
			}
		} else {
			$response->Result = $status;
		}

		return $response;
	}

	public function CancelNotification($request) {
		$data = (array) $request;
		$this->model->cancelNotification($data);
	}

	/**
	 * Indicates if the subscriber is provisioned for Dcb
	 * @param Billrun_Subscriber $subscriber
	 */
	protected function isDcbProvisioned($subscriber) {
		return $subscriber->isExtraDataActive('google_play');
	}

	protected function getSubscriberDetails($OUT) {
		$cursor = Billrun_Factory::db()->tokensCollection()->query(array('OUT' => $OUT))->cursor();
		if (!$cursor->count()) {
			return null;
		} else {
			return array(
				"sid" => $cursor->current()['sid'],
				"aid" => $cursor->current()['aid'],
				"plan" => $cursor->current()['plan'],
				"ndc_sn" => $cursor->current()['ndc_sn'],
			);
		}
	}

	protected function getIdentityParams($ndc_sn) {
		$params['EXTRAS'] = 1;
		$params['time'] = date(Billrun_Base::base_dateformat);
		$callingNumberCRMConfig = Billrun_Config::getInstance()->getConfigValue('customer.calculator.customer_identification_translation.caller.calling_number', array('toKey' => 'NDC_SN', 'clearRegex' => '/^0*\+{0,1}972/'));
		$params[$callingNumberCRMConfig['toKey']] = preg_replace($callingNumberCRMConfig['clearRegex'], '', $ndc_sn);
		return $params;
	}

	public static function toMicros($value) {
		return intval($value * 1000000);
	}
	
	public static function fromMicros($value) {
		return intval($value / 1000000);
	}

}
