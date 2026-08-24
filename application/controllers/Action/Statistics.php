<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * This class holds the api logic for the statistics.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       4.0
 */
class StatisticsAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	protected $model;

	protected function initializeModel() {
		$this->model = new StatisticsModel();
	}

	protected function create($request) {
		$this->initializeModel();
		$statistics = json_decode($request->get('statistics'), true);
		if (!$statistics || empty($statistics)) {
			Billrun_Factory::log("No statistics specified for save!", Zend_Log::NOTICE);
			return array(
				'status' => 0,
				'desc' => 'No statistics specified for save!'
			);
		} else {
			$statistics['creation_date'] = new Mongodloid_Date();
			$this->model->update($statistics);
			return array(
				'status' => 1,
				'desc' => 'create',
				'input' => $statistics
			);
		}
	}

	protected function query($request) {
		$this->initializeModel();
		$from = $this->getRequest()->get('from');
		$to = $this->getRequest()->get('to');
		$query = array("creation_date" => array());
		if ($from) {
			$query["creation_date"]['$gte'] = new Mongodloid_Date(strtotime($from));
		} else {
			$query["creation_date"]['$gte'] = new Mongodloid_Date(strtotime('1970-01-01'));
		}
		if ($to) {
			$query["creation_date"]['$lte'] = new Mongodloid_Date(strtotime($to));
		}
		$data = $this->model->getData($query);
		$statistics = array();
		foreach ($data as $statistic) {
			$statistics[] = $statistic->getRawData();
		}
		return array(
			'status' => 1,
			'desc' => $statistics
		);
	}

	public function execute() {
		$this->allowed();
		$method = $this->getRequest()->get('method');
		if (empty($method)) {
			$output = array(
				'status' => 0,
				'desc' => "No method supplied"
			);
		} else if ($method === "create") {
			$output = $this->create($this->getRequest());
		} else if ($method === "query") {
			$output = $this->query($this->getRequest());
		} else {
			$output = array(
				'status' => 0,
				'desc' => 'Unsupported method'
			);
		}
		$this->getController()->setOutput(array($output));
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
