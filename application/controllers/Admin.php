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
	protected $baseUrl = null;

	/**
	 * method to control and navigate the user to the right view
	 */
	public function init() {
		$this->baseUrl = $this->getRequest()->getBaseUri();
		Yaf_Loader::getInstance(APPLICATION_PATH . '/application/helpers')->registerLocalNamespace('Admin');
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
		if (!$this->allowed('read'))
			return false;
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
		if (!$this->allowed('read'))
			return false;
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$ids = Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);

		$model = self::getModel($coll);

		if ($type == 'remove' && !in_array($coll, array('lines', 'users'))) {
			$entity = $model->getItem($ids);
			$this->getView()->entity = $entity;
			$this->getView()->key = $entity[$model->search_key];
			if (!$model->isLast($entity)) {
				die("There's already a newer entity with this key");
			} else if (is_subclass_of($model, "TabledateModel") && !$model->startsInFuture($entity)) {
				die("Only future entities could be removed");
			}
		} else {
			$this->getView()->key = "the selected documents";
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
		if (!$this->allowed('write'))
			die(json_encode(null));
		$ids = explode(",", Billrun_Util::filter_var($this->getRequest()->get('ids'), FILTER_SANITIZE_STRING));
		if (!is_array($ids) || count($ids) == 0 || empty($ids)) {
			return;
		}
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);

		$model = self::getModel($coll);

		$collection = Billrun_Factory::db()->getCollection($coll);
		if (!($collection instanceof Mongodloid_Collection)) {
			return false;
		}

		$params = array();
		foreach ($ids as $id) {
			$params['_id']['$in'][] = new MongoId((string) $id);
		}

		// this is just insurance that the loop worked fine
		if (empty($params)) {
			return;
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
		if (!$this->allowed('write'))
			die(json_encode(null));
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

	public function logDetailsAction() {
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$stamp = Billrun_Util::filter_var($this->getRequest()->get('stamp'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);

		$model = self::getModel($coll);
		$entity = $model->getDataByStamp(array("stamp" => $stamp));

		// passing values into the view
		$this->getView()->entity = $entity;
		$this->getView()->protectedKeys = $model->getProtectedKeys($entity, $type);
		$this->getView()->collectionName = $coll;
		$this->getView()->type = $type;
	}

	public function csvExportAction() {
		$session = $this->getSession('lines');

		if (!empty($session->query)) {

			$options = array(
				'collection' => 'lines',
				'sort' => array('urt' => 1),
			);
			$model = self::getModel('lines', $options);

			$skip = Billrun_Factory::config()->getConfigValue('admin_panel.csv_export.skip', 0);
			$size = Billrun_Factory::config()->getConfigValue('admin_panel.csv_export.size', 10000);
			$params = array_merge($this->getTableViewParams($session->query, $skip, $size), $this->createFilterToolbar('lines'));
			Admin_Lines::getCsvFile($params);
		} else {
			return false;
		}
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
		if (!$this->allowed('read'))
			return false;
		$this->forward("tabledate", array('table' => 'plans'));
		return false;
	}

	/**
	 * rates controller of admin
	 */
	public function ratesAction() {
		if (!$this->allowed('read'))
			return false;
		$session = $this->getSession("rates");
		$show_prefix = $this->getSetVar($session, 'showprefix', 'showprefix', 0);
		$this->forward("tabledate", array('table' => 'rates', 'showprefix' => $show_prefix));
		return false;
	}

	public function tabledateAction() {
		$showprefix_param = $this->_request->getParam("showprefix");
		$showprefix = $showprefix_param == 'on' && !$showprefix_param == '0' ? 1 : 0;
		$table = $this->_request->getParam("table");

//		$sort = array('urt' => -1);
		$sort = $this->applySort($table);
		$options = array(
			'collection' => $table,
			'sort' => $sort,
			'showprefix' => $showprefix,
		);

		// set the model
		self::getModel($table, $options);
		$query = $this->applyFilters($table);

		$this->getView()->component = $this->buildComponent($table, $query);
	}

	public function loginAction() {
		$params = array_merge($this->getRequest()->getParams(), $this->getRequest()->getPost());
		$db = Billrun_Factory::db()->usersCollection()->getMongoCollection();

		$username = $this->getRequest()->get('username');
		$password = $this->getRequest()->get('password');

		if ($username != '') {
			$adapter = new Zend_Auth_Adapter_MongoDb(
					$db, 'username', 'password'
			);

			$adapter->setIdentity($username);
			$adapter->setCredential($password);

			$result = $this->auth()->authenticate($adapter);

			if ($result->isValid()) {
				$ret_action = $this->getRequest()->get('ret_action');
				if ($ret_action) {
					$this->redirect($ret_action);
					return false;
				}
				return true;
			}
		}

		$this->getView()->component = $this->getLoginForm($params);
	}

	protected function getLoginForm($params) {
		$this->title = "Login";

		// TODO: use ready pager/paginiation class (zend? joomla?) with auto print
		$params = array_merge(array(
			'title' => $this->title,
				), $params);
//		$params = array_merge($options, $params, $this->getTableViewParams($filter_query), $this->createFilterToolbar($table));

		$ret = $this->renderView('login', $params);
		return $ret;
	}

	protected function auth() {
		return Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_Yaf());
	}

	protected function allowed($permission) {
		$action = $this->getRequest()->getActionName();
		$auth = $this->auth();
		if ($action != 'login') {
			if (!$auth->hasIdentity()) {
				$this->forward('login', array('ret_action' => $action));
				return false;
			}
			$user = Billrun_Factory::user($auth->getIdentity());
			if (!$user->valid() || !$user->allowed($permission)) {
				$this->forward('error');
				return false;
			}
		}
		return true;
	}

	/**
	 * lines controller of admin
	 */
	public function linesAction() {
		if (!$this->allowed('read'))
			return false;

		$table = 'lines';
		$sort = $this->applySort($table);
		$options = array(
			'collection' => $table,
			'sort' => $sort,
		);

		self::getModel($table, $options);
		$query = $this->applyFilters($table);

		$session = $this->getSession($table);
		$this->getSetVar($session, $query, 'query', $query);

		$this->getView()->component = $this->buildComponent('lines', $query);
	}

	protected function errorAction() {
		$this->getView()->component = $this->renderView('error');
	}

	/**
	 * events controller of admin
	 */
	public function eventsAction() {
		if (!$this->allowed('read'))
			return false;
		$table = "events";
//		$sort = array('creation_time' => -1);
		$sort = $this->applySort($table);
		$options = array(
			'collection' => $table,
			'sort' => $sort,
		);

		$model = self::getModel($table, $options);
		$query = $this->applyFilters($table);

		$this->getView()->component = $this->buildComponent($table, $query);
	}

	/**
	 * log controller of admin
	 */
	public function logAction() {
		if (!$this->allowed('read'))
			return false;
		$table = "log";
//		$sort = array('received_time' => -1);
		$sort = $this->applySort($table);
		$options = array(
			'collection' => $table,
			'sort' => $sort,
		);

		$model = self::getModel($table, $options);
		$query = $this->applyFilters($table);

		$this->getView()->component = $this->buildComponent($table, $query);
	}

	/**
	 * users controller of admin
	 */
	public function usersAction() {
		if (!$this->allowed('admin'))
			return false;
		$table = "users";
		$options = array(
			'collection' => $table,
		);

		$model = self::getModel($table, $options);
		$query = $this->applyFilters($table);

		$this->getView()->component = $this->buildComponent($table, $query);
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
	protected function getTableViewParams($filter_query = array(), $skip = null, $size = null) {
		if (isset($skip) && !empty($size)) {
			$data = $this->model->getData($filter_query, $skip, $size);
		} else {
			$data = $this->model->getData($filter_query);
		}
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
			'offset' => $this->model->offset(),
		);

		return $params;
	}

	protected function createFilterToolbar() {
		$params['filter_fields'] = $this->model->getFilterFields();
		$params['filter_fields_order'] = $this->model->getFilterFieldsOrder();
		$params['sort_fields'] = $this->model->getSortFields();
		$params['extra_columns'] = $this->model->getExtraColumns();

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
		if ($tpl == 'edit' || $tpl == 'confirm' || $tpl == 'logdetails') {
			return parent::render($tpl, $parameters);
		}
		$tpl = 'index';
		//check with active menu we are on
		$parameters['active'] = $this->getRequest()->getActionName();
		if ($this->getRequest()->getActionName() == "index") {
			$parameters['active'] = "";
		}
		if ($this->getRequest()->getActionName() == "tabledate") {
			$parameters['active'] = $this->_request->getParam("table");
		}

		$parameters['title'] = $this->title;
		$parameters['baseUrl'] = $this->baseUrl;
		return $this->getView()->render($tpl . ".phtml", $parameters);
	}

	public function getModel($collection_name, $options = array()) {
		$session = $this->getSession($collection_name);
		$options['page'] = $this->getSetVar($session, "page", "page", 1);
		$options['size'] = $this->getSetVar($session, "listSize", "size", 1000);
		$options['extra_columns'] = $this->getSetVar($session, "extra_columns", "extra_columns", array());

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

	protected function buildComponent($table, $filter_query, $options = array()) {
		$this->title = ucfirst($table);

		// TODO: use ready pager/paginiation class (zend? joomla?) with auto print
		$params = array(
			'title' => $this->title,
			'session' => $this->getSession($table),
		);
		$params = array_merge($options, $params, $this->getTableViewParams($filter_query), $this->createFilterToolbar($table));

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
	 * Gets a variable from the request / session and sets it to the session if found
	 * @param Object $session the session object
	 * @param string $source_name the variable name in the request
	 * @param type $target_name the variable name in the session
	 * @param type $default the default value for the variable
	 * @return type
	 */
	protected function getSetVar($session, $source_name, $target_name = null, $default = null) {
		if (is_null($target_name)) {
			$target_name = $source_name;
		}
		$request = $this->getRequest();
		$new_search = $request->get("new_search") == "1";
		if (is_array($source_name)) {
			$key = Billrun_Util::generateArrayStamp($source_name);
		} else {
			$key = $source_name;
		}
		$var = $request->get($key);
		if ($new_search) {
			if (is_string($var) || is_array($var)) {
				$session->$target_name = $var;
			} else {
				$session->$target_name = $default;
			}
		} else if (is_string($var) || is_array($var)) {
			$session->$target_name = $var;
		} else if (!isset($session->$target_name)) {
			$session->$target_name = $default;
		}
		return $session->$target_name;
	}

	protected function applyFilters($table) {
		$model = $this->model;
		$session = $this->getSession($table);
		$filter_fields = $model->getFilterFields();
		$query = array();
		if ($filter = $this->getManualFilters($table)) {
			$query['$and'][] = $filter;
		}
		foreach ($filter_fields as $filter_name => $filter_field) {
			$value = $this->getSetVar($session, $filter_field['key'], $filter_field['key'], $filter_field['default']);
			if (!empty($value) && $filter_field['db_key'] != 'nofilter' && $filter = $model->applyFilter($filter_field, $value)) {
				$query['$and'][] = $filter;
			}
		}
		return $query;
	}

	protected function applySort($table) {
		$session = $this->getSession($table);
		$sort_by = $this->getSetVar($session, 'sort_by', 'sort_by', '_id');
		$order = $this->getSetVar($session, 'order', 'order', 'asc') == 'asc' ? 1 : -1;
		$sort = array($sort_by => $order);
		return $sort;
	}

	public function getManualFilters($table) {
		$query = false;
		$session = $this->getSession($table);
		$keys = $this->getSetVar($session, 'manual_key', 'manual_key');
		$types = Admin_Lines::getOptions();
		$operators = $this->getSetVar($session, 'manual_operator', 'manual_operator');
		$values = $this->getSetVar($session, 'manual_value', 'manual_value');
		for ($i = 0; $i < count($keys); $i++) {
			if ($keys[$i] == '' || $values[$i] == '') {
				continue;
			}
			switch ($types[$keys[$i]]) {
				case 'number':
					$values[$i] = floatval($values[$i]);
					break;
				case 'date':
					if (Zend_Date::isDate($values[$i], 'yyyy-MM-dd hh:mm:ss')) {
						$values[$i] = new MongoDate((new Zend_Date($values[$i], null, new Zend_Locale('he_IL')))->getTimestamp());
					} else {
						continue 2;
					}
				default:
					break;
			}
			// TODO: decoupling to config of fields
			switch ($operators[$i]) {
				case 'starts_with':
					$operators[$i] = '$regex';
					$values[$i] = "^$values[$i]";
					break;
				case 'ends_with':
					$operators[$i] = '$regex';
					$values[$i] = "$values[$i]$";
					break;
				case 'like':
					$operators[$i] = '$regex';
					$values[$i] = "$values[$i]";
					break;
				case 'lt':
					$operators[$i] = '$lt';
					break;
				case 'lte':
					$operators[$i] = '$lte';
					break;
				case 'gt':
					$operators[$i] = '$gt';
					break;
				case 'gte':
					$operators[$i] = '$gte';
					break;
				case 'ne':
					$operators[$i] = '$ne';
					break;
				case 'equals':
					$operators[$i] = '$in';
					$values[$i] = array($values[$i]);
					break;
				default:
					break;
			}
			$query[$keys[$i]][$operators[$i]] = $values[$i];
		}
		return $query;
	}

}
