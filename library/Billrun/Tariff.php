<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Tariff class
 * 
 * Main rate system
 * 
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Tariff {

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
	static protected $options = null;

	protected function __construct(array $options = array()) {
		$this->options = $options;
		$this->load();
	}
	/**
	 * load the tariff from DB
	 */
	protected function load() {
		
	}

	public function get() {
		$args = func_get_args();
	}

	public function set() {
		$args = func_get_args();
	}

	public function delete() {
		$args = func_get_args();
	}

	public function save() {
		$args = func_get_args();
	}
	
	public function getInstance(array $options = array()) {
		if (!isset(self::$instance)) {
			self::$instance = new self($options);
		}
		return self::$instance;
	}

}
