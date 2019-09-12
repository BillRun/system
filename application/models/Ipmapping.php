<?php

require_once APPLICATION_PATH . '/application/helpers/Admin/Table.php';

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Lines model class to pull data from database for lines collection
 *
 * @package  Models
 * @subpackage Lines
 * @since    0.5
 */
class IpmappingModel extends TableModel {

	/**
	 *
	 * @var boolean show garbage lines
	 */
	protected $garbage = false;
	protected $lines_coll = null;
	protected $longQuery;
	
	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->ipmapping;
		if (isset($params['long_query'])) {
			$this->longQuery = $params['long_query'];
		}
		parent::__construct($params);
		$this->search_key = "stamp";
		$this->lines_coll = Billrun_Factory::db()->ipmapping();
	}

	public function getProtectedKeys($entity, $type) {
		$parent_protected = parent::getProtectedKeys($entity, $type);
		if ($type == 'update') {
			return array_merge($parent_protected, ["type", "stamp", "urt"]);
		}
		return $parent_protected;
	}

	/**
	 * @param Mongodloid collection $collection
	 * @param array $entity
	 * 
	 * @return type
	 * @todo move to model
	 */
	public function getItem($id) {

		$entity = parent::getItem($id);
		Admin_Table::setEntityFields($entity);
		return $entity;
	}

	public function getHiddenKeys($entity, $type) {
		$hidden_keys = array_merge(parent::getHiddenKeys($entity, $type), array("plan_ref"));
		return $hidden_keys;
	}

	public function update($data) {
		$currentDate = new MongoDate();
		parent::update($data);
	}
	
	/**
	 * Get data for the find view.
	 * @param type $filter_query
	 * @return type
	 */
	public function getData($filter_query = array()) {
		$cursor = $this->collection->query($filter_query)->cursor()
			->sort($this->sort)->skip($this->offset())->limit($this->size);
		if ($this->longQuery) {
			$cursor->timeout(-1);
		}
		if (isset($filter_query['$and']) && $this->filterExists($filter_query['$and'], ['stamp'])) {
			$this->_count = $cursor->count(false);
		} else {
			$this->_count = Billrun_Factory::config()->getConfigValue('admin_panel.lines.global_limit', 10000);
		}

		$ret = array();
		foreach ($cursor as $item) {
			$item->collection($this->lines_coll);
			//TODO add  Transforms here
			$ret[] = $item;
		}
		return $ret;
	}

	

	
	public function getDistinctField($field, $filter_query = array()) {
		if (empty($field) || empty($filter_query)) {
			return array();
		}
		return $this->collection->distinct($field, $filter_query);
	}

	public function getTableColumns() {
		$columns = array(
			'external_ip' => 'External IP',
			'internal_ip' => 'Internal IP',
			'start_port' => 'Starting Port',
			'end_port' => 'Ending Port',
			'network' => 'Network ID',
			'urt' => 'Time',
			'recording_entity' => 'Recording Entity',
		);
		if (!empty($this->extra_columns)) {
			$extra_columns = array_intersect_key($this->getExtraColumns(), array_fill_keys($this->extra_columns, ""));
			$columns = array_merge($columns, $extra_columns);
		}
		return $columns;
	}

	public function getFilterFields() {
		$months = 12;
		$billruns = array();
		$timestamp = time();
		for ($i = 0; $i < $months; $i++) {
			$billrun_key = Billrun_Util::getBillrunKey($timestamp);
			if ($billrun_key >= '201401') {
				$billruns[$billrun_key] = $billrun_key;
			}
			else {
				break;
			}
			$timestamp = strtotime("1 month ago", $timestamp);
		}
		arsort($billruns);

		$filter_fields = array(
			'internal_ip' => array(
				'key' => 'internal_ip',
				'db_key' => 'internal_ip',
				'input_type' => 'text',
				'comparison' => 'contains',
				'display' => 'Internal IP',
				'default' => '',
			),
			'external_ip' => array(
				'key' => 'external_ip',
				'db_key' => 'external_ip',
				'input_type' => 'text',
				'comparison' => 'contains',
				'display' => 'External IP',
				'default' => '',
			),
			'start_port' => array(
				'key' => 'start_port',
				'db_key' => 'start_port',
				'input_type' => 'number',
				'comparison' => '$lte',
				'display' => 'Start Port Below',
				'default' => '',
			),
			'end_port' => array(
				'key' => 'end_port',
				'db_key' => 'end_port',
				'input_type' => 'number',
				'comparison' => '$gte',
				'display' => 'End Port Above',
				'default' => '',
			),
			'from' => array(
				'key' => 'from',
				'db_key' => 'urt',
				'input_type' => 'date',
				'comparison' => '$gte',
				'display' => 'From',
				'default' => (new Zend_Date(strtotime('2 months ago'), null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd HH:mm:ss'),
			),
			'to' => array(
				'key' => 'to',
				'db_key' => 'urt',
				'input_type' => 'date',
				'comparison' => '$lte',
				'display' => 'To',
				'default' => (new Zend_Date(strtotime("next month"), null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd HH:mm:ss'),
			),
		);
		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function applyFilter($filter_field, $value) {
		if ($filter_field['comparison'] == 'special') {
			if ($filter_field['input_type'] == 'boolean') {
				if (!is_null($value) && $value != $filter_field['default']) {
					$rates_coll = Billrun_Factory::db()->ratesCollection();
					$unrated_rate = $rates_coll->query("key", "UNRATED")->cursor()->current()->createRef($rates_coll);
					$month_ago = new MongoDate(strtotime("1 month ago"));
					return array(
						'$or' => array(
							array('arate' => $unrated_rate), // customer rate is "UNRATED"
							array('sid' => false), // or subscriber not found
							array('$and' => array(// old unpriced records which should've been priced
									array('arate' => array(
											'$exists' => true,
											'$nin' => array(
												false, $unrated_rate
											),
										)),
									array('sid' => array(
											'$exists' => true,
											'$ne' => false,
										)),
									array('urt' => array(
											'$lt' => $month_ago
										)),
									array('aprice' => array(
											'$exists' => false
										)),
								)),
					));
				}
			}
		} else {
			return parent::applyFilter($filter_field, $value);
		}
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			0 => array(
				'internal_ip' => array(
					'width' => 2,
				),
				'external_ip' => array(
					'width' => 2,
				),
				'from' => array(
					'width' => 2,
				),
				'to' => array(
					'width' => 2,
				),
			),
			1 => [
				'start_port' => [	'width' => 2	],
				'end_port' => 	[	'width' => 2	],
			]
		);
		return $filter_field_order;
	}

	public function getSortFields() {
		return array(
			'process_time' => 'Process time',
			'urt' => 'Time',
			'type' => 'Type',
			'internal_ip' => 'Internal IP',
			'start_port' => 'Start Port',
			'end_port' => 'End Port',
		);
	}

	protected function formatCsvCell($row, $header) {
		if (($header == 'from' || $header == 'to' || $header == 'urt' || $header == 'notify_time') && $row) {
			if (!empty($row["tzoffset"])) {
				// TODO change this to regex; move it to utils
				$tzoffset = $row['tzoffset'];
				$sign = substr($tzoffset, 0, 1);
				$hours = substr($tzoffset, 1, 2);
				$minutes = substr($tzoffset, 3, 2);
				$time = $hours . ' hours ' . $minutes . ' minutes';
				if ($sign == "-") {
					$time .= ' ago';
				}
				$timsetamp = strtotime($time, $row['urt']->sec);
				$zend_date = new Zend_Date($timsetamp);
				$zend_date->setTimezone('UTC');
				return $zend_date->toString("d/M/Y H:m:s") . $row['tzoffset'];
			} else {
				$zend_date = new Zend_Date($row[$header]->sec);
				return $zend_date->toString("d/M/Y H:m:s");
			}
		} else {
			return parent::formatCsvCell($row, $header);
		}

	}
	
	public function remove($params) {
		// first remove line from queue (collection) than from lines collection (parent)
		Billrun_Factory::db()->queueCollection()->remove($params);
		return parent::remove($params);
	}
	
	
	protected function initializeCollection($params){
		if (isset($params['db']) && $params['db'] == "billing") {
			$this->collection = call_user_func(array(Billrun_Factory::db(array('name' => $params['db'])), $params['collection'] . 'Collection'));
		} else if (isset($params['db'])) {
			$db = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue($params['db'] . '.db'));
			$this->collection = $db->getCollection($params['collection']);
		} else {
			$this->collection = call_user_func(array(Billrun_Factory::db(), $params['collection'] . 'Collection'));
		}
	}

}
