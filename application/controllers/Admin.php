<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing admin controller class
 *
 * @package  Controller
 * @since    1.0
 */
class AdminController extends Yaf_Controller_Abstract {

	/**
	 * use for page title
	 * 
	 * @var string 
	 */
	protected $status_model;

	/**
	 * method to control and navigate the user to the right view
	 */
	public function init() {
		$this->status_model = new statusModel();
	}

	/**
	 * default controller of admin
	 */
	public function indexAction() {

		$params['testConnection'] = $this->status_model->testFtpConnection();
		$last_file = $this->status_model->lastFile();
		$params['file_name'] = $last_file['file_name'];
		$params['received_time'] = $last_file['received_time'];
		print( $this->renderView('status', $params));
//		$this->getView()->component = $this->renderView('status');
	}

	/**
	 * method to render component page
	 * 
	 * @param string $viewName the view name to render
	 * @return type
	 */
	protected function renderView($viewName, array $params = array()) {
		$path = Billrun_Factory::config()->getConfigValue('application.directory');
		$view_path = $path . '/views/' . strtolower($this->getRequest()->getControllerName());
		$view = new Yaf_View_Simple($view_path);

		foreach ($params as $key => $val) {
			$view->assign($key, $val);
		}

		return $view->render($viewName . '.phtml', $params);
	}

}
