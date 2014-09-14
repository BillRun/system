<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
		Billrun_Factory::log()->log("Execute VLR api call", Zend_Log::INFO);
		$request = $this->getRequest();

		$vlr = $request->get('vlr', NULL);
		
		$cacheParams = array(
			'fetchParams' => array(
				'vlr' => $vlr,
			),
		);
		
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