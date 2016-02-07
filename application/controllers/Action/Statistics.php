<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * This class holds the api logic for the statistics.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       4.0
 * @author Spyes
*/
class StatisticsAction extends ApiAction {

	protected $model;

	protected function initializeModel() {
		$this->model = new StatisticsModel();
	}
	
	public function execute() {
		$this->initializeModel();
		$statistics = json_decode($this->getRequest()->get('statistics'), true);
		if (!$statistics || empty($statistics)) {
			Billrun_Factory::log("No statistics specified for save!", Zend_Log::NOTICE);
			$output = array(
				'status' => 0,
				'desc' => 'No statistics specified for save!'
			);
		} else {
			$this->model->update($statistics);
			$output = array(
				'status' => 1,
				'desc' => 'create',
				'input' => $statistics
			);
		}
		$this->getController()->setOutput(array($output));
	}
}