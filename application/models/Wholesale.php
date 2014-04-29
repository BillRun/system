<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2014 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Configmodel class
 *
 * @package  Models
 * @since    2.1
 */
class WholesaleModel {

	/**
	 * the collection the config run on
	 * 
	 * @var Mongodloid Collection
	 */
	protected $collection;

	/**
	 * Mappings from circuit groups to names
	 * @var array
	 */
	protected $cgrs;

	public function __construct() {
		// load the config data from db
		$this->collection = Billrun_Factory::db(array('name' => 'billrun'))->wholesaleCollection();
	}

	public function getStats($group_field, $from_day, $to_day) {
		$incoming_call_data = $this->getCall('TG', $group_field, $from_day, $to_day);
		$outgoing_call_data = $this->getCall('FG', $group_field, $from_day, $to_day);
		if ($group_field == 'carrier') {
			$incoming_call_data = $this->AddCGRName($incoming_call_data, 'group_by', 'carrier');
			$outgoing_call_data = $this->AddCGRName($outgoing_call_data, 'group_by', 'carrier');
		}
		return array(
			'incoming_call' => $incoming_call_data,
			'outgoing_call' => $outgoing_call_data,
		);
	}

	public function getNameByCgr($cgr) {
		if (is_null($cgr)) {
			return '';
		}
		if (!isset($this->cgrs)) {
			$this->cgrs = $this->getCgr();
		}
		if (isset($this->cgrs[$cgr])) {
			return $this->cgrs[$cgr];
		} else {
			return $cgr;
		}
	}

	public function getCgr() {
		$cursor = Billrun_Factory::db(array('name' => 'billrun'))->cgrCollection()->query()->cursor();
		$ret = array();
		foreach ($cursor as $document) {
			$ret[$document['shortname']] = $document['longname'];
		}
		return $ret;
	}

	public function AddCGRName($data, $cgr_field, $cgr_name_field) {
		foreach ($data as &$row) {
			$row[$cgr_name_field] = $this->getNameByCgr($row[$cgr_field]);
		}
		return $data;
	}

	/**
	 * 
	 * @param string $direction FG
	 * @param string $network nr or empty
	 * 
	 * @return array of results
	 */
	public function getCall($direction, $group_field, $from_day, $to_day, $carrier = null, $network = 'all') {
		$match = array(
			'$match' => array(
				'dayofmonth' => array('$gte' => $from_day, '$lte' => $to_day),
				'direction' => $direction,
				'network' => $network,
			),
		);
		if ($carrier) {
			$match['$match']['carrier'] = $carrier;
		}
		$group = array(
			'$group' => array(
				'_id' => array(
					'group_by' => '$' . $group_field,
					'usaget' => '$usaget',
				),
				'duration' => array(
					'$sum' => '$duration',
				),
			)
		);
		$project = array(
			'$project' => array(
				'_id' => 0,
				'group_by' => '$_id.group_by',
				'usaget' => '$_id.usaget',
				'duration' => 1,
			)
		);

		$sort = array(
			'$sort' => array(
				'group_by' => 1,
				'usaget' => 1,
			),
		);

//		$usaget_group = array(
//			'$group' => array(
//				
//			),
//		);
		$callData = $this->collection->aggregate($match, $group, $project, $sort);

		return $callData;
	}

	public function getGroupFields() {
		$group_fields = array(
			'group_by' => array(
				'key' => 'group_by',
				'input_type' => 'select',
				'display' => 'Group by',
				'values' => array('dayofmonth' => array('display' => 'Day of month', 'popup' => 'carrier'), 'carrier' => array('display' => 'Carrier', 'popup' => 'dayofmonth')),
				'default' => 'dayofmonth',
			),
		);
		return $group_fields;
	}

	public function getFilterFields() {
		$filter_fields = array(
			'from' => array(
				'key' => 'from_day',
				'db_key' => 'dayofmonth',
				'input_type' => 'date',
				'comparison' => '$gte',
				'display' => 'From day',
				'default' => (new Zend_Date(strtotime('60 days ago'), null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd'),
			),
			'to' => array(
				'key' => 'to_day',
				'db_key' => 'dayofmonth',
				'input_type' => 'date',
				'comparison' => '$lte',
				'display' => 'To day',
				'default' => (new Zend_Date(time(), null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd'),
			),
		);
		return $filter_fields;
	}

}
