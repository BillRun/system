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
	 * the page number to pull; use as current page
	 * 
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
	 *
	 * @var string the main key field name (e.g. "name" for plans)
	 */
	public $search_key;

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
		$resource = $this->collection->query()->cursor()->sort($this->sort)->skip($this->offset())->limit($this->size);
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

	public function getPagesCount() {
		return (int) ceil($this->count() / $this->size);
	}

	public function offset() {
		if ($this->page > 0) {
			$offset = ($this->page - 1) * $this->size;
			if ($offset > $this->count()) {
				$offset = 0;
			}
		} else {
			$offset = 0;
		}
		return $offset;
	}

	/**
	 * get the pager data
	 * 
	 * @return array
	 */
	public function getPager() {
		$ret = array(
			'count' => $this->getPagesCount(),
			'current' => $this->page,
			'size' => $this->size,
		);
		return $ret;
	}

	public function printPager($print = false) {

		if ($this->getPagesCount() > 1) {
			$current = $this->page;
			$count = $this->count();

			// TODO: move it to config
			$range = 5;

			$min = $current - $range;
			$max = $current + $range;

			if ($current < 1) {
				$current = 1;
			}

			if ($current > $count) {
				$current = $count;
			}

			if ($min < 1) {
				$min = 1;
			}
			if ($max > $count) {
				$max = $count;
			}

			$ret = '<div class="pagination pagination-right">'
				. '<ul>';
			if ($current == 1) {
				$ret .= '<li class="disabled"><a href="javascript:void(0);">First</a></li>'
					. '<li class="disabled"><a href="javascript:void(0);">Prev</a></li>';
			} else {
				$ret .= '<li><a href="?page=1">First</a></li>'
					. '<li><a href="?page=' . ($current - 1) . '">Prev</a></li>';
			}

			for ($i = $min; $i < $current; $i++) {
				$ret .= '<li><a href="?page=' . $i . '">' . $i . '</a></li>';
			}

			$ret .= '<li class="active disabled"><a href="javascript:void(0);">' . $current . '</a></li>';

			for ($i = ($current + 1); $i <= $max; $i++) {
				$ret .= '<li><a href="?page=' . $i . '">' . $i . '</a></li>';
			}

			if ($current == $count) {
				$ret .= '<li class="disabled"><a href="javascript:void(0);">Next</a></li>'
					. '<li class="disabled"><a href="javascript:void(0);">Last</a></li>';
			} else {
				$ret .= '<li><a href="?page=' . ($current + 1) . '">Next</a></li>'
					. '<li><a href="?page=' . $count . '">Last</a></li>';
			}

			$ret .= '</ul></div>';

			if ($print) {
				print $ret;
			}
			return $ret;
		}
	}

	public function printSizeList($print = false) {
		$ret = '<div id="listSize" class="btn-group">
				<button class="btn btn-danger dropdown-toggle" data-toggle="dropdown">' . $this->size . ' <span class="caret"></span></button>
				<ul class="dropdown-menu">';
		// TODO: move it to config
		$ranges = array(
			10, 50, 100, 500, 1000
		);
		foreach ($ranges as $r) {
			$ret .= '<li><a href="?listSize=' . $r . '">' . $r . '</a></li>';
		}
		$ret .= '</ul></div><!-- /btn-group -->';
		if ($print) {
			print $ret;
		}
		return $ret;
	}

	public function getItem($id) {
		if (!($this->collection instanceof Mongodloid_Collection)) {
			return false;
		}

		$entity = $this->collection->findOne($id, true);

		// convert mongo values into javascript values
		$entity['_id'] = (string) $entity['_id'];

		return $entity;
	}

	public function remove($params) {
		$id = $params['_id'];
		return $this->collection->remove($id);
	}
	
	public function save($params) {
//		if (method_exists($this, $coll . 'BeforeDataSave')) {
//			call_user_func_array(array($this, $coll . 'BeforeDataSave'), array($collection, &$newEntity));
//		}
		if (isset($params['_id'])) {
			$entity = $this->collection->findOne($params['_id']);
			foreach ($params as $key => $value) {
				$entity[$key] = $value;
			}
		} else {
			$entity = new Mongodloid_Entity($params);
		}
		$entity->save($this->collection);
//		if (method_exists($this, $coll . 'AfterDataSave')) {
//			call_user_func_array(array($this, $coll . 'AfterDataSave'), array($collection, &$newEntity));
//		}
		return $entity;
	}

	public function getProtectedKeys($entity, $type) {
		return array("_id");
	}

}
