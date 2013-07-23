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
	protected $session = null;
	protected $model = null;

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
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$id = Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);

		$model = self::getModel($coll);
		$entity = $model->getItem($id);
		if ($type == 'close_and_new' && is_subclass_of($model, "TabledateModel") && !$model->isLast($entity)) {
			die("There's already a newer entity with this key");
		}

		// passing values into the view
		$this->getView()->entity = $entity;
		$this->getView()->collectionName = $coll;
		$this->getView()->type = $type;
		$this->getView()->protectedKeys = $model->getProtectedKeys($entity, $type);
		$this->getView()->hiddenKeys = $model->getHiddenKeys($entity, $type);
	}

	public function confirmAction() {
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$ids = Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);

		$model = self::getModel($coll);

		if ($type == 'remove' && $coll != 'lines') {
			$entity = $model->getItem($ids);
			$this->getView()->entity = $entity;
			$this->getView()->key = $entity[$model->search_key];
			if (!$model->isLast($entity)) {
				die("There's already a newer entity with this key");
			} else if (is_subclass_of($model, "TabledateModel") && !$model->startsInFuture($entity)) {
				die("Only future entities could be removed");
			}
		} else {
			$this->getView()->key = "the selected lines";
		}


		$this->getView()->collectionName = $coll;
		$this->getView()->type = $type;
		$this->getView()->ids = $ids;
//		$this->getView()->component = $this->renderView('remove', array('entity' => $entity, 'collectionName' => $coll, 'type' => $type, 'key' => $entity[$model->search_key]));
	}

	/**
	 * save controller
	 * @return boolean
	 * @todo move to model
	 * @todo protect the from and to to be continuely
	 */
	public function removeAction() {
		$ids = explode(",", Billrun_Util::filter_var($this->getRequest()->get('ids'), FILTER_SANITIZE_STRING));
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);

		$model = self::getModel($coll);

		$collection = Billrun_Factory::db()->getCollection($coll);
		if (!($collection instanceof Mongodloid_Collection)) {
			return false;
		}

		if (count($ids) == 1) {
			$params['_id'] = $ids[0];
		} else {
			foreach ($ids as $id) {
				$params['_id']['_id']['$in'][] = new MongoId($id);
			}
		}

		if ($type == 'remove') {
			$saveStatus = $model->remove($params);
		}

		// @TODO: need to load ajax view
		// for now just die with json
		die(json_encode(null));
	}

	/**
	 * save controller
	 * @return boolean
	 * @todo move to model
	 * @todo protect the from and to to be continuely
	 */
	public function saveAction() {
		$flatData = $this->getRequest()->get('data');
		$id = Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);

		$model = self::getModel($coll);

		$collection = Billrun_Factory::db()->getCollection($coll);
		if (!($collection instanceof Mongodloid_Collection)) {
			return false;
		}

		$data = @json_decode($flatData, true);

		if (empty($data) || empty($id) || empty($coll)) {
			return false;
		}

		$params = array_merge($data, array('_id' => new MongoId($id)));

		if ($type == 'update') {
			$saveStatus = $model->update($params);
		} else if ($type == 'close_and_new') {
			$saveStatus = $model->closeAndNew($params);
		} else if ($type == 'duplicate') {
			$saveStatus = $model->duplicate($params);
		}

//		$ret = array(
//			'status' => $saveStatus,
//			'closeLine' => $entity->getRawData(),
//			'newLine' => $newEntity->getRawData(),
//		);
		// @TODO: need to load ajax view
		// for now just die with json
		die(json_encode(null));
	}

	/**
	 * method to save all related rates after save
	 * 
	 * @param Mongodloid_Collection $collection The collection to save to
	 * @param Mongodolid_Entity $entity The entity to save
	 * 
	 * @return void
	 * @todo move to model
	 */
	protected function plansAfterDataSave($collection, &$entity) {
		$ratesColl = Billrun_Factory::db()->ratesCollection();
		$planName = $entity->get('name');
		$ratesColl->query('rates.call.plans', $entity->get('name'));
	}

	/**
	 * plans controller of admin
	 */
	public function plansAction() {
		$this->forward("tabledate", array('table' => 'plans'));
		return false;
	}

	/**
	 * rates controller of admin
	 */
	public function ratesAction() {
		$this->forward("tabledate", array('table' => 'rates'));
		return false;
	}

	public function tabledateAction() {
		$table = $this->_request->getParam("table");
		$session = $this->getSession($table);
		$date = $this->getVar($session, 'dateFilter');
		if (is_string($date)) {
			$dateFilter = new Zend_Date($date, null, new Zend_Locale('he_IL'));
		}

		if (isset($dateFilter)) {
			$session->dateFilter = $dateFilter;
		} else if (!isset($session->dateFilter)) {
			$session->dateFilter = new Zend_Date(null, null, new Zend_Locale('he_IL'));
		} // else it will take what already in the session

		$options['date'] = $session->dateFilter;

		$dateInput = new MongoDate($options['date']->getTimestamp());

		$query['$and'] = array(
			array(
				'from' => array(
					'$lt' => $dateInput,
				),
				'to' => array(
					'$gt' => $dateInput,
				),
			)
		);
		$this->getView()->component = $this->buildComponent($table, $query, array('creation_time' => -1), $options);
	}

	/**
	 * lines controller of admin
	 */
	public function linesAction() {
		$session = $this->getSession('lines');
		$garbage = $this->getVar($session, "garbage", "off");
		$query = array();
		if ($garbage == "on") {
			$rates_coll = Billrun_Factory::db()->ratesCollection();
			$unrated_rate = $rates_coll->query("key", "UNRATED")->cursor()->current()->createRef($rates_coll);
			$month_ago = new MongoDate(strtotime("1 month ago"));
			$query['$or'] = array(
				array('customer_rate' => $unrated_rate), // customer rate is "UNRATED"
				array('subscriber_id' => false), // or subscriber not found
				array('$and' => array(// old unpriced records which should've been priced
						array('customer_rate' => array(
								'$exists' => true,
								'$nin' => array(
									false, $unrated_rate
								),
						)),
						array('subscriber_id' => array(
								'$exists' => true,
								'$ne' => false,
						)),
						array('unified_record_time' => array(
								'$lt' => $month_ago
						)),
						array('price_customer' => array(
								'$exists' => false
						)),
				)),
			);
		}

		$sort = array('unified_record_time' => -1);

		$this->getView()->component = $this->buildComponent('lines', $query, $sort);
	}

	/**
	 * events controller of admin
	 */
	public function eventsAction() {
//		$columns = array(
//			'creation_time' => 'Creation time',
//			'event_type' => 'Event type',
//			'imsi' => 'IMSI',
//			'msisdn' => 'MSISDN',
//			'source' => 'Source',
//			'threshold' => 'Threshold',
//			'units' => 'Units',
//			'value' => 'Value',
//			'notify_time' => 'Notify time',
//			'email_sent' => 'Email sent',
//			'priority' => 'Priority',
//			'_id' => 'Id',
//		);
//		$this->getView()->component = $this->setTableView('events', $columns, array('creation_time' => -1));
		$query = array();
		$sort = array();
		$this->getView()->component = $this->buildComponent('events', $query, $sort);
	}

	/**
	 * log controller of admin
	 */
	public function logAction() {
		$columns = array(
			'source' => 'Source',
			'type' => 'Type',
			'retrieved_from' => 'Retrieved from',
			'file_name' => 'Filename',
			'received_time' => 'Date received',
			'process_time' => 'Date processed',
			'_id' => 'Id',
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
	protected function getTableViewParams($filter_query = array()) {

		$data = $this->model->getData($filter_query);
		$columns = $this->model->getTableColumns();
		$edit_key = $this->model->getEditKey();
		$pagination = $this->model->printPager();
		$sizeList = $this->model->printSizeList();

		$params = array(
			'data' => $data,
			'columns' => $columns,
			'edit_key' => $edit_key,
			'pagination' => $pagination,
			'sizeList' => $sizeList,
		);

		return $params;
	}

	protected function createFilterToolbar() {

		$params['criteria_tpl'] = $this->model->toolbar();
//		if ($table == 'lines' || $table == 'events') {
//			$params['criteria_tpl'] = 'events';
//		} else {
//			$criteria_tpl = 'date';
//		}

		return $params;
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
		if ($tpl == 'edit' || $tpl == 'confirm') {
			return parent::render($tpl, $parameters);
		}
		$tpl = 'index';
		//check with active menu we are on
		$parameters['active'] = $this->getRequest()->getActionName();
		$parameters['title'] = $this->title;
		return $this->getView()->render($tpl . ".phtml", $parameters);
	}

	public function getModel($collection_name, $options = array()) {
		if (is_null($this->model)) {
			$model_name = ucfirst($collection_name) . "Model";
			if (class_exists($model_name)) {
				$this->model = new $model_name($options);
			} else {
				die("Error loading model");
			}
		}
		return $this->model;
	}

	protected function buildComponent($table, $filter_query, $sort = array(), $options = array()) {
		$this->title = ucfirst($table);

		$page = (int) $this->getRequest()->get('page');
		$size = (int) $this->getRequest()->get('listSize');

		$session = $this->getSession($table);

		if ($page) {
			$session->page = $page;
		} else if (!isset($session->page)) {
			$session->page = 0;
		}

		if ($size) {
			$session->size = $size;
		} else if (!isset($session->size)) {
			$session->size = 1000;
		}

		// use for model
		$options = array_merge($options, array(
			'collection' => $table,
			'page' => $session->page,
			'size' => $session->size,
			'sort' => $sort,
			));

		$model = self::getModel($table, $options);

		// TODO: use ready pager/paginiation class (zend? joomla?) with auto print
		$params = array(
			'title' => $this->title,
		);

		$params = array_merge($options, $params, $this->getTableViewParams($filter_query), $this->createFilterToolbar($table, $sort));

		$ret = $this->renderView('table', $params);
		return $ret;
	}

	/**
	 * 
	 * @param string $table the table name
	 */
	protected function getSession($table) {
		$session = Yaf_session::getInstance();
		$session->start();

		if (!isset($session->$table)) {
			$session->$table = new stdClass();
		}
		return $session->$table;
	}

	/**
	 * Gets a variable from the request (POST) / session and sets it to the session if found
	 * @param Object $session the session object
	 * @param string $var the variable name
	 */
	protected function getVar($session, $var, $default = null) {
		$request = $this->getRequest();
		if (is_string($request->getPost($var))) {
			$session->$var = $request->getPost($var);
		} else if (!isset($session->$var)) {
			$session->$var = $default;
		}
		return $session->$var;
	}

}