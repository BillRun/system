<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Report action class
 *
 * @package  Action
 * @since 5.5
 * 
 */
class ReportAction extends ApiAction {
	
	use Billrun_Traits_Api_UserPermissions;
	
	
	
	protected $model = null;
	protected $request = null;
	protected $status = true;
	protected $desc = 'success';
	protected $next_page = true;
	protected $response = array();
	
	public function execute() {
		$this->request = $this->getRequest(); // supports GET / POST requests;
		$action = $this->request->getRequest('action', '');
		$this->model = new ReportModel();
		if (!method_exists($this, $action)) {
			return $this->setError('Report controller - cannot find action: ' . $action);
		}
		$query = $this->request->getRequest('query', null);
		$page = $this->request->getRequest('page', 0);
		$size = $this->request->getRequest('size', -1);
		$this->{$action}($query, $page, $size);
		return $this->response();
	}
	
	public function generateReport($query, $page, $size) {
		$debug = true;
		$parsed_query = json_decode($query, TRUE);
		$nextPageData = (!$debug && $size !== -1) ? $this->model->applyFilter($parsed_query, $page + 1, $size) : array(); // TODO: improve performance, avoid duplicate aggregate run
		$this->response = $this->model->applyFilter($parsed_query, $page, $size);
		$this->next_page = count($nextPageData) > 0; 
	}
	
	protected function response() {
		$this->getController()->setOutput(array(
			array(
				'status' => $this->status,
				'desc' => $this->desc,
				'details' => $this->response,
				'next_page' => $this->next_page,
			)
		));
		return true;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}