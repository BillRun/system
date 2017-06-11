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
	protected $rg_config_field = '';

	public function execute() {
		Billrun_Factory::log("Execute Red Button action", Zend_Log::INFO);
		$this->request = $this->getRequest()->getRequest();
		$this->rg_config_field = Billrun_Factory::config()->getConfigValue('rating_group_conversion.rg_config_field', 'rg_conversion');
		$action = $this->request['action'];
		if (!method_exists($this, $action)) {
			$errorMsg = 'Cannot find red button action: "' . $action . '"';
			$this->setError($errorMsg, $this->request);
			return;
		}
		
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
		
		$currentConf[$this->rg_config_field] = $ratingGroupConversion;
		$configColl = Billrun_Factory::db()->configCollection();
		$ret = $configColl->insert($currentConf);
		if (!isset($ret['ok']) || !$ret['ok']) {
			$this->setError('Rating Group Conversion  - error saving to DB. details: ' . print_R($ret, 1), $this->request);
			return;
		}
		$this->responseSuccess("Rating Group Conversion $mode Successfully");
	}
	
	protected function getRatingGroupParams() {
		return Billrun_Factory::config()->getConfigValue('rating_group_conversion.rating_group_params', array());
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
		return Billrun_Factory::config()->getConfigValue($this->rg_config_field, array());
	}
	
	protected function getRatingGroupConversionsLog() {
		$limit = isset($this->request['limit']) ? $this->request['limit'] : 10;
		$logCollection = Billrun_Factory::db()->logCollection();
		$query = array(
			'source' => 'api',
			'type' => 'redbutton',
			'request.action' => 'ratingGroupConversion',
			'request.mode' => array('$in' => array('on', 'off')),
		);
		$sort = array('process_time' => -1);
		$conversions = $logCollection->find($query)->sort($sort)->limit($limit);
		$ret = array();
		foreach ($conversions as $conversion) {
			$ret[] = array_merge(array(
				'time' => $conversion['process_time'],
				'mode' => $conversion['request']['mode'],
				'user' => $conversion['user_name'] ? $conversion['user_name'] : 'API',
			),
			json_decode($conversion['request']['params'], JSON_OBJECT_AS_ARRAY));
		}
		
		$this->getController()->setOutput(array(array('status' => 1, 'response' => $ret)));
	}

	protected function getRatingGroupConversions() {
		$ret = $this->getLastConfigRatingGroupConversion();
		$this->getController()->setOutput(array(array('status' => 1, 'response' => $ret)));
	}
	
	protected function responseSuccess($msg = 'success') {
		$this->getController()->setOutput(array(array('status' => 1, 'message' => $msg, 'request' => $this->request)));
	}
}
