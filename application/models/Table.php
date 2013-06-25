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
	 * the sort of the page
	 * 
	 * @var array
	 */
	protected $sort;

	/**
	 * the count of the page
	 * 
	 * @var int
	 */
	protected $_count = null;

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
		
		if (isset($params['sort'])) {
			$this->sort = $params['sort'];
		}

	}

	/**
	 * Get the data resource
	 * 
	 * @return Mongo Cursor
	 */
	public function getData() {
		if ($this->page > 0) {
			$skip = ($this->page-1) * $this->size;
			if ($skip > $this->count()) {
				$skip = 0;
			}
		} else {
			$skip = 0;
		}

		$resource = $this->collection->query()->cursor()->sort($this->sort)->skip($skip)->limit($this->size);
		return $resource;
	}
	
	/**
	 * Get whole data count
	 * 
	 * @param boolean $force if set will inforce recount although already count done
	 * 
	 * @return int
	 */
	public function count($force = false) {
		if ($force || is_null($this->_count)) {
			$this->_count = $this->collection->query()->count();
		}
		return $this->_count;
	}
	
	/**
	 * get the pager data
	 * 
	 * @return array
	 */
	public function getPager() {
		$ret = array(
			'count' => (int) ceil($this->count() / $this->size),
			'current' => $this->page,
			'size' => $this->size,
		);
		return $ret;
	}

	public function update($data) {
		
	}

	public function insert($data) {
		
	}

	public function closeUpdate($data) {
		
	}

}
