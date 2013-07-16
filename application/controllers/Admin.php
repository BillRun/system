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
	}

	public function confirmAction() {
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$id = Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);

		$model = self::getModel($coll);
		$entity = $model->getItem($id);
		if ($type == 'remove') {
			if (!$model->isLast($entity)) {
				die("There's already a newer entity with this key");
			} else if (is_subclass_of($model, "TabledateModel") && !$model->startsInFuture($entity)) {
				die("Only future entities could be removed");
			}
		}

		$this->getView()->entity = $entity;
		$this->getView()->collectionName = $coll;
		$this->getView()->type = $type;
		$this->getView()->key = $entity[$model->search_key];
		$this->getView()->id = $id;
//		$this->getView()->component = $this->renderView('remove', array('entity' => $entity, 'collectionName' => $coll, 'type' => $type, 'key' => $entity[$model->search_key]));
	}

	/**
	 * save controller
	 * @return boolean
	 * @todo move to model
	 * @todo protect the from and to to be continuely
	 */
	public function removeAction() {
		$id = Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);

		$model = self::getModel($coll);

		$collection = Billrun_Factory::db()->getCollection($coll);
		if (!($collection instanceof Mongodloid_Collection)) {
			return false;
		}

		$params = array('_id' => new Mongodloid_Id($id));

		if ($type == 'remove') {
			$saveStatus = $model->remove($params);
		}

		// @TODO: need to load ajax view
		// for now just die with json
		die(json_encode($ret));
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
			$saveStatus = $model->save($params);
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
		die(json_encode($ret));
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
			$session->$table->size = 1000;
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
		if ($tpl == 'edit' || $tpl == 'confirm') {
			return parent::render($tpl, $parameters);
		}
		$tpl = 'index';
		//check with active menu we are on
		$parameters['active'] = $this->getRequest()->getActionName();
		$parameters['title'] = $this->title;
		return $this->getView()->render($tpl . ".phtml", $parameters);
	}

	public static function getModel($collection_name) {
		$model_name = ucfirst($collection_name) . "Model";
		if (class_exists($model_name)) {
			return new $model_name;
		} else {
			die("Error loading model");
		}
	}

}