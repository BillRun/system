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
class LinesModel extends TableModel {

	/**
	 *
	 * @var boolean show garbage lines
	 */
	protected $garbage = false;
	protected $lines_coll = null;
	protected $longQuery;
	
	public function __construct(array $params = array()) {
		if ($params['db'] == 'archive' || $params['db'] == 'historyarchive') {
			$params['collection'] = $params['collection'];
		} else {
			$params['collection'] = Billrun_Factory::db()->lines;
		}
		if (isset($params['long_query'])) {
			$this->longQuery = $params['long_query'];
		}
		parent::__construct($params);
		$this->search_key = "stamp";
		$this->lines_coll = Billrun_Factory::db()->linesCollection();
	}

	public function getProtectedKeys($entity, $type) {
		$parent_protected = parent::getProtectedKeys($entity, $type);
		if ($type == 'update') {
			return array_merge($parent_protected, array("type", "aid", "sid", "file", "log_stamp", "imsi", "source", "stamp", "urt", "usaget", "billrun"));
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
		if (isset($data['arate'])) {
			$ratesColl = Billrun_Factory::db()->ratesCollection();
			$rateEntity = $ratesColl->query('key', $data['arate'])
							->lessEq('from', $currentDate)
							->greaterEq('to', $currentDate)
							->cursor()->current();
			$data['arate'] = $rateEntity->createRef($ratesColl);
		}
		if (isset($data['plan'])) {
			$plansColl = Billrun_Factory::db()->plansCollection();
			$planEntity = $plansColl->query('name', $data['plan'])
							->lessEq('from', $currentDate)
							->greaterEq('to', $currentDate)
							->cursor()->current();
			$data['plan_ref'] = $planEntity->createRef($plansColl);
		}
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
		if (isset($filter_query['$and']) && $this->filterExists($filter_query['$and'], array('aid', 'sid', 'stamp'))) {
			$this->_count = $cursor->count(false);
		} else {
			$this->_count = Billrun_Factory::config()->getConfigValue('admin_panel.lines.global_limit', 10000);
		}

		$ret = array();
		foreach ($cursor as $item) {
			$item->collection($this->lines_coll);
			if ($arate = $this->getDBRefField($item, 'arate')) {
				$item['arate'] = $arate['key'];
				$item['arate_id'] = strval($arate['_id']);
			} else {
				$item['arate'] = $arate;
			}
			if(isset($item['rat_type'])) {
				$item['rat_type'] = Admin_Table::translateField($item, 'rat_type');
			}
			$ret[] = $item;
		}
		return $ret;
	}
	
	/**
	 * Translate an aggregated value.
	 * @param string $key - Key of the current aggregated value.
	 * @param string $value - Actual value extracted by the aggregated query.
	 * @return string translated value.
	 */
	protected function translateAggregatedValue($key, $value) {		
		// Translate the values.
		if(strcasecmp($key, "Day of the Week") === 0) {
			// Transform to zero based.
			$value-=1;
			return date('D', strtotime("Sunday +{$value} days"));
		}

		// Translate the values.
		if(strcasecmp($key, "Month") === 0) {
			// Transform to zero based.
			$value-=1;
			return date('M', strtotime("January +{$value} months"));
		}
		
		return $value;
	}
	
	/**
	 * Get the lines to display from the aggregated data.
	 * @param Mongodloid_AggregatedCyrsor $cursor - Cursor of the aggregation query.
	 * @param array $groupKeys - Keys of the grouping.
	 * @return array of lines to display.
	 */
	protected function getAggregatedLines($cursor, $groupKeys) {
		$ret = array();
		// Go through the items and construct aggregated entities.
		foreach ($cursor as $item) {
			$values = $item->getRawData();
			foreach ($groupKeys as $key) {
				// Translate the values.
				$value = $this->translateAggregatedValue($key, $values['_id'][$key]);
				
				// TODO: The 'group_by' constant should perheps move to a more fitting location.
				$item->set('group_by' . '.' . $key, $value, true);
			}
			$item->set('_id', new MongoId(), true);
			$ret[] = $item;
		}
		
		return $ret;
	}
	
	/**
	 * Get the aggregated data to show.
	 * @param array $filterQuery - Query to get the aggregated data for.
	 * @return aray - Mongo entities to return.
	 */
	public function getAggregateData($filterQuery = array()) {
		if (empty($filterQuery[0]['$match'])) {
			unset($filterQuery[0]);
			$filterQuery = array_values($filterQuery); // reset array index (required for aggregate framework)
			$indexGroup = 0;
		} else {
			$indexGroup = count($filterQuery) - 1;
		}
		
		$cursor = $this->collection->aggregatecursor($filterQuery)
			->sort($this->sort)->skip($this->offset())->limit($this->size);
		
		$groupKeys = array_keys($filterQuery[$indexGroup]['$group']['_id']);
				
		$ret = $this->getAggregatedLines($cursor, $groupKeys);
		
		$this->_count = count($ret);// Billrun_Factory::config()->getConfigValue('admin_panel.lines.global_limit', 10000);
		return $ret;
	}
	
	/**
	 * method to get data aggregated
	 * 
	 * @param array $filter_query what to filter by
	 * @param array $aggregate what to aggregate by
	 * 
	 * @return array of result
	 */
	public function getDataAggregated($filter_query = array(), $aggregate = array()) {

		$cursor = $this->collection->aggregatecursor($filter_query, $aggregate);

		if (isset($filter_query['$and']) && $this->filterExists($filter_query['$and'], array('aid', 'sid', 'stamp'))) {
			$this->_count = $cursor->count(false);
		} else {
			$this->_count = Billrun_Factory::config()->getConfigValue('admin_panel.lines.global_limit', 10000);
		}

		$ret = array();
		foreach ($cursor as $item) {
//			$item->collection($this->lines_coll);
//			if ($arate = $this->getDBRefField($item, 'arate')) {
//				$item['arate'] = $arate['key'];
//				$item['arate_id'] = strval($arate['_id']);
//			} else {
//				$item['arate'] = $arate;
//			}
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
			'type' => 'Type',
			'aid' => 'Account',
			'sid' => 'Subscriber',
			'calling_number' => 'Calling',
			'called_number' => 'Called',
			'plan' => 'Plan',
			'usaget' => 'Usage',
			'usagev' => 'Volume',
			'arate' => 'Rate',
			'aprice' => 'Charge',
			'billrun' => 'Billrun',
			'urt' => 'Time',
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
			'aid' => array(
				'key' => 'aid',
				'db_key' => 'aid',
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'Account id',
				'default' => '',
			),
			'sid' => array(
				'key' => 'sid',
				'db_key' => 'sid',
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'Subscriber id',
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
			'usage' => array(
				'key' => 'usage',
				'db_key' => 'usaget',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'display' => 'Usage',
				'values' => Billrun_Factory::config()->getConfigValue('admin_panel.line_usages'),
				'default' => array(),
			),
			'billrun' => array(
				'key' => 'billrun',
				'db_key' => 'billrun',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'display' => 'Billrun',
				'values' => $billruns,
				'default' => array(),
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
				'aid' => array(
					'width' => 2,
				),
				'sid' => array(
					'width' => 2,
				),
				'from' => array(
					'width' => 2,
				),
				'to' => array(
					'width' => 2,
				),
			),
			1 => array(
				'usage' => array(
					'width' => 2,
				),
				'billrun' => array(
					'width' => 2,
				),
			),
		);
		return $filter_field_order;
	}

	public function getSortFields() {
		return array(
			'aid' => 'Account id',
			'billrun_key' => 'Billrun',
			'aprice' => 'Charge',
			'plan' => 'Plan',
			'process_time' => 'Process time',
			'sid' => 'Subscriber id',
			'urt' => 'Time',
			'type' => 'Type',
			'usaget' => 'Usage type',
			'usagev' => 'Usage volume',
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
	
	public function getActivity($sids, $from_date, $to_date, $include_outgoing, $include_incoming, $include_sms) {
		if (!is_array($sids)) {
			settype($sids, 'array');
		}
		$query = array(
			'sid' => array(
				'$in' => $sids,
			),
			'usaget' => array('$in' => array()),
		);
		
		if ($include_incoming) {
			$query['usaget']['$in'][] = 'incoming_call';
		}
		
		if ($include_outgoing) {
			$query['usaget']['$in'][] = 'call';
		}
		
		if ($include_sms) {
			$query['usaget']['$in'][] = 'sms';
		}
		
		$query['urt'] = array(
			'$lte' => new MongoDate($to_date),
			'$gte' => new MongoDate($from_date),
		);

		$cursor = $this->collection->query($query)->cursor()->limit(100000)->sort(array('urt' => 1));
		$ret = array();
		
		foreach($cursor as $row) {
			$incoming = preg_match('/^incoming_/',$row['usaget']);
			$ret[] = array(
				'date' => date(Billrun_Base::base_dateformat, $row['urt']->sec),
				'called_number' => $row['called_number'],
				'calling_number' => $row['calling_number'],
				'usagev' => !empty($row['usagev']) ? $row['usagev'] : $row['duration'],
				'usaget' => $row['usaget'],
				'subs_first_ci' =>  $incoming ? $row['called_subs_first_ci'] : $row['calling_subs_first_ci'],
				'subs_first_lac' => $incoming ? hexdec($row['called_subs_first_lac']) : hexdec($row['calling_subs_first_lac']),
				'subs_last_ci' => $incoming ?  $row['called_subs_last_ci'] : $row['calling_subs_last_ci'],
				'subs_last_lac' => $incoming ? hexdec($row['called_subs_last_lac']) : hexdec($row['calling_subs_last_lac'])  ,
			);
		}
		
		return $ret;

	}
	
	public function remove($params) {
		// first remove line from queue (collection) than from lines collection (parent)
		Billrun_Factory::db()->queueCollection()->remove($params);
		return parent::remove($params);
	}
	
	public static function getArchiveLinesCollections() {
		try {
			$archiveDb = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('archive.db'));
			$filtered_collections = $archiveDb->getCollectionNames(array('filter' => array('name' => array('$regex' => '^lines'))));
			sort($filtered_collections);
		} catch (Exception $ex) {
			return array();
		}
		return $filtered_collections;
	}
	
	public static function getHistoryArchiveLinesCollections() {
		try {
			$historyArchiveDb = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('historyarchive.db'));
			$filtered_collections = $historyArchiveDb->getCollectionNames(array('filter' => array('name' => array('$regex' => '^lines'))));
			sort($filtered_collections);
		} catch (Exception $ex) {
			return array();
		}
		return $filtered_collections;
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
