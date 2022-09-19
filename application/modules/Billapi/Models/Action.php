<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi operation that run on entities
 *
 * @package  Billapi
 * @since    5.3
 */
abstract class Models_Action {

	use Models_Verification;

	/**
	 * Singleton handler
	 * 
	 * @var Models_Action
	 */
	protected static $instance = null;

	/**
	 * data container of the operation
	 * 
	 * @var array
	 */
	protected $data = array();

	/**
	 * request container of the operation
	 * 
	 * @var array
	 */
	protected $request = array();

	/**
	 * settings (config) container of the operation
	 * 
	 * @var array
	 */
	protected $settings = array();

	/**
	 * The wanted query
	 * @var array
	 */
	protected $query = array();

	/**
	 * The new data
	 * @var array
	 */
	protected $update = array();

	/**
	 * collection handler
	 * 
	 * @var array
	 */
	protected $collectionHandler = array();

	/**
	 * The request options
	 * @var array
	 */
	protected $options = array();

	/**
	 * constructor
	 * 
	 * @param array $params parameters of the action
	 */
	protected function __construct(array $params = array()) {
		if (isset($params['request'])) {
			$this->request = $params['request'];
		}
		
		if (isset($params['settings'])) {
			$this->settings = $this->getConfigParams($params);
		}
		
		$query = isset($params['request']['query']) ? @json_decode($params['request']['query'], TRUE) : array();
		$update = isset($params['request']['update']) ? @json_decode($params['request']['update'], TRUE) : array();
		if (json_last_error() != JSON_ERROR_NONE) {
			throw new Billrun_Exceptions_Api(0, array(), 'Input parsing error');
		}
		list($this->query, $this->update) = $this->validateRequest($query, $update, $this->request['collection'], $this->settings, 999999, false, $params['options']);
		
		if (isset($params['data'])) {
			$this->setData($params['data']);
		}
		
		if (isset($this->request['collection'])) {
			$this->collectionHandler = Billrun_Factory::db()->{$this->getCollectionName() . 'Collection'}();
		}

		if (isset($this->request['options'])) {
			$this->options = @json_decode($this->request['options'], TRUE);
		}
	}
	
	protected function getConfigParams($params) {
		return $params['settings'];
	}

	/**
	 * method to get the collection name of the action
	 * by default it passed in the request
	 * 
	 * @return string the collection name
	 */
	protected function getCollectionName() {
		return $this->request['collection'];
	}

	/**
	 * get action instance
	 * 
	 * @param array $params the parameters of the action
	 * 
	 * @return Models_Action operation
	 */
	public static function getInstance($params) {
		if (is_null(self::$instance)) {
			$class = 'Models_Action_' . ucfirst($params['request']['action']) . '_' . ucfirst($params['request']['collection']);
			if (@class_exists($class, true)) {
				self::$instance = new $class($params);
			} else {
				$class = 'Models_Action_' . ucfirst($params['request']['action']);
				self::$instance = new $class($params);
			}
		}
		return self::$instance;
	}

	/**
	 * set the data of the operation
	 * 
	 * @param array $d operation data
	 */
	public function setData($d) {
		$this->data = $d;
	}

	/**
	 * retrieve the data of the operation
	 * 
	 * @return array operation data
	 */
	public function getData() {
		return $this->data;
	}

	abstract public function execute();
}
