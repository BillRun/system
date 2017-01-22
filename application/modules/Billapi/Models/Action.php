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
	protected $request= array();
	
	/**
	 * settings (config) container of the operation
	 * 
	 * @var array
	 */
	protected $settings = array();
	
	/**
	 * collection handler
	 * 
	 * @var array
	 */
	protected $collectionHandler = array();
	
	/**
	 * constructor
	 * 
	 * @param array $params parameters of the action
	 */
	protected function __construct(array $params = array()) {
		if (isset($params['data'])) {
			$this->setData($params['data']);
		}
		if (isset($params['request'])) {
			$this->request = $params['request'];
		}
		if (isset($params['settings'])) {
			$this->settings = $params['settings'];
		}
		if (isset($this->request['collection'])) {
			$this->collectionHandler = Billrun_Factory::db()->{$this->request['collection'] . 'Collection'}();
		}
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
			if (class_exists($class, true)) {
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