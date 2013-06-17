<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Table model class to pull data from database
 *
 * @package  Models
 * @since    0.5
 */
class TableModel {

	/**
	 * the collection to run on
	 * 
	 * @var Mongodloid Collection
	 */
	protected $collection;

	/**
	 * the page number to pull
	 * @var int
	 */
	protected $page;

	/**
	 * the size of the page
	 * 
	 * @var int
	 */
	protected $size;

	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) {

		if (isset($params['collection'])) {
			$this->collection = call_user_func(array(Billrun_Factory::db(), $params['collection'] . 'Collection'));
		}

		if (isset($params['page'])) {
			$this->page = $params['page'];
		}

		if (isset($params['size'])) {
			$this->size = $params['size'];
		}
	}

	/**
	 * get the data resource
	 * 
	 * @return type
	 */
	public function getData() {
		$skip = $this->page * $this->size;
		$resource = $this->collection->query()->cursor()->skip($skip)->limit($this->size);
		return $resource;
	}

	public function update($data) {
		
	}

	public function insert($data) {
		
	}

	public function closeUpdate($data) {
		
	}

}
