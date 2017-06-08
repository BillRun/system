<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Red button action class
 * Used for real time alerts
 *
 * @package  Action
 * @since    4.6
 */
class RedButtonAction extends ApiAction {
	
	protected $request = null;
	protected $response = null;
	protected $params = null;
	protected $configColl = null;
	protected static $RG_CONFIG_FIELD = 'rg_conversion';

	public function execute() {
		Billrun_Factory::log("Execute Red Button action", Zend_Log::INFO);
		$this->request = $this->getRequest()->getRequest();
		$action = $this->request['action'];
		if (!method_exists($this, $action)) {
			$errorMsg = 'Cannot find red button action: "' . $action . '"';
			$this->setError($errorMsg, $this->request);
			return;
		}
		
		$this->configColl = Billrun_Factory::db()->configCollection();
		$this->{$action}();
	}
	
	protected function ratingGroupConversion() {
		if (!isset($this->request['mode'])) {
			$this->setError('Rating Group Conversion  - mode is not set', $this->request);
			return;
		}
		$mode = $this->request['mode'];
		$this->params = json_decode($this->request['params'], JSON_OBJECT_AS_ARRAY);
		if (!$this->validateRatingGroupConversionParmas()) {
			$this->setError('Rating Group Conversion  - invalid/missing params', $this->request);
			return;
		}
		
		$ratingGroupConversion = $this->getLastConfigRatingGroupConversion();
		
		switch ($mode) {
			case 'on':
				$ratingGroupConversion[] = $this->params;
				break;
			case 'off':
				foreach ($ratingGroupConversion as $index => $conversion) {
					if ($this->isEqualRatingGroupConversion($conversion, $this->params)) {
						unset($ratingGroupConversion[$index]);
					}
				}
				break;

			default:
				$this->setError('Rating Group Conversion  - invalid mode', $this->request);
				return;
		}
		
		$currentConf[self::$RG_CONFIG_FIELD] = $ratingGroupConversion;
		$this->configColl->insert($currentConf);
		$this->responseSuccess("Rating Group Conversion $mode Successfully");
	}
	
	protected function getRatingGroupParams() {
		return array('mcc', 'from_rg', 'to_rg');
	}
	
	protected function validateRatingGroupConversionParmas() {
		if (!$this->params) {
			return false;
		}
		$rg_params = $this->getRatingGroupParams();
		foreach ($rg_params as $rg_param) {
			if (!isset($this->params[$rg_param])) {
				return false;
			}
		}
		
		return true;
	}

	protected function isEqualRatingGroupConversion($rg_conversion1, $rg_conversion2) {
		$rg_params = $this->getRatingGroupParams();
		foreach ($rg_params as $rg_param) {
			if ($rg_conversion1[$rg_param] !== $rg_conversion2[$rg_param]) {
				return false;
			}
		}
		
		return true;
	}
	
	protected function getLastConfigRatingGroupConversion() {
		$currentConf = $this->configColl
			->query()
			->cursor()->setReadPreference('RP_PRIMARY')
			->sort(array('_id' => -1))
			->limit(1)
			->current()
			->getRawData();
		unset($currentConf['_id']);
		return isset($currentConf[self::$RG_CONFIG_FIELD]) ? $currentConf[self::$RG_CONFIG_FIELD] : array();
	}
	
	protected function responseSuccess($msg = 'success') {
		$this->getController()->setOutput(array(array('status' => 1, 'message' => $msg, 'request' => $this->request)));
	}
}
