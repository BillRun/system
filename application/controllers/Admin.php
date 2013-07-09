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
	 * save controller
	 * @return boolean
	 * @todo move to model
	 */
	public function editAction() {
		$coll = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);
		$id = Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);

		$collection = Billrun_Factory::db()->getCollection($coll);
		if (!($collection instanceof Mongodloid_Collection)) {
			return false;
		}

		$entity = $collection->findOne($id, true);

		// convert mongo values into javascript values
		$entity['_id'] = (string) $entity['_id'];
		$entity['from'] = (new Zend_Date($entity['from']->sec))->toString('YYYY-MM-dd HH:mm:ss');
		$entity['to'] = (new Zend_Date($entity['to']->sec))->toString('YYYY-MM-dd HH:mm:ss');

		if (method_exists($this, $coll . 'DataLoad')) {
			call_user_func_array(array($this, $coll . 'DataLoad'), array($collection, &$entity));
		}

		// passing values into the view
		$this->getView()->entity = $entity;
		$this->getView()->collectionName = $coll;
		$this->getView()->protectedKeys = array(
			'_id',
//			'from',
//			'to',
		);
	}

	/**
	 * save controller
	 * @return boolean
	 * @todo move to model
	 */
	public function saveAction() {
		$data = (array) $this->getRequest()->get('data');
		$id = Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		if (method_exists($this, $coll . 'BeforeDataSave')) {
			call_user_func_array(array($this, $coll . 'BeforeDataSave'), array(&$data));
		}

		$collection = Billrun_Factory::db()->getCollection($coll);
		if (!($collection instanceof Mongodloid_Collection)) {
			return false;
		}

		$entity = $collection->findOne($id);

		$from = new Zend_Date($data['from'], null, 'he-IL');
		$to = new Zend_Date($data['to'], null, 'he-IL');

		// close the old line
		$currentTime = new MongoDate();
		$entity->set('to', $currentTime);

		// open new line
		$currentTime->sec += 1;
		$newEntity = new Mongodloid_Entity($data);
		//convert dates into mongodate
		$newEntity->set('from', $currentTime);
		$newEntity->set('to', new MongoDate($to->getTimestamp()));
		$saveStatus = $newEntity->save($collection);

		$ret = array(
			'status' => $saveStatus,
			'closeLine' => $entity,
			'newLine' => $newEntity,
		);
		// @TODO: need to load ajax view
		// for now just die with json
		die(json_encode($ret));
	}

	/**
	 * method to convert plans ref into their name
	 * triggered before present the rate entity for edit
	 * 
	 * @param Mongodloid collection $collection
	 * @param array $entity
	 * 
	 * @return type
	 * @todo move to model
	 */
	protected function ratesDataLoad($collection, &$entity) {
		if (!isset($entity['rates'])) {
			return;
		}

		foreach ($entity['rates'] as &$rate) {
			if (isset($rate['plans'])) {
				foreach ($rate['plans'] as &$plan) {
					$data = $collection->getRef($plan);
					$plan = $data->get('name');
				}
			}
		}
	}

	/**
	 * method to convert plans ref into their name
	 * triggered before present the rate entity for edit
	 * 
	 * @param Mongodloid collection $collection
	 * @param array $entity
	 * 
	 * @return void
	 * @todo move to model
	 */
	protected function ratesDataSave($collection, &$entity) {
		if (!isset($entity['rates'])) {
			return;
		}

		$plansColl = Billrun_Factory::db()->plansCollection();
		$currentDate = new MongoDate();
		//convert plans
		foreach ($entity['rates'] as &$rate) {
			if (isset($rate['plans'])) {
				$plans = array();
				foreach ($rate['plans'] as &$plan) {
					$planEntity = $plansColl->query('name', $plan)
							->lessEq('from', $currentDate)
							->greaterEq('to', $currentDate)
							->cursor()->current();
					$plans[] = $collection->createRef($planEntity);
				}
				$rate['plans'] = $plans;
			}
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
		$this->getView()->component = $this->setTableView('plans', $columns, array('creation_time' => -1), 'name');
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
		$this->getView()->component = $this->setTableView('rates', $columns, array('creation_time' => -1), 'key');
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

		foreach ($params as $key => $val) {
			$view->assign($key, $val);
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
	 * @todo refactoring this function
	 */
	protected function setTableView($table, $columns = array(), $sort = array(), $edit_key = null) {
		$page = (int) $this->getRequest()->get('page');
		$size = (int) $this->getRequest()->get('listSize');

		$session = Yaf_session::getInstance();
		$session->start();

		if (!isset($session->$table)) {
			$session->$table = new stdClass();
		}

		if ($page) {
			$session->$table->page = $page;
		} else if (!isset($session->$table->page)) {
			$session->$table->page = 0;
		}

		if ($size) {
			$session->$table->size = $size;
		} else if (!isset($session->$table->size)) {
			$session->$table->size = 100;
		}

		// use for model
		$options = array(
			'collection' => $table,
			'page' => $session->$table->page,
			'size' => $session->$table->size,
			'sort' => $sort,
		);

		if ($table == 'rates' || $table == 'plans') {
			$date = $this->getRequest()->getPost('dateFilter');
			if (is_string($date)) {
				$filterDate = new Zend_Date($date, null, new Zend_Locale('he_IL'));
			}

			if (isset($filterDate)) {
				$session->$table->filterDate = $filterDate;
			} else if (!isset($session->$table->filterDate)) {
				$session->$table->filterDate = new Zend_Date(null, null, new Zend_Locale('he_IL'));
			} // else it will take what already in the session

			$options['date'] = $session->$table->filterDate;
			$model = new TabledateModel($options);
		} else {
			$model = new TableModel($options);
		}

		$data = $model->getData();
		// TODO: use ready pager/paginiation class (zend? joomla?) with auto print
		$pagination = $model->printPager();
		$sizeList = $model->printSizeList();
		$title = ucfirst($table);

		$params = array(
			'data' => $data,
			'title' => $title,
			'columns' => $columns,
			'offset' => $model->offset(),
			'pagination' => $pagination,
			'sizeList' => $sizeList,
			'edit_key' => $edit_key,
		);

		if ($table == 'rates' || $table == 'plans') {
			$params['filterDate'] = $session->$table->filterDate;
		}
		$this->title = $title;

		$ret = $this->renderView('table', $params);

		return $ret;
	}

	protected function createFilterToolbar() {
		
	}

	// choose columns
	// delete
	// apply property
	// remove property
	protected function createToolbar() {
		
	}

	/**
	 * 
	 * @param string $tpl the default tpl the controller used; this will be override to use the general admin layout
	 * @param array $parameters parameters of the view
	 * 
	 * @return string the render layout including the page (component)
	 */
	protected function render($tpl, array $parameters = array()) {
		if ($tpl == 'edit') {
			return parent::render($tpl, $parameters);
		}
		$tpl = 'index';
		//check with active menu we are on
		$parameters['active'] = $this->getRequest()->getActionName();
		$parameters['title'] = $this->title;
		return $this->getView()->render($tpl . ".phtml", $parameters);
	}

}