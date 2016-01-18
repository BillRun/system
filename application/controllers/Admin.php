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
	protected $cssPaths = array();
	protected $jsPaths = array();
	protected $commit;
	
	/**
	 * method to control and navigate the user to the right view
	 */
	public function init() {
		if (APPLICATION_ENV === 'prod') {
			// TODO: set the branch through config
			$branch = 'production';
			if (file_exists(APPLICATION_PATH . '/.git/refs/heads/' . $branch)) {
				$this->commit = rtrim(file_get_contents(APPLICATION_PATH . '/.git/refs/heads/' . $branch), "\n");
			} else {
				$this->commit = md5(date('ymd'));
			}
		} else {
			$this->commit = md5(time());
		}

		$this->baseUrl = $this->getRequest()->getBaseUri();
		$this->addCss($this->baseUrl . '/css/bootstrap.min.css');
		$this->addCss($this->baseUrl . '/css/bootstrap-datetimepicker.min.css');
		$this->addCss($this->baseUrl . '/css/bootstrap-switch.css');
		$this->addCss($this->baseUrl . '/css/bootstrap-multiselect.css');
		$this->addCss($this->baseUrl . '/css/jsoneditor.css');
		$this->addCss($this->baseUrl . '/css/main.css');
		$this->addJs($this->baseUrl . '/js/vendor/bootstrap.min.js');
		$this->addJs($this->baseUrl . '/js/plugins.js');
		$this->addJs($this->baseUrl . '/js/moment.js');
		$this->addJs($this->baseUrl . '/js/moment-with-locales.min.js');
		$this->addJs($this->baseUrl . '/js/bootstrap-datetimepicker.min.js');
		$this->addJs($this->baseUrl . '/js/jquery.jsoneditor.js');
		$this->addJs($this->baseUrl . '/js/bootstrap-multiselect.js');
		$this->addJs($this->baseUrl . '/js/bootstrap-switch.js');
		$this->addJs($this->baseUrl . '/js/jquery.csv-0.71.min.js');
		$this->addJs($this->baseUrl . '/js/main.js');
		Yaf_Loader::getInstance(APPLICATION_PATH . '/application/helpers')->registerLocalNamespace('Admin');
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/view/admin_panel.ini');
	}

	protected function addCss($path) {
		$this->cssPaths[] = $path;
	}

	protected function addJs($path) {
		$this->jsPaths[] = $path;
	}

	protected function fetchJsFiles() {
		$ret = '';
		foreach ($this->jsPaths as $jsPath) {
			$ret.='<script src="' . $jsPath . (Billrun_Factory::config()->isProd() ? '?' . $this->commit : '') . '"></script>' . PHP_EOL;
		}
		return $ret;
	}

	protected function fetchCssFiles() {
		$ret = '';
		foreach ($this->cssPaths as $cssPath) {
			$ret.='<link rel="stylesheet" href="' . $cssPath . '?' . $this->commit . '">' . PHP_EOL;
		}
		return $ret;
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

	public function getEntityAction() {
		if (!$this->allowed('read'))
			return false;
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$id = Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);
		$response = new Yaf_Response_Http();

		$model = self::initModel($coll);
		if ($type == 'new') {
			$entity = $model->getEmptyItem();
		} else {
			$entity = $model->getItem($id);
			if (!$entity) {
				$response->setBody(json_encode(array('error' => 'Could not find entity')));
				$response->response();
				return false;
			}
			$entity = $entity->getRawData();
			foreach ($model->getHiddenKeys($entity, $type) as $key) {
				if ($key !== '_id') unset($entity[$key]);
			}
		}
		$response->setBody(json_encode(array('authorized_write' => AdminController::authorized('write'), 'entity' => $entity)));
		$response->response();
		return false;
	}
	
	public function getCollectionItemsAction() {
		if (!$this->allowed('read'))
			return false;
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$size = $this->getRequest()->get('size');
		$response = new Yaf_Response_Http();
		$session = $this->getSession($coll);
		$filter = @json_decode($this->getRequest()->get('filter'));
		if ($filter) {
			foreach($filter as $key => $val) {
				$session->$key = $val;
				if (is_array($val)) {
					$t = array();
					foreach($val as $v) {
						$t[$v] = $v;
					}
					$session->$key = $t;
				}
			}
		}
		$show_prefix = $this->getSetVar($session, 'showprefix', 'showprefix', 0);
		$sort = $this->applySort($coll);
		$options = array(
			'collection' => $coll,
			'sort' => $sort,
			'showprefix' => $show_prefix
		);
		// set the model
		self::initModel($coll, $options);
		$this->model->setSize($size);
		$query = $this->applyFilters($coll);
		$basic_params = array(
			'title' => $this->title,
			'active' => $coll,
			'session' => $session,
		);
		$params = array_merge($options, $basic_params, $this->getTableViewParams($query), $this->createFilterToolbar($coll));
		$items = array();
		foreach ($params['data'] as $item) {
			$i = array();
			foreach ($params['columns'] as $col => $v) {
				$i[$col] = $item->get($col);
			}
			$i['_id'] = strval($item->getId());
			$items[] = $i;
		}
		$params['data'] = $items;
		$response->setBody(json_encode(array(
			'items' => $params,
			'pager' => $this->model->getPager(),
			'authorized_write' => AdminController::authorized('write'),
			'filter_fields' => $this->model->getFilterFields()
		)));
		$response->response();
		return false;
	}

	public function getAvailablePlansAction() {
		if (!$this->allowed('read'))
			return false;
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);
		$planModel = new PlansModel();
		$names = $planModel->getData(array('type' => $type));
		$availablePlans = array();
		foreach($names as $name) {
			$availablePlans[$name['name']] = $name['name'];
		}
		$response = new Yaf_Response_Http();
		$response->setBody(json_encode($availablePlans));
		$response->response();
		return false;
	}

	public function getAvailableServiceProvidersAction() {
		if (!$this->allowed('read'))
			return false;
		if ($this->getRequest()->get('full_objects')) {
			$serviceProvidersModel = new ServiceprovidersModel();
			$collection = array();
			foreach ($serviceProvidersModel->getData() as $serviceProvider) {
				$raw = $serviceProvider->getRawData();
				$raw['_id'] = strval($serviceProvider->getId());
				$collection[] = $raw;
			}
		} else {
			$collection = Billrun_Factory::db()->serviceprovidersCollection()->distinct('name');
		}
		$response = new Yaf_Response_Http();
		$response->setBody(json_encode($collection));
		$response->response();
		return false;
	}

	public function getViewINIAction() {
		if (!$this->allowed('read'))
			return false;
		$response = new Yaf_Response_Http();
		$conf = Billrun_Config::getInstance(new Yaf_Config_Ini(APPLICATION_PATH . '/conf/ui.ini'));
		$response->setBody(json_encode($conf->toArray()));
		$response->response();
		return false;
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

		$model = self::initModel($coll);
		if ($type == 'new') {
			$entity = $model->getEmptyItem();
		} else {
			$entity = $model->getItem($id);
		}
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
		if (!$this->allowed('write'))
			return false;
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$ids = Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);

		$model = self::initModel($coll);

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
		$ids = $this->getRequest()->get('ids');
		if (!is_array($ids)) {
			Billrun_Util::filter_var($ids, FILTER_SANITIZE_STRING);
			$ids = explode(",", $this->getRequest()->get('ids'));
		}
		if (!is_array($ids) || count($ids) == 0 || empty($ids)) {
			return;
		}
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);

		$model = self::initModel($coll);

		if ($coll == 'users' && in_array(strval(Billrun_Factory::user()->getMongoId()), $ids)) { // user is not allowed to remove oneselfs
			die(json_encode("Can't remove oneself"));
		}

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
		$v = new Billrun_Validator() ;
		// $v->RequiredValidator("sasa","");
		// $v->IntegerValidator("sasa1111111","111.1");
		// $v->NumberValidator("sasa","1212");
		// $v->LengthValidator("sasa111","1212",array("min"=>10));

		//print_R($v->getErrors());

		if (!$this->allowed('write'))
			return $this->responseError("Permission denied, make sure you have write permission");

		$flatData = $this->getRequest()->get('data');
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);
		$id = Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$dup_rates = $this->getRequest()->get('duplicate_rates');
		$batch_no = $this->getRequest()->get('batch_no') ? $this->getRequest()->get('batch_no') : false;
		if ($batch_no) {
			$range = json_decode($this->getRequest()->get('range'));
		}
		$duplicate_rates = ($dup_rates == 'true') ? true : false;
		$model = self::initModel($coll);

		$collection = Billrun_Factory::db()->getCollection($coll);
		if (!($collection instanceof Mongodloid_Collection)) {
			 return $this->responseError($collection . " collection not exists");
		}

		$data = @json_decode($flatData, true);

		if (empty($data) || ($type != 'new' && empty($id)) || empty($coll)) {
				return $this->responseError("Data param is empty");
		}

		if ($id) {
			$params = array_merge($data, array('_id' => new MongoId($id)));
		} else {
			$params = $data;
		}
		if ($duplicate_rates) {
			$params = array_merge($params, array('duplicate_rates' => $duplicate_rates));
		}
		/*
		$v->validate($params,$coll) ;
		if(!$v->isValid()) {	   	
			return $this->responseError($v->getErrors());
		}
		*/
		if ($type == 'update') {
			if (strtolower($coll) === 'cards') {
				//$this->getRequest()->set('update', $this->getRequest()->get('data'));
				$this->forward("Api", "Cards", $this->getRequest());
			} else {
				$saveStatus = $model->update($params);
			}
		} else if ($type == 'close_and_new') {
		  	$saveStatus = $model->closeAndNew($params);
		} else if (in_array($type, array('duplicate', 'new'))) {
			$saveStatus = $model->duplicate($params);
		}

//		$ret = array(
//			'status' => $saveStatus,
//			'closeLine' => $entity->getRawData(),
//			'newLine' => $newEntity->getRawData(),
//		);
		// @TODO: need to load ajax view
		// for now just die with json
		return $this->responseSuccess(array("data" => $params , "status"=>true ));
	}


	public function logDetailsAction() {
		$coll = Billrun_Util::filter_var($this->getRequest()->get('coll'), FILTER_SANITIZE_STRING);
		$stamp = Billrun_Util::filter_var($this->getRequest()->get('stamp'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);

		$model = self::initModel($coll);
		$entity = $model->getDataByStamp(array("stamp" => $stamp));

		// passing values into the view
		$this->getView()->entity = $entity;
		$this->getView()->protectedKeys = $model->getProtectedKeys($entity, $type);
		$this->getView()->collectionName = $coll;
		$this->getView()->type = $type;
	}

	public function csvExportAction() {
		if (!$this->allowed('read'))
			return false;

		$collectionName = $this->getRequest()->get("collection");
		$session = $this->getSession($collectionName);

		if (!empty($session->query)) {

			$options = array(
				'collection' => $collectionName,
				'sort' => $this->applySort($collectionName),
			);

			// init model
			self::initModel($collectionName, $options);

			$skip = intval(Billrun_Factory::config()->getConfigValue('admin_panel.csv_export.skip', 0));
			$size = intval(Billrun_Factory::config()->getConfigValue('admin_panel.csv_export.size', 10000));
			$params = array_merge($this->getTableViewParams($session->query, $skip, $size), $this->createFilterToolbar('lines'));
			$this->model->exportCsvFile($params);
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
	public function chargingplansAction() {
		if (!$this->allowed('read'))
			return false;
		$this->_request->setParam('plan_type', 'charging');
		$this->forward('tabledate', array('table' => 'plans'));
		return false;
	}
	public function customerplansAction() {
		if (!$this->allowed('read'))
			return false;
		$this->_request->setParam('plan_type', 'customer');
		$this->forward('tabledate', array('table' => 'plans'));
		return false;
	}

	public function plansAction() {
		if (!$this->allowed('read'))
			return false;
		$this->forward("tabledate", array('table' => 'plans'));
		return false;
	}

	public function subscribersAction() {
		if (!$this->allowed('read'))
			return false;
		$this->forward("tabledate", array('table' => 'subscribers'));
		return false;
	}

	public function subscribersAutoRenewServicesAction() {
		if (!$this->allowed('read'))
			return false;
		$this->forward("tabledate", array('table' => 'subscribers_auto_renew_services'));
		return false;
	}

	public function Action() {
		if (!$this->allowed('read'))
			return false;
		$table = "cards";
//		$sort = array('received_time' => -1);
		$sort = $this->applySort($table);
		$options = array(
			'collection' => $table,
			'sort' => $sort,
		);

		self::initModel($table, $options);
		$query = $this->applyFilters($table);

		// this use for export
		$this->getSetVar($this->getSession($table), $query, 'query', $query);

		$this->getView()->component = $this->buildTableComponent($table, $query);
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
		if ($table === "plans") $options['plan_type'] = $this->_request->getParam('plan_type');
		// set the model
		self::initModel($table, $options);
		$query = $this->applyFilters($table);

		$this->getView()->component = $this->buildTableComponent($table, $query);
	}

	public function loginAction() {
		if (Billrun_Factory::user() !== FALSE) {
			// if already logged-in redirect to admin homepage
			$this->forceRedirect($this->baseUrl . '/admin/');
		}
		$params = array_merge($this->getRequest()->getParams(), $this->getRequest()->getPost());
		$db = Billrun_Factory::db()->usersCollection()->getMongoCollection();

		$username = $this->getRequest()->get('username');
		$password = $this->getRequest()->get('password');

		if ($username != '' && !is_null($password)) {
			$adapter = new Zend_Auth_Adapter_MongoDb(
				$db, 'username', 'password'
			);

			$adapter->setIdentity($username);
			$adapter->setCredential($password);

			$result = Billrun_Factory::auth()->authenticate($adapter);

			if ($result->isValid()) {
				$ip = $this->getRequest()->getServer('REMOTE_ADDR', 'Unknown IP');
				Billrun_Factory::log('User ' . $username . ' logged in to admin panel from IP: ' . $ip, Zend_log::INFO);
				// TODO: stringify to url encoding (A-Z,a-z,0-9)
				$ret_action = $this->getRequest()->get('ret_action');
//				if (empty($ret_action)) {
//					$ret_action = 'admin';
//				}
				$this->forceRedirect($this->baseUrl . $ret_action);
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

	/**
	 * method to check if user is authorize to resource
	 *
	 * @param string $permission the permission require authorization
	 *
	 * @return boolean true if have access, else false
	 *
	 * @todo: refactoring to core
	 */
	static public function authorized($permission, $page = null) {
		$user = Billrun_Factory::user();
		if (!$user || !$user->valid() || !$user->allowed($permission, $page)) {
			return false;
		}

		return true;
	}

	static public function showInMenu($page) {
		return Billrun_Config::getInstance()->getConfigValue('show_in_menu.' . $page, true);
	}

	/**
	 * method to check if user is allowed to access page, if not redirect or show error message
	 *
	 * @param string $permission the permission required to the page
	 *
	 * @return boolean true if have access, else false
	 *
	 */
	protected function allowed($permission) {
		$action = $this->getRequest()->getActionName();
		if ($action != 'index') {
			$page = $action;
		} else {
			$page = null;
		}
		if (self::authorized($permission, $page)) {
			return true;
		}

		if (Billrun_Factory::user()) {
			$this->forward('error');
			return false;
		}

		$this->forward('login', array('ret_action' => $action));
		return false;
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

		self::initModel($table, $options);
		$query = $this->applyFilters($table);

		$session = $this->getSession($table);
		// this use for export
		$this->getSetVar($session, $query, 'query', $query);

		$this->getView()->component = $this->buildTableComponent('lines', $query);
	}

	public function queueAction() {
		if (!$this->allowed('read'))
			return false;

		$table = 'queue';
		$sort = $this->applySort($table);
		$options = array(
			'collection' => $table,
			'sort' => $sort,
		);

		self::initModel($table, $options);
		$query = $this->applyFilters($table);

		$session = $this->getSession($table);
		// this use for export
		$this->getSetVar($session, $query, 'query', $query);

		$this->getView()->component = $this->buildTableComponent('queue', $query);
	}

	public function cardsAction() {
		if (!$this->allowed('read'))
			return false;
		$table = "cards";
		$sort = $this->applySort($table);
		$options = array(
			'collection' => $table,
			'sort' => $sort,
		);

		self::initModel($table, $options);
		$query = $this->applyFilters($table);
		// this use for export

		$this->getSetVar($this->getSession($table), $query, 'query', $query);
		$this->getView()->component = $this->buildTableComponent($table, $query);
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

		self::initModel($table, $options);
		$query = $this->applyFilters($table);

		$this->getView()->component = $this->buildTableComponent($table, $query);
	}

	/**
	 * log controller of admin
	 */
	public function logsAction() {
		if (!$this->allowed('read'))
			return false;
		$table = "log";
//		$sort = array('received_time' => -1);
		$sort = $this->applySort($table);
		$options = array(
			'collection' => $table,
			'sort' => $sort,
		);

		$model = self::initModel($table, $options);
		$query = $this->applyFilters($table);

		$this->getView()->component = $this->buildTableComponent($table, $query);
	}

	/**
	 * log controller of admin
	 */
	public function balancesAction() {
		if (!$this->allowed('read'))
			return false;
		$table = "balances";
//		$sort = array('received_time' => -1);
		$sort = $this->applySort($table);
		$options = array(
			'collection' => $table,
			'sort' => $sort,
		);

		self::initModel($table, $options);
		$query = $this->applyFilters($table);

		// this use for export
		$this->getSetVar($this->getSession($table), $query, 'query', $query);

		$this->getView()->component = $this->buildTableComponent($table, $query);
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

		self::initModel($table, $options);
		$query = $this->applyFilters($table);

		$this->getView()->component = $this->buildTableComponent($table, $query);
	}

	/**
	 * config controller of admin
	 */
	public function operationsAction() {
		if (!$this->allowed('operations'))
			return false;

		$this->getView()->component = $this->renderView('operations');
	}

	/**
	 * config controller of admin
	 */
	public function configAction() {
		if (!$this->allowed('admin'))
			return false;

		$model = $this->initModel('config');
		$configData = $model->getConfig();

		$viewData = array(
			'data' => $configData,
			'options' => $model->getOptions(),
		);
		$this->getView()->component = $this->renderView('config', $viewData);
	}

	/**
	 * config controller of admin
	 */
	public function configsaveAction() {
		if (!$this->allowed('admin'))
			return false;
		// get model cofig
		$model = $this->initModel('config');
		$data = $this->getRequest()->getRequest();
		$model->setConfig($data);
		$this->forceRedirect('/admin/config');
	}

	protected function forceRedirect($uri) {
		if (empty($uri)) {
			$uri = '/';
		}
		header('Location: ' . $uri);
		exit();
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
			$this->model->setSize($size);
			$this->model->setPage($skip);
		}
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
			'offset' => $this->model->offset(),
		);

		return $params;
	}

	protected function createFilterToolbar() {
		return array(
			'filter_fields' => $this->model->getFilterFields(),
			'filter_fields_order' => $this->model->getFilterFieldsOrder(),
			'sort_fields' => $this->model->getSortElements(),
			'extra_columns' => $this->model->getExtraColumns(),
		);
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
		if ($tpl == 'edit' || $tpl == 'confirm' || $tpl == 'logdetails' || $tpl == 'wholesaleajax') {
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
			if ($parameters['active'] === 'plans') {
				$parameters['active'] = $this->_request->getParam('plan_type') . $parameters['active'];
			}
		}

		$parameters['title'] = $this->title;
		$parameters['baseUrl'] = $this->baseUrl;

		$parameters['css'] = $this->fetchCssFiles();
		$parameters['js'] = $this->fetchJsFiles();

		return $this->getView()->render($tpl . ".phtml", $parameters);
	}

	public function initModel($collection_name, $options = array()) {
		$session = $this->getSession($collection_name);
		$options['page'] = $this->getSetVar($session, "page", "page", 1);
		$options['size'] = $this->getSetVar($session, "listSize", "size", Billrun_Factory::config()->getConfigValue('admin_panel.lines.limit', 100));
		$options['extra_columns'] = $this->getSetVar($session, "extra_columns", "extra_columns", array());

		if (is_null($this->model)) {
			$model_name = ucfirst(str_replace('_', '', $collection_name)) . "Model";
			if (class_exists($model_name)) {
				$this->model = new $model_name($options);
			} else {
				die("Error loading model");
			}
		}
		if ($collection_name === "plans" && $options['plan_type']) $this->model->type = $options['plan_type'];
		return $this->model;
	}

	protected function buildTableComponent($table, $filter_query, $options = array()) {
		$this->title = str_replace('_', ' ', ucfirst($table));
		if ($table === 'plans') {
			$this->title = ucfirst($this->_request->getParam('plan_type')) . ' ' . $this->title;
		}
		// TODO: use ready pager/paginiation class (zend? joomla?) with auto print
		$basic_params = array(
			'title' => $this->title,
			'active' => $table,
			'session' => $this->getSession($table),
		);
		$params = array_merge($options, $basic_params, $this->getTableViewParams($filter_query), $this->createFilterToolbar($table));

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

	protected function applyFilters($table, $session = false) {
		$model = $this->model;
		if (!$session) {
			$session = $this->getSession($table);
		}
		$filter_fields = $model->getFilterFields();
		$query = array();
		if (($filter = $this->getManualFilters($table, $session))) {
			$query['$and'][] = $filter;
		}
		foreach ($filter_fields as $filter_name => $filter_field) {
			$value = $this->getSetVar($session, $filter_field['key'], $filter_field['key'], $filter_field['default']);
			if ((!empty($value) || $value === 0 || $value === "0") &&
				is_array($filter_field) && isset($filter_field['db_key']) && 
				$filter_field['db_key'] != 'nofilter' &&
				($filter = $model->applyFilter($filter_field, $value))) {
					$query['$and'][] = $filter;
			}
		}
		if ($table === "plans") {
			$query['$and'][] = array('type' => $this->_request->getParam('plan_type'));
		}
		return $query;
	}

	protected function applySort($table) {
		$session = $this->getSession($table);
		$sort_by = $this->getSetVar($session, 'sort_by', 'sort_by');
		if ($sort_by) {
			$order = $this->getSetVar($session, 'order', 'order', 'asc') == 'asc' ? 1 : -1;
			$sort = array($sort_by => $order);
		} else {
			if ($table === "subscribers") {
				$sort = array('from' => -1);
			} else if ($table === "lines") {
				$sort = array('urt' => -1);
			}else {
				$sort = array();
			}
		}
		return $sort;
	}

	/**
	 * Return array of advanced options based on the current model used.
	 * @return array of advanced options, false if no mode identified.
	 */
	protected function getAdvancedOptionsPerModel() {
		if ($this->model instanceof LinesModel) {
			return Admin_Lines::getOptions();
		} else if ($this->model instanceof BalancesModel) {
			// TODO: make refactoring of the advanced options for each page (lines, balances, etc)
			return array(
				$keys = array(array(
					'type' => 'number',
					'display' => 'usage',
				)
			));
		} else if ($this->model instanceof EventsModel) {
			return array(
				$keys[0] => array(
					'type' => 'text',
				)
			);
		}

		// If model is unidentified return false;
		return false;
	}

	/**
	 * Translate the value by the type option.
	 * @param string $option - Type option to translate by.
	 * @param string $inputValue - Value to translate.
	 * @return the translated value.
	 */
	protected function translateValueByType($option, $inputValue) {
		// TODO: Change this switch case to OOP classes.
		switch ($option) {
			case 'number':
				$returnValue = floatval($inputValue);
			case 'date':
				// TODO: If the date is not in this format, should report error?
				if (Zend_Date::isDate($inputValue, 'yyyy-MM-dd hh:mm:ss')) {
					$returnValue = new MongoDate((new Zend_Date($inputValue, null, new Zend_Locale('he_IL')))->getTimestamp());
				} else {
					return false;
				}
			default:
				break;
		}

		return $returnValue;
	}

	/**
	 * Translate the value by the case option.
	 * @param string $option - Case option to translate by.
	 * @param string $inputValue - Value to translate.
	 * @return the translated value.
	 */
	protected function translateValueByCase($option, $inputValue) {
		return Admin_Table::convertValueByCaseType($inputValue, $option);
	}

	/**
	 * Convert value to set for the correct mongo type.
	 * @param string $option - Value type.
	 * @param string $inputValue - The value to be set.
	 * @return The value to set in the correct mongo type, null if no convertion found,
	 * false if invalid.
	 */
	protected function getValueForOption($option, $inputValue) {
		$returnValue = $this->translateValueByType($option['type'], $inputValue);
		if($returnValue === false) {
			return false;
		}

		if (isset($option['case'])) {
			$returnValue = $this->translateValueByCase($option['case'], $returnValue);
		}

		return $returnValue;
	}

	/**
	 * Return a pair of oprator and value in the mongo format based on user string
	 * input.
	 * @param string $operator - Readable string operator, not mongo format.
	 * @param string $value - The value to be coupled with the operator.
	 * @return pair - Operator as key and value as value.
	 */
	protected function getOperatorValuePair($operator, $value) {
		$translator = Admin_MongoOperatorTranslators_Manager::getUpdater($operator);
		// No translator found.
		if($translator != null) {
			// TODO: decoupling to config of fields
			return $translator->translate($value);
		}

		// If no translator found return the input parameters.
		return array($operator => $value);
	}

	/**
	 * Set the manual filter if the filter is a db ref.
	 * @param type $inputValue - Value for the filter.
	 * @param type $inputOperator - Operator for the filter.
	 * @return pair - Operator as key and value as value.
	 */
	protected function setManualFilterForDbref($inputValue, $inputOperator) {
		$collection = Billrun_Factory::db()->{$advancedOptions[$key]['collection'] . "Collection"}();
		$pre_query = null;
		$pre_query[$advancedOptions[$key]['collection_key']][$operator] = $inputValue;
		$cursor = $collection->query($pre_query);
		$value = array();
		foreach ($cursor as $entity) {
			$value[] = $collection->createRefByEntity($entity);
		}

		return array('$in' => $value);
	}

	/**
	 * Set the naual filter for a key to the query.
	 * @param array $query - Query to set the filter to.
	 * @param type $key - Key for the filter.
	 * @param type $inputValue - Value for the filter.
	 * @param type $operator - Operator for filter.
	 * @param array $advancedOptions - Array of advanced options for this action
	 */
	protected function setManualFilterForKey($query, $key, $inputValue, $operator, $advancedOptions) {
			$convertedValue = $this->getValueForOption($advancedOptions[$key], $inputValue);
			if($convertedValue === false) {
				return;
			}

			$value = $convertedValue;

			list($operator, $value) = each($this->getOperatorValuePair($operator, $convertedValue));

			// Handle a db ref option.
			if ($advancedOptions[$key]['type'] == 'dbref') {
				list($operator, $value) = each($this->setManualFilterForDbref($value, $operator));
			}

		$query[$key][$operator] = $value;
	}

	public function getManualFilters($table, $session = false) {
		$advanced_options = $this->getAdvancedOptionsPerModel();
		if ($advanced_options === false) {
			Billrun_Factory::log("No options found for current model.", Zend_Log::DEBUG);
			return false;
		}

		$query = false;
		if (!$session) $session = $this->getSession($table);
		$keys = $this->getSetVar($session, 'manual_key', 'manual_key');

		$operators = $this->getSetVar($session, 'manual_operator', 'manual_operator');
		$values = $this->getSetVar($session, 'manual_value', 'manual_value');
		settype($operators, 'array');
		settype($values, 'array');
		for ($i = 0; $i < count($keys); $i++) {
			$key = $keys[$i];
			$value = $values[$i];
			if ($key == '' || $value == '') {
				continue;
			}
			$operator = $operators[$i];
			$this->setManualFilterForKey($query, $key, $value, $operator);
		}
		return $query;
	}

	public function logoutAction() {
		Billrun_Factory::auth()->clearIdentity();
		$session = Yaf_Session::getInstance();
		foreach ($session as $k => $v) {
			unset($session[$k]);
		}

		$this->forceRedirect('/admin/login');
	}

	/**
	 * method to export rates to csv
	 *
	 * @return null; directly export to client
	 *
	 * @todo refactoring with model csv export
	 */
	public function exportratesAction() {
		if (!$this->allowed('read'))
			return false;
		$table = "rates";
		$sort = $this->applySort($table);
		$options = array(
			'collection' => $table,
			'sort' => $sort,
		);

		self::initModel($table, $options);
		$query = $this->applyFilters($table);

		$rates = $this->model->getRates($query);


		$showprefix = $_GET['show_prefix'];
		$show_prefix = $showprefix == 'true' ? true : false;
		$header = $this->model->getPricesListFileHeader($show_prefix);
		$data_output[] = implode(",", $header);
		foreach ($rates as $rate) {
			$rules = $this->model->getRulesByRate($rate, $show_prefix);
			foreach ($rules as $rule) {
				$imploded_text = '';
				foreach ($header as $title) {
					$imploded_text.=$rule[$title] . ',';
				}
				$data_output[] = substr($imploded_text, 0, strlen($imploded_text) - 1);
			}
		}

		$output = implode(PHP_EOL, $data_output);
		header("Cache-Control: max-age=0");
		header("Content-type: application/csv");
		header("Content-Disposition: attachment; filename=export_rates.csv");
		die($output);
	}

	public function wholesaleAction() {
		if (!$this->allowed('reports'))
			return false;
		$this->addJs('//www.google.com/jsapi');
		$this->addJs('/js/graphs.js');
		$this->addJs('/js/jquery.stickytableheaders.min.js');
		$table = 'wholesale';
		$group_by = $this->getSetVar($this->getSession($table), 'group_by', 'group_by', 'dayofmonth');
		$from_day = $this->getSetVar($this->getSession($table), 'from_day', 'from_day', (new Zend_Date(strtotime('60 days ago'), null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd'));
		$to_day = $this->getSetVar($this->getSession($table), 'to_day', 'to_day', (new Zend_Date(time(), null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd'));
		$model = new WholesaleModel();
		$viewData = array(
			'data' => $model->getStats($group_by, $from_day, $to_day),
			'group_fields' => $model->getGroupFields(),
			'filter_fields' => $model->getFilterFields(),
			'session' => $this->getSession($table),
			'group_by' => $group_by,
			'tbl_params' => $model->getTblParams(),
			'retail_data' => $model->getRetailData($from_day, $to_day),
			'retail_tbl_params' => $model->getRetailTableParams(),
			'common_columns' => $model->getCommonColumns(),
			'baseUrl' => $this->baseUrl,
		);
		$this->getView()->component = $this->renderView($table, $viewData);
	}

	public function wholesaleAjaxAction() {
		if (!$this->authorized('reports'))
			return false;
		$group_by = $this->getRequest()->get('group_by');
		$direction = $this->getRequest()->get('direction');
		$carrier = $this->getRequest()->get('carrier');
		$from_day = $this->getRequest()->get('from_day');
		$to_day = $this->getRequest()->get('to_day');
		$model = new WholesaleModel();
		$report_type = $this->getReportTypeByDirection($direction);
		if ($report_type == 'nr') {
			$data = $model->getNrStats($group_by, $from_day, $to_day, $carrier);
		} else {
			$data = $model->getStats($group_by, $from_day, $to_day, $report_type, $carrier);
		}
		$this->getView()->data = $data;
		$this->getView()->carrier = $carrier;
		$this->getView()->group_by = $group_by;
		$this->getView()->group_by_display = $model->getGroupFields()['group_by']['values'][$group_by]['display'];
		$this->getView()->from_day = $from_day;
		$this->getView()->tbl_params = $model->getTblParams($report_type);
	}

	protected function getReportTypeByDirection($direction) {
		switch ($direction) {
			case 'TG':
				return 'incoming_call';
			case 'FG':
				return 'outgoing_call';
			default:
				return 'nr';
		}
	}

public function responseError($message,$statusCode = 400)
	{
		
		$resp = $this->getResponse();
		$req  =  $this->getRequest();
		$resp->setHeader($req->getServer('SERVER_PROTOCOL') , $statusCode );
		$resp->setHeader('Content-Type','application/json');
		if(is_array($message)) { 
			$resp->setBody(json_encode($message));
		} else { 
			$resp->setBody($message);
		}
		//$resp->response();
		return false;
	}

	/**
	 * @param string $message
	 * @param int $successStatus
	 * @return bool
	 */
	public function responseSuccess($answer)
	{
			
		$statusCode = 200 ;	
		$resp =  $this->getResponse();
		$req  =  $this->getRequest();
		$resp->setHeader($req->getServer('SERVER_PROTOCOL')  , $statusCode . ' OK');
		$resp->setHeader('Content-Type','application/json');
		$resp->setBody(json_encode($answer));
		//$resp->response();
		return false;
	}

  
}
