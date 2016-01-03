<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Configmodel class
 *
 * @package  Models
 * @since    2.1
 */
class ConfigModel {
	
	/**
	 * the collection the config run on
	 * 
	 * @var Mongodloid Collection
	 */
	protected $collection;
	
	/**
	 * the config values
	 * @var array
	 */
	protected $data;
	
	/**
	 * options of config
	 * @var array
	 */
	protected $options;
	
	public function __construct() {
		// load the config data from db
		$this->collection = Billrun_Factory::db()->configCollection();
		$this->options = array('receive', 'process', 'calculate');
		$this->loadConfig();
	}
	
	public function getOptions() {
		return $this->options;
	}
	
	protected function loadConfig() {
		$ret = $this->collection->query()
			->cursor()
			->sort(array('_id' => -1))
			->limit(1)
			->current()
			->getRawData();

		$this->data = $ret;
	}
	
	public function getConfig() {
		return $this->data;
	}
	
	public function setConfig($data) {
		foreach ($this->options as $option) {
			if (!isset($data[$option])) {
				$data[$option] = 0;
			}
		}
		return $this->collection->insert($data);
	}
	
	public function save($items) {
		$data = $this->getConfig();
		$saveData = array_merge($data, $items);
		$this->setConfig($saveData);
	}
}