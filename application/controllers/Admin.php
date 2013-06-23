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
		$this->baseUrl = $this->getRequest()->getBaseUri();
	}

	/**
	 * default method of admin
	 */
	public function indexAction() {
		if (($table = $this->getRequest()->getParam('table'))) {
			$this->getView()->component = $this->setTableView($table);
		} else {
			$this->getView()->component = $this->renderView('home');
		}
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

		if (isset($params['data'])) {
			$view->assign('data', $params['data']);
		}
		if (isset($params['columns'])) {
			$view->assign('columns', $params['columns']);
		}
		if (isset($params['title'])) {
			$view->assign('title', $params['title']);
		}
		return $view->render($viewName . '.phtml', $params);
	}

	public function plansAction() {
		$columns = array(
			'name',
			'from',
			'to',
			'_id',
		);
		$this->getView()->component = $this->setTableView('plans', $columns);
	}

	public function ratesAction() {
		$columns = array(
			'key',
			'from',
			'to',
			'_id',
		);
		$this->getView()->component = $this->setTableView('rates', $columns);
	}

	protected function setTableView($table, $columns) {
		$page = 0;
		$limit = 100;
		$options = array(
			'collection' => $table,
			'page' => $page,
			'size' => $limit,
		);

		$model = new TableModel($options);
		$data = $model->getData();

		$params = array(
			'data' => $data,
			'title' => ucfirst($table),
			'columns' => $columns,
		);
		$ret = $this->renderView('table', $params);

		return $ret;
	}

	protected function render($tpl, array $parameters = array()) {
		$tpl = 'index';
		//check with active menu we are on
		$parameters['active'] = $this->getRequest()->getActionName();
		return $this->getView()->render($tpl . ".phtml", $parameters);
	}

}