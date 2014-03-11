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
	 * display the current status of the  generators  and  the currently  running test
	 * @param type $param
	 */
	public function statusAction($param) {
		
	}
	
	/**
	 * generate a report
	 */
	public function reportAction() {
		$from = Billrun_Util::getFieldVal(Billrun_Util::filter_var($this->getRequest()->get('from'), FILTER_SANITIZE_STRING),date("Y-m-d H:i:sP",time()-86400*7));
		$to =  Billrun_Util::getFieldVal(Billrun_Util::filter_var($this->getRequest()->get('to'), FILTER_SANITIZE_STRING),date("Y-m-d H:i:sP"));
		$testId =  Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);
		$generator = Billrun_Generator::getInstance(array('type'=> 'Report_GeneratedCalls','from' => $from, 'to' => $to, 'test_id' => $testId ));
		header("Cache-Control: max-age=0");
		header("Content-type: application/csv");
		header("Content-Disposition: attachment; filename=csv_export.csv");
		print_r($generator->generate());
	}

	/**
	 * create a new test
	 * @param type $param
	 */
	public function createAction($param) {
		$from = Billrun_Util::getFieldVal(Billrun_Util::filter_var($this->getRequest()->get('from'), FILTER_SANITIZE_STRING),date("Y-m-d H:i:sP",time()-86400*7));
		$testId =  Billrun_Util::filter_var($this->getRequest()->get('id'), FILTER_SANITIZE_STRING);
		$numbers =  Billrun_Util::getFieldVal(Billrun_Util::filter_var($this->getRequest()->get('numbers'), FILTER_SANITIZE_STRING),array(/*TODO  change to configuration*/));
		$generator = Billrun_Generator::getInstance(array_merge(Billrun_Factory::config()->getConfigValue('Report_CallingScript',array()),
																array('type'=> 'Report_CallingScript','start_test' => $from, 'test_id' => $testId,'to_remote'=> true ) ));
		$generator->generate();
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

		foreach ($ids as $id) {
			$params['_id']['$in'][] = new MongoId($id);
		}

		if ($type == 'remove') {
			$saveStatus = $model->remove($params);
		}

		// @TODO: need to load ajax view
		// for now just die with json
		die(json_encode(null));
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
			'offset' => $this->model->offset(),
		);

		return $params;
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
		$session = $this->getSession($collection_name);
		$options['page'] = $this->getSetVar($session, "page", "page", 1);
		$options['size'] = $this->getSetVar($session, "listSize", "size", 1000);

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
		$var = $request->get($source_name);
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


	protected function applySort($table) {
		$session = $this->getSession($table);
		$sort_by = $this->getSetVar($session, 'sort_by', 'sort_by', '_id');
		$order = $this->getSetVar($session, 'order', 'order', 'asc') == 'asc' ? 1 : -1;
		$sort = array($sort_by => $order);
		return $sort;
	}	

}