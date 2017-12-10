<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * vlr action class
 *
 * @package  Action
 * 
 * @since    2.6
 */
class VLRAction extends ApiAction {

	public function execute() {
		Billrun_Factory::log("Execute VLR api call", Zend_Log::INFO);
		$request = $this->getRequest();

		$vlr = $request->get('vlr', NULL);

		if (empty($vlr)) {
			return $this->setError('VLR number is empty', $request->getRequest());
		}

		$max_prefix_length = 7;
		if (strlen($vlr) > $max_prefix_length) {
			$vlr = substr($vlr, 0, $max_prefix_length);
		}

		$cacheParams = array(
			'fetchParams' => array(
				'vlr' => $vlr,
			),
		);

		$this->setCacheLifeTime(604800); // 1 week
		$rate = $this->cache($cacheParams);

		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'details' => $rate,
				'input' => $request->getRequest(),
		)));
	}

	protected function fetchData($params) {
		$model = new RatesModel();
		$rate = $model->getRateByVLR($params['vlr']);
		unset($rate['_id']);
		return $rate;
	}

}
