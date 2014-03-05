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
	 *
	 * @var array extra columns to display in the table
	 */
	public $extra_columns;

	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) {

		if (isset($params['collection'])) {
			if (isset($params['db'])) {
				$this->collection = Billrun_Factory::db(array('name' => $params['db']))->balancesCollection();
			} else {
				$this->collection = call_user_func(array(Billrun_Factory::db(), $params['collection'] . 'Collection'));
//                          $this->collection->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED);
			}
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
		
		if (isset($params['extra_columns'])) {
			$this->extra_columns = $params['extra_columns'];
		}
	}

	/**
	 * Get the data resource
	 * 
	 * @return Mongo Cursor
	 */
	public function getData($filter_query = array()) {
		$resource = $this->collection->query($filter_query)->cursor()->sort($this->sort)->skip($this->offset())->limit($this->size);
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

		if (($count = $this->getPagesCount()) > 1) {
			$current = $this->page;

			// TODO: move it to config
			$range = 5;

			$min = $current - $range;
			$max = $current + $range;

			if ($current < 1) {
				$current = 1;
			}

			if ($current > $this->getPagesCount()) {
				$current = $this->getPagesCount();
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

		$entity = $this->collection->findOne($id);

		// convert mongo values into javascript values
		$entity['_id'] = (string) $entity['_id'];

		return $entity;
	}

	public function remove($params) {
		return $this->collection->remove($params);
	}

	public function update($params) {

//		if (method_exists($this, $coll . 'BeforeDataSave')) {
//			call_user_func_array(array($this, $coll . 'BeforeDataSave'), array($collection, &$newEntity));
//		}
		if (isset($params['_id'])) {
			$entity = $this->collection->findOne($params['_id']);
			$protected_keys = $this->getProtectedKeys($entity, "update");
			$hidden_keys = $this->getHiddenKeys($entity, "update");
			$raw_data = $entity->getRawData();
			$new_data = array();
			foreach ($protected_keys as $value) {
				$new_data[$value] = $raw_data[$value];
			}
			foreach ($hidden_keys as $value) {
				$new_data[$value] = $raw_data[$value];
			}
			foreach ($params as $key => $value) {
				$new_data[$key] = $value;
			}
			$entity->setRawData($new_data);
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

	public function getHiddenKeys($entity, $type) {
		return array();
	}

	public function getFilterFields() {
		return array();
	}

	public function getEditKey() {
		return null;
	}

	public function applyFilter($filter_field, $value) {
		if ($filter_field['input_type'] == 'number') {
			if ($value != '') {
				if ($filter_field['comparison'] == 'equals') {
					return array(
						$filter_field['key'] => intval($value),
					);
				}
			}
		} else if ($filter_field['input_type'] == 'text') {
			if ($value != '') {
				if ($filter_field['comparison'] == 'contains') {
					return array(
						$filter_field['db_key'] => array('$regex' => strval($value)),
					);
				}
			}
		} else if ($filter_field['input_type'] == 'date') {
			if (is_string($value) && Zend_Date::isDate($value, 'yyyy-MM-dd hh:mm:ss')) { //yyyy-MM-dd hh:mm:ss
				$value = new MongoDate((new Zend_Date($value, null, new Zend_Locale('he_IL')))->getTimestamp());
				return array(
					$filter_field['db_key'] => array(
						$filter_field['comparison'] => $value
					)
				);
			}
		} else if ($filter_field['input_type'] == 'multiselect') {
			if (is_array($value) && !empty($value)) {
				return array(
					$filter_field['db_key'] => array(
						$filter_field['comparison'] => $value
					)
				);
			}
		}
		return false;
	}

	public function getFilterFieldsOrder() {
		return array();
	}

	protected function getDBRefField($item, $field_name) {
		if (($value = $item->get($field_name, true)) && MongoDBRef::isRef($value)) {
			$value = Billrun_DBRef::getEntity($value);
		}
		return $value;
	}
	
	public function getExtraColumns() {
		return array();
	}

}
