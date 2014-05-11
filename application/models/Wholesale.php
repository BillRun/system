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
	 *
	 * @var type 
	 */
	protected $db;

	public function __construct() {
		$db = Billrun_Factory::config()->getConfigValue('wholesale.db');
		$this->db = Zend_Db::factory('Pdo_Mysql', array(
					'host' => $db['host'],
					'username' => $db['username'],
					'password' => $db['password'],
					'dbname' => $db['name']
		));
	}

	public function getStats($group_field, $from_day, $to_day, $report_type = null) {
		if ($report_type) {
			$table_data = $this->convertToAssocArray($this->getCall($report_type == 'incoming_call' ? 'TG' : 'FG', $group_field, $from_day, $to_day), 'group_by');
			return array(
				'table_data' => array($report_type => $table_data),
				'available_group_values' => $this->getAvailableGroupValues($table_data),
			);
		}
		$incoming_call = $this->convertToAssocArray($this->getCall('TG', $group_field, $from_day, $to_day), 'group_by');
		$outgoing_call = $this->convertToAssocArray($this->getCall('FG', $group_field, $from_day, $to_day), 'group_by');

		$ret = array(
			'incoming_call' => array(
				'table_data' => array('incoming_call' => $incoming_call),
				'available_group_values' => $this->getAvailableGroupValues($incoming_call),
			),
			'outgoing_call' => array(
				'table_data' => array('outgoing_call' => $outgoing_call),
				'available_group_values' => $this->getAvailableGroupValues($outgoing_call),
			),
			'nr' => $this->getNrStats($group_field, $from_day, $to_day),
		);
		return $ret;
	}

	protected function getAvailableGroupValues() {
		$ret = array();
		$arg_list = func_get_args();
		foreach ($arg_list as $data) {
			$ret = array_merge($ret, array_keys($data));
		}
		$ret = array_unique($ret);
		asort($ret);
		return $ret;
	}

	public function getNrStats($group_field, $from_day, $to_day, $carrier = null) {
		$incoming_call = $this->convertToAssocArray($this->getCall('TG', $group_field, $from_day, $to_day, $carrier, 'nr'), 'group_by');
		$outgoing_call = $this->convertToAssocArray($this->getCall('FG', $group_field, $from_day, $to_day, $carrier, 'nr'), 'group_by');
		$data = $this->convertToAssocArray($this->getData($group_field, $from_day, $to_day, $carrier), 'group_by');
		$ret = array(
			'table_data' => array(
				'incoming_call' => $incoming_call,
				'outgoing_call' => $outgoing_call,
				'data' => $data,
			),
		);
		$ret['available_group_values'] = $this->getAvailableGroupValues($incoming_call, $outgoing_call, $data);
		return $ret;
	}

	protected function convertToAssocArray($source_array, $row_field) {
		foreach ($source_array as $index => $row) {
			$source_array[$row[$row_field]] = $row;
			unset($source_array[$index]);
		}
		return $source_array;
	}

	/**
	 * 
	 * @param string $direction FG
	 * @param string $network nr or empty
	 * 
	 * @return array of results
	 */
	public function getData($group_field, $from_day, $to_day, $carrier = null) {
		$query = 'SELECT ' . ($group_field == 'carrier' ? 'cgr_compressed.longname' : 'dayofmonth') . ' AS group_by, usaget, sum(duration) AS duration, round(sum(duration)/pow(1024,2)*0.0297,2) AS cost '
				. 'FROM wholesale left join cgr_compressed ON wholesale.carrier=cgr_compressed.shortname '
				. 'WHERE usaget like "data" AND wholesale.carrier NOT IN ("GT", "OTHER") AND dayofmonth BETWEEN "' . $from_day . '" AND "' . $to_day . '" ';
		if ($carrier) {
			$query .= ' AND longname LIKE "' . $carrier . '"';
		}
		$query.= 'GROUP by group_by';

		$data = $this->db->fetchAll($query);
		foreach ($data as &$row) {
			if (isset($row['cost'])) {
				$row['cost'] = floatval($row['cost']);
			}
			if (isset($row['duration'])) {
				$row['duration'] = $row['duration'] / pow(1024, 2);
			}
		}
		return $data;
	}

	public function getCall($direction, $group_field, $from_day, $to_day, $carrier = null, $network = 'all') {
		$sub_query = 'SELECT usaget, dayofmonth, longname as carrier, sum(duration) as seconds,'
				. 'CASE WHEN carrier like "N%" and direction like "TG" THEN sum(duration)/60*0.0614842117289702 ELSE sum(duration)/60*0.0614842117289702 END as cost'
				. ' FROM wholesale left join cgr_compressed on wholesale.carrier=cgr_compressed.shortname'
				. ' WHERE direction like "' . $direction . '" AND network like "' . $network . '" AND dayofmonth BETWEEN "' . $from_day . '" AND "' . $to_day . '"'
				. ' GROUP BY dayofmonth,wholesale.carrier,usaget,direction'
				. ' ORDER BY usaget,dayofmonth,longname';

		$query = 'SELECT ' . $group_field . ' AS group_by, usaget ,sum(seconds) as duration, round(sum(cost),2) as cost from (' . $sub_query . ') as sq';

		if ($carrier) {
			$query .= ' WHERE carrier LIKE "' . $carrier . '"';
		}

		$query .= ' GROUP BY ' . $group_field;

		$callData = $this->db->fetchAll($query);
		foreach ($callData as &$row) {
			if (isset($row['cost'])) {
				$row['cost'] = floatval($row['cost']);
			}
			if (isset($row['duration'])) {
				$row['duration'] = $row['duration'] / 3600;
//				$hours = floor($row['duration'] / 3600);
//				$minutes = floor(($row['duration'] / 60) % 60);
//				$seconds = $row['duration'] % 60;
//				$row['duration'] = str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($seconds, 2, '0', STR_PAD_LEFT);
			}
		}
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

	public function getTblParams($report_type = null) {
		$reports = array(
			'incoming_call' => array(
				'title' => 'Incoming',
				'default' => true,
				'direction' => 'TG',
				'group_by_field' => 'group_by',
				'fields' => array(
					'incoming_call' => array(
						0 => array(
							'value' => 'duration',
							'display' => 'duration',
							'decimal' => 0,
							'label' => 'Duration (hours)',
						),
						1 => array(
							'value' => 'cost',
							'display' => 'cost',
							'decimal' => 2,
							'label' => 'Cost',
						),
					),
				),
			),
			'outgoing_call' => array(
				'title' => 'Outgoing',
				'direction' => 'FG',
				'group_by_field' => 'group_by',
				'fields' => array(
					'outgoing_call' => array(
						0 => array(
							'value' => 'duration',
							'display' => 'duration',
							'decimal' => 0,
							'label' => 'Duration (hours)',
						),
						1 => array(
							'value' => 'cost',
							'display' => 'cost',
							'decimal' => 2,
							'label' => 'Cost',
						),
					),
				),
			),
			'nr' => array(
				'title' => 'National roaming',
				'direction' => 'Nr',
				'group_by_field' => 'group_by',
				'fields' => array(
					'incoming_call' => array(
						0 => array(
							'value' => 'duration',
							'display' => 'duration',
							'decimal' => 0,
							'label' => 'Duration (hours)',
						),
						1 => array(
							'value' => 'cost',
							'display' => 'cost',
							'decimal' => 2,
							'label' => 'Cost',
						),
					),
					'outgoing_call' => array(
						0 => array(
							'value' => 'duration',
							'display' => 'duration',
							'decimal' => 0,
							'label' => 'Duration (hours)',
						),
						1 => array(
							'value' => 'cost',
							'display' => 'cost',
							'decimal' => 2,
							'label' => 'Cost',
						),
					),
					'data' => array(
						0 => array(
							'value' => 'duration',
							'display' => 'duration',
							'decimal' => 0,
							'label' => 'Volume (GB)'
						),
						1 => array(
							'value' => 'cost',
							'display' => 'cost',
							'decimal' => 2,
							'label' => 'Cost',
						),
					),
				),
			),
		);
		if ($report_type) {
			return array($report_type => $reports[$report_type]);
		} else {
			return $reports;
		}
	}

	public function getRetailData($from_day, $to_day) {
		$query = 'SELECT retail_extra.dayofmonth,retail_extra.over_plan,retail_extra.out_plan,retail_new.subsCount AS newSubs,'
				. 'retail_churn.subsCount AS churnSubs,sum(retail_active.subsCount) AS totalCustomer,sum(retail_active.totalCost) AS flatRateRevenue,'
				. 'simAggregated.simCount,simAggregated.totalSimCost FROM retail_extra LEFT JOIN retail_new ON retail_extra.dayofmonth=retail_new.dayofmonth '
				. 'LEFT JOIN retail_churn ON retail_extra.dayofmonth = retail_churn.dayofmonth '
				. 'LEFT JOIN retail_active ON retail_extra.dayofmonth=retail_active.dayofmonth '
				. 'LEFT JOIN '
				. '(SELECT dayofmonth,sum(simCount)-sum(cancelCount) AS simCount,sum(totalSimCost)-sum(totalCancelCost) AS totalSimCost '
				. 'FROM retail_sim GROUP BY dayofmonth) AS simAggregated '
				. 'ON retail_extra.dayofmonth=simAggregated.dayofmonth '
				. 'WHERE retail_active.planName IS NOT NULL AND retail_extra.dayofmonth BETWEEN "' . $from_day . '" AND "' . $to_day . '"';

		$data = $this->db->fetchAll($query);
		return $data;
	}

	public function getRetailTableParams() {
		return array(
			'title' => 'Retail',
			'fields' => array(
				array(
					'value' => 'dayofmonth',
					'display' => 'dayofmonth',
					'label' => 'Day of month',
					'decimal' => false,
				),
				array(
					'value' => 'newSubs',
					'display' => 'newSubs',
					'decimal' => 0,
					'label' => 'New customers',
				),
				array(
					'value' => 'churnSubs',
					'display' => 'churnSubs',
					'decimal' => 0,
					'label' => 'Churn total',
				),
				array(
					'value' => 'totalCustomer',
					'display' => 'totalCustomer',
					'decimal' => 0,
					'label' => 'Total customer end of period',
				),
				array(
					'value' => 'simCount',
					'display' => 'simCount',
					'decimal' => 0,
					'label' => 'Sim count',
				),
				array(
					'value' => 'totalSimCost',
					'display' => 'totalSimCost',
					'decimal' => 2,
					'label' => 'Sending fees',
				),
				array(
					'value' => 'flatRateRevenue',
					'display' => 'flatRateRevenue',
					'decimal' => 2,
					'label' => 'Flat rate revenue',
				),
				array(
					'value' => 'over_plan',
					'display' => 'over_plan',
					'decimal' => 2,
					'label' => 'Extra flat rate revenue (over)',
				),
				array(
					'value' => 'out_plan',
					'display' => 'out_plan',
					'decimal' => 2,
					'label' => 'Extra flat rate revenue (out)',
				),
			)
		);
	}

}

