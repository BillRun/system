<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Rates action class
 *
 * @package  Action
 * @since    0.5
 */
class RatesAction extends ApiAction {

	public function execute() {
		Billrun_Factory::log()->log("Execute rates api call", Zend_Log::INFO);
		$request = $this->getRequest();
		
		$query = $request->get('query', array());
		$model = new RatesModel();
		$results = $model->getData($query, array('key', 'rates'));

		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'details' => $results,
				'input' => $request->getRequest(),
		)));

	}

}