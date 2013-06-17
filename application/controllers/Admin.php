<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing admin controller class
 *
 * @package  Controller
 * @since    0.5
 */
class AdminController extends Yaf_Controller_Abstract {
	
	protected $componentView = null;

	/**
	 * method to control and navigate the user to the right view
	 */
	public function init() {
		// TODO: take from config?
		$this->base_url = $this->getRequest()->getBaseUri();
//		$action = $this->getRequest()->getActionName();

//		$path = Billrun_Factory::config()->getConfigValue('application.directory');
//		$view = new Yaf_View_Simple($path . '/views/layout');
//		Yaf_Application::app()->getDispatcher()->setView($view);
			
	}
	
	/**
	 * default method of admin
	 */
	public function indexAction() {
		if (($table = $this->getRequest()->getParam('table'))) {
			$this->setTableView($table);
		} else {
//			$this->getView()->component = $this->renderView('home');
		}
	}
	
	/**
	 * 
	 * @param string $viewName the view name to render
	 * @return type
	 */
	protected function renderView($viewName) {
		$path = Billrun_Factory::config()->getConfigValue('application.directory');
		var_dump($path);
		$view_path = $path . '/views/' . strtolower($this->getRequest()->getControllerName());
		var_dump($view_path);
		$view = new Yaf_View_Simple($view_path);
		var_dump($viewName);
		die("asd");
		return $view->render($viewName);
	}

	public function plansAction() {
		$this->setTableView('plans');
	}

	public function ratesAction() {
		$this->setTableView('rates');		
	}

	protected function setTableView($table) {
		$page = 0;
		$limit = 100;
		$options = array(
			'collection' => $table,
			'page' => $page,
			'size' => $limit,
		);
		
		$model = new TableModel($options);
		$this->getView()->data = $model->getData();
//		$this->getView()->component = $this->render(dirname($_SERVER['DOCUMENT_ROOT']) . '/application/views/admin/table');
	}

}