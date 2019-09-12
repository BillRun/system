<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
	 *
	 * @var string The collection name
	 */
	protected $collection_name;

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
	protected $sort = array();

	/**
	 * the count of the full scope (use for pagination)
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
			$this->initializeCollection($params);
			$this->collection_name = $params['collection'];
		}

		if (isset($params['page'])) {
			$this->setPage($params['page']);
		}

		if (isset($params['size'])) {
			$this->setSize($params['size']);
		}

		if (isset($params['sort'])) {
			$this->sort = $params['sort'];
		}

		if (isset($params['extra_columns'])) {
			$this->extra_columns = $params['extra_columns'];
		}
	}

	public function setSize($size) {
		$this->size = $size;
	}

	public function setPage($page) {
		$this->page = $page;
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

	/**
	 * Get the pager html to use.
	 * @param type $print 
	 * @param type $params - Table of parameters to send through the href.
	 * @return string
	 */
	public function printPager($print = false, $params = array()) {

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
			
			$hrefParams = http_build_query($params);
			// If the parameter list is not empty, put a & prefix.
			if(!empty($hrefParams)) {
				$hrefParams= '&' . $hrefParams;
			}
			
			$ret = '<ul class="pagination pagination-right">';
			if ($current == 1) {
				$ret .= '<li class="disabled"><a href="javascript:void(0);">First</a></li>'
					. '<li class="disabled"><a href="javascript:void(0);">Prev</a></li>';
			} else {
				$ret .= '<li><a href="?page=1' . $hrefParams . '">First</a></li>'
					. '<li><a href="?page=' . ($current - 1) . $hrefParams . '">Prev</a></li>';
			}

			for ($i = $min; $i < $current; $i++) {
				$ret .= '<li><a href="?page=' . $i . $hrefParams . '">' . $i . '</a></li>';
			}

			$ret .= '<li class="active disabled"><a href="javascript:void(0);">' . $current . '</a></li>';

			for ($i = ($current + 1); $i <= $max; $i++) {
				$ret .= '<li><a href="?page=' . $i . $hrefParams . '">' . $i . '</a></li>';
			}

			if ($current == $count) {
				$ret .= '<li class="disabled"><a href="javascript:void(0);">Next</a></li>'
					. '<li class="disabled"><a href="javascript:void(0);">Last</a></li>';
			} else {
				$ret .= '<li><a href="?page=' . ($current + 1) . $hrefParams . '">Next</a></li>'
					. '<li><a href="?page=' . $count . $hrefParams . '">Last</a></li>';
			}

			$ret .= '</ul>';

			if ($print) {
				print $ret;
			}
			return $ret;
		}
	}
	/**
	 * Get the size list html to use.
	 * @param type $print 
	 * @param type $params - Table of parameters to send through the href.
	 * @return string
	 */
	public function printSizeList($print = false, $params = array()) {
		$ret = '<div id="listSize" class="btn-group">
				<button class="btn btn-danger dropdown-toggle" data-toggle="dropdown">' . $this->size . ' <span class="caret"></span></button>
				<ul class="dropdown-menu">';
		
		$hrefParams = http_build_query($params);
		// If the parameter list is not empty, put a & prefix.
		if(!empty($hrefParams)) {
			$hrefParams= '&' . $hrefParams;
		}
			
		// TODO: move it to config
		$ranges = array(
			10, 50, 100, 500, 1000
		);
		foreach ($ranges as $r) {
			$ret .= '<li><a href="?listSize=' . $r . $hrefParams . '">' . $r . '</a></li>';
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
				if (isset($raw_data[$value])) {
					$new_data[$value] = $raw_data[$value];
				}
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
		$defaultNativeLong = ini_get('mongo.native_long');
		$this->setMongoNativeLong(1);
		$entity->save($this->collection, 1);
		$this->setMongoNativeLong($defaultNativeLong);
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

	public function getSortFields() {
		return array();
	}

	public function getEditKey() {
		return null;
	}

	public function applyFilter($filter_field, $value) {
		if ($filter_field['input_type'] == 'number') {
			if ($value != '') {
				if ($filter_field['comparison'] == 'equals') {
					if (is_array($filter_field['db_key'])) {
						$ret = array('$or' => array(
								array(
									$filter_field['db_key'][0] => array(
										'$in' => array_map('floatval', explode(',', $value)),
									),
								),
								array(
									$filter_field['db_key'][1] => array(
										'$in' => array_map('strval', explode(',', $value)),
									),
								)
							)
						);
					} else {
						$ret = array(
							$filter_field['db_key'] => array(
								'$in' => array_map('floatval', explode(',', $value)),
							),
						);
					}
					return $ret;
				} if (strpos($filter_field['comparison'],'$') === 0 ) {
					return array(
						$filter_field['db_key'] => array($filter_field['comparison'] => floatval($value)),
					);
				}
			}
		} else if ($filter_field['input_type'] == 'text') {
			if ($value != '') {
				if ($filter_field['comparison'] == 'contains') {
					if (isset($filter_field['case_type'])) {
						$value = Admin_Table::convertValueByCaseType($value, $filter_field['case_type']);
					}
					return array(
						$filter_field['db_key'] => array('$regex' => strval($value)),
					);
				} else if (strpos($filter_field['comparison'],'$') === 0 ) {
					return array(
						$filter_field['db_key'] => array($filter_field['comparison'] => strval($value)),
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
			if (isset($filter_field['ref_coll']) && isset($filter_field['ref_key'])) {
				$collection = Billrun_Factory::db()->{$filter_field['ref_coll'] . "Collection"}();
				$pre_query = array(
					$filter_field['ref_key'] => array(
						'$in' => $value,
					),
				);
				$cursor = $collection->query($pre_query);
				$value = array();
				foreach ($cursor as $entity) {
					$value[] = $entity->createRef($collection);
				}
			}
			if (is_array($value) && !empty($value)) {

				if ($this instanceof QueueModel && $filter_field['db_key'] == 'calc_name') {
					$value = $this->prev_calc($value);
				}
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

	/**
	 * 
	 * @param type $item
	 * @param type $field_name
	 * @return type
	 * @todo: Same function in DBRef, change all calls to the static function and make this deprecated.
	 */
	protected function getDBRefField($item, $field_name) {
		if (($value = $item->get($field_name, true)) && MongoDBRef::isRef($value)) {
			$value = Billrun_DBRef::getEntity($value);
		}
		return $value;
	}
	
	public function getQueryTypes() {
		$query_types = Billrun_Factory::config()->getConfigValue('admin_panel.' . $this->collection_name . '.query_types', array());
		return $query_types;
	}
	
	public function getAggregateByFields() {
		$aggregateByFields = Billrun_Factory::config()->getConfigValue('admin_panel.' . $this->collection_name . '.aggregate_by_fields', array());
		return $aggregateByFields;
	}

	public function getExtraColumns() {
		$extra_columns = Billrun_Factory::config()->getConfigValue('admin_panel.' . $this->collection_name . '.extra_columns', array());
		return $extra_columns;
	}

	public function getTableColumns() {
		$columns = Billrun_Factory::config()->getConfigValue('admin_panel.' . $this->collection_name . '.table_columns', array());
		if (!empty($this->extra_columns)) {
			$extra_columns = array_intersect_key($this->getExtraColumns(), array_fill_keys($this->extra_columns, ""));
			$columns = array_merge($columns, $extra_columns);
		}
		return $columns;
	}

	public function getSortElements() {
		$sort_fields = $this->getSortFields();
		if ($sort_fields) {
			$sort_fields = array_merge(array(0 => 'N/A'), $sort_fields);
		}
		return $sort_fields;
	}

	public function duplicate($params) {
		$key = $params[$this->search_key];
		$count = $this->collection
			->query($this->search_key, $key)
			->count();

		if ($count) {
			die(json_encode("key already exists"));
		}
		if (isset($params['_id']->{'id'})) {
			$params['source_id'] = (string) $params['_id']->{'$id'};
		} else if (isset($params['_id'])){
			$params['source_id'] = (string) $params['_id'];
		}
		unset($params['_id']);
		return $this->update($params);
	}

	public function getEmptyItem() {
		return new Mongodloid_Entity();
	}

	/**
	 * method to check if indexes exists in the query filters
	 * 
	 * @param type $filters the filters to search in
	 * @param type $searched_filter the filter to search
	 * 
	 * @return boolean true if searched filter exists in the filters supply
	 * @todo Moved this to utils, use the one in utils and deprecate this function.
	 */
	protected function filterExists($filters, $searched_filter) {
		settype($searched_filter, 'array');
		foreach ($filters as $k => $f) {
			$keys = array_keys($f);
			if (count(array_intersect($searched_filter, $keys))) {
				return true;
			}
		}
		return false;
	}

	public function exportCsvFile($params) {
		$separator = ',';
		$header_output[] = implode($separator, $this->prepareHeaderExport($params['columns']));
		$data_output = $this->prepareDataExport($params['data'], array_keys($params['columns']), $separator);
		$output = implode(PHP_EOL, array_merge($header_output, $data_output));
		$this->export($output);
	}

	protected function export($output) {
		header("Cache-Control: max-age=0");
		header("Content-type: application/csv");
		header("Content-Disposition: attachment; filename=csv_export.csv");
		die($output);
	}

	protected function prepareHeaderExport($headerData) {
		$row = array('#');
		foreach ($headerData as $value) {
			$row[] = $value;
		}
		return $row;
	}

	protected function prepareDataExport($data, $columns, $separator = ',') {
		$ret = array();
		$c = 0;
		foreach ($data as $item) {
			$ret[] = ++$c . $separator . $this->formatCsvRow($item, $columns, $separator);
		}
		return $ret;
	}

	protected function formatCsvRow($row, $columns, $separator = ',') {
		$ret = array();
		foreach ($columns as $h) {
			$ret[] = $this->formatCsvCell($row, $h);
		}
		return implode($separator, $ret);
	}

	protected function formatCsvCell($row, $header) {
		return $row[$header];
	}
	
	protected function initializeCollection($params) {
		if (isset($params['db'])) {
			$this->collection = call_user_func(array(Billrun_Factory::db(array('name' => $params['db'])), $params['collection'] . 'Collection'));
		} else {
			$this->collection = call_user_func(array(Billrun_Factory::db(), $params['collection'] . 'Collection'));
		}
	}
	
	/**
	 * method to set MongoDB native long
	 * this is useful only on MongoDB 2.4 and below because the native long is off by default
	 * 
	 * @param int $status either 1 to turn on or 0 for off
	 */
	protected function setMongoNativeLong($status = 1) {
		Billrun_Factory::db()->setMongoNativeLong($status);
	}


}
