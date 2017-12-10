<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Tariff class
 * 
 * Main rate system
 * 
 * @package  Billing
 * @since    0.5
 */
class Billrun_Tariff {

	/**
	 * Instance of the Tariff
	 *
	 * @var self instance (Billrun_Tariff)
	 */
	static protected $instance = null;

	/**
	 * Options of the Tariff
	 *
	 * @var array
	 */
	protected $options = null;

	/**
	 * Rates data of the Tariff
	 *
	 * @var array
	 */
	protected $rates = null;

	/**
	 * constructor
	 * 
	 * @param array $options the options to preset for the class
	 */
	protected function __construct(array $options = array()) {
		$this->options = $options;
		// todo: support lazy load
		$this->load();
	}

	/**
	 * load the tariff from DB
	 */
	protected function load() {
		$this->rates = Billrun_Factory::db()->ratesCollection();
	}

	/**
	 * get tariff rate
	 */
	public function get() {
		$args = func_get_args();
		if (is_array($args[0])) {
			$params = $args[0];
			$searchValue = isset($params['searchValue']) ? $params['searchValue'] : array('$exists' => true);
			$searchField = isset($params['searchBy']) ? $params['searchBy'] : 'key';
		} else {
			@list($searchField, $searchValue) = $args;
			$searchValue = $searchValue ? $params['searchBy'] : array('$exists' => true);
		}

		$data = $this->rates->query()->equals($searchField, $searchValue)->cursor()->current();

		return $data;
	}

	/**
	 * get tariff rate
	 */
	public function query() {
		$args = func_get_args();

		$data = $this->rates->query($args);

		return $data;
	}

	/**
	 * set the tariff rate
	 */
	public function set() {
		return false;
	}

	/**
	 * delete tariff rate
	 */
	public function delete() {
		return false;
	}

	/**
	 * save tariff to database
	 */
	public function save() {
		return false;
	}

	/**
	 * Singleton of tariff class
	 * 
	 * @param array $options the options of the instance
	 * 
	 * @return type
	 */
	public static function getInstance(array $options = array()) {
		if (!isset(self::$instance)) {
			self::$instance = new self($options);
		}
		return self::$instance;
	}

}
