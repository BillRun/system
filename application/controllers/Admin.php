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

	/**
	 * use for page title
	 * 
	 * @var string 
	 */
	protected $title = null;
	/**
	 * method to control and navigate the user to the right view
	 */
	public function init() {
		$this->baseUrl = $this->getRequest()->getBaseUri();
	}

	/**
	 * default controller of admin
	 */
	public function indexAction() {
		if (($table = $this->getRequest()->getParam('table'))) {
			$this->getView()->component = $this->setTableView($table);
		} else {
			$this->getView()->component = $this->renderView('home');
		}
	}
	
	/**
	 * plans controller of admin
	 */
	public function plansAction() {
		$columns = array(
			'name',
			'from',
			'to',
			'_id',
		);
		$this->getView()->component = $this->setTableView('plans', $columns, array('creation_time' => -1));
	}

	/**
	 * rates controller of admin
	 */
	public function ratesAction() {
		$columns = array(
			'key',
			'from',
			'to',
			'_id',
		);
		$this->getView()->component = $this->setTableView('rates', $columns, array('creation_time' => -1));
	}

	/**
	 * events controller of admin
	 */
	public function eventsAction() {
		$columns = array(
			'creation_time',
			'event_type',
			'imsi',
			'source',
			'threshold',
			'units',
			'value',
			'_id',
		);
		$this->getView()->component = $this->setTableView('events', $columns, array('creation_time' => -1));
	}

	/**
	 * log controller of admin
	 */
	public function logAction() {
		$columns = array(
			'source',
			'type',
			'file_name',
			'received_time',
			'process_time',
			'_id',
		);
		$this->getView()->component = $this->setTableView('log', $columns, array('received_time' => -1));
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
		if (isset($params['pager'])) {
			$view->assign('pager', $params['pager']);
		}
		if (isset($params['title'])) {
			$view->assign('title', $params['title']);
			$this->title = $params['title'];
		}
		return $view->render($viewName . '.phtml', $params);
	}

	/**
	 * method to render table view
	 * 
	 * @param string $table the db table to render
	 * @param array $columns the columns to show
	 * 
	 * @return string the render page (HTML)
	 */
	protected function setTableView($table, $columns = array(), $sort = array()) {
		$page = (int) $this->getRequest()->get('page', 0);
		$limit = (int) $this->getRequest()->getParam('listSize', 100);

		$options = array(
			'collection' => $table,
			'page' => $page,
			'size' => $limit,
			'sort' => $sort,
		);

		$model = new TableModel($options);
		$data = $model->getData();
		// use ready pager/paginiation class (zend? joomla?) with auto print
		$pager = $model->getPager();
		
		$params = array(
			'data' => $data,
			'title' => ucfirst($table),
			'columns' => $columns,
			'pager' => $pager,
		);

		$ret = $this->renderView('table', $params);

		return $ret;
	}

	/**
	 * 
	 * @param string $tpl the default tpl the controller used; this will be override to use the general admin layout
	 * @param array $parameters parameters of the view
	 * 
	 * @return string the render layout including the page (component)
	 */
	protected function render($tpl, array $parameters = array()) {
		$tpl = 'index';
		//check with active menu we are on
		$parameters['active'] = $this->getRequest()->getActionName();
		$parameters['title'] = $this->title;
		return $this->getView()->render($tpl . ".phtml", $parameters);
	}

}