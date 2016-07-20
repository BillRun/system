<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2014 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
	protected $plans = array();

	public function __construct() {
		$db = Billrun_Factory::config()->getConfigValue('wholesale.db');
		$this->db = Zend_Db::factory('Pdo_Mysql', array(
					'host' => $db['host'],
					'username' => $db['username'],
					'password' => $db['password'],
					'dbname' => $db['name']
		));
	}

	public function getStats($group_field, $from_day, $to_day, $report_type = null, $carrier = null) {
		if ($report_type) {
			$table_data = $this->convertToAssocArray($this->getCall($report_type == 'incoming_call' ? 'TG' : 'FG', $group_field, $from_day, $to_day, $carrier), 'group_by');
			return array(
				'table_data' => array($report_type => $table_data),
				'available_group_values' => $this->getAvailableGroupValues($table_data),
			);
		}
		$incoming_call = $this->convertToAssocArray($this->getCall('TG', $group_field, $from_day, $to_day, $carrier), 'group_by');
		$outgoing_call = $this->convertToAssocArray($this->getCall('FG', $group_field, $from_day, $to_day, $carrier), 'group_by');

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
		$unique_ret = array_unique($ret);
		asort($unique_ret);
		return $unique_ret;
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
		$query = 'SELECT ' . ($group_field == 'carrier' ? 'cgr_compressed.company_name' : 'dayofmonth') . ' AS group_by, usaget, sum(duration) AS duration, round(sum(duration)/pow(1024,2)*0.0297,2) AS cost '
				. 'FROM wholesale left join cgr_compressed ON wholesale.carrier=cgr_compressed.carrier '
				. 'WHERE usaget like "data" AND wholesale.carrier NOT IN ("GT", "OTHER") AND dayofmonth BETWEEN "' . $from_day . '" AND "' . $to_day . '" ';
		if ($carrier) {
			$query .= ' AND company_name LIKE "' . $carrier . '"';
		}
		$query.= 'GROUP by group_by';

		$data = $this->db->fetchAll($query);
		foreach ($data as &$row) {
			if (isset($row['cost'])) {
				$row['cost'] = floatval($row['cost']);
			}
			if (isset($row['duration'])) {
				$row['duration'] = $row['duration'] / pow(1024, 3);
			}
		}
		return $data;
	}

	public function getCall($direction, $group_field, $from_day, $to_day, $carrier = null, $network = 'all') {
		$sub_query = 'SELECT usaget, dayofmonth, company_name as carrier, sum(duration) as seconds,'
				. 'CASE WHEN network like "nr" THEN sum(duration)/60*0.053'
				. ' WHEN wholesale.carrier like "N%" and direction like "FG" THEN sum(duration)/60*0.0101002109924085'
				. ' WHEN wholesale.carrier like "I%" and direction like "FG" THEN sum(duration)/60*-0.0614842117289702'
				. ' ELSE sum(duration)/60*0.0614842117289702 END as cost'
				. ' FROM wholesale left join cgr_compressed on wholesale.carrier=cgr_compressed.carrier'
				. ' WHERE'
				. ' wholesale.carrier NOT IN("DDWW", "DKRT", "GPRT", "GT", "LALC", "LCEL", "NSML", "PCTI", "POPC") AND' // temporary exclude these carriers until Dror explains them
				. ' direction like "' . $direction . '" AND network like "' . $network . '" AND dayofmonth BETWEEN "' . $from_day . '" AND "' . $to_day . '"'
				. ' GROUP BY dayofmonth,wholesale.carrier,usaget,direction'
				. ' ORDER BY usaget,dayofmonth,company_name';

		$query = 'SELECT ' . $group_field . ' AS group_by, usaget ,sum(seconds) as duration, round(sum(cost),2) as cost from (' . $sub_query . ') as sq';

		if ($carrier) {
			$query .= ' WHERE wholesale.carrier LIKE "' . $carrier . '"';
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
							'label' => 'Charge',
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
		$query = 'SELECT retail_extra.dayofmonth,SUM(retail_promotions_agg.totalCost) as promotionsCost, SUM(retail_extra.over_plan) as over_plan,SUM(retail_new_subscribers.count) as newSubsNotNp,SUM(retail_extra.out_plan) AS out_plan,SUM(retail_new.subsCount) AS newSubs,'
				. 'SUM(retail_churn.subsCount) AS churnSubs,SUM(retail_active_agg.totalCost) AS flatRateRevenue,';
		$query .= 'sum(retail_active_agg.' . implode('+retail_active_agg.', $this->getPlans()) . ') AS totalCustomer';
		foreach ($this->getPlans() as $planName) {
			$query.= ', retail_active_agg.' . $planName . ' as ' . $planName;
		}

		$query .= ',sum(retail_sim2.simCount) as simCountTotal, sum(retail_sim2.simCost) as simCostTotal, '
				. 'SUM(retail_sim2.upsCount) as upsCountTotal, SUM(retail_sim2.upsCost) as upsCostTotal, '
				. 'SUM(retail_sim2.simCount+retail_sim2.upsCount) as totalSimCountTotal, SUM(retail_sim2.simCost+retail_sim2.upsCost) as totalSimCostTotal,'
				. 'SUM(retail_unsubscribe.subsCount) as subsLeft, retail_active_agg.totalCost+retail_extra.over_plan+retail_extra.out_plan-retail_promotions_agg.totalCost+SUM(retail_sim2.simCost+retail_sim2.upsCost) as finalTotalAmount';
		$query.= ' FROM retail_extra LEFT JOIN retail_new ON retail_extra.dayofmonth=retail_new.dayofmonth '
				. 'LEFT JOIN retail_new_subscribers ON retail_extra.dayofmonth = retail_new_subscribers.dayofmonth '
				. 'LEFT JOIN retail_churn ON retail_extra.dayofmonth = retail_churn.dayofmonth '
				. 'LEFT JOIN (SELECT dayofmonth';

		foreach ($this->getPlans() as $planName) {
			$query.= ', SUM(IF(retail_active.planName="' . $planName . '", retail_active.subsCount, 0)) as ' . $planName;
		}

		$query .= ', sum(totalCost) AS totalCost FROM retail_active GROUP BY dayofmonth) AS retail_active_agg ON retail_extra.dayofmonth=retail_active_agg.dayofmonth '
				. 'LEFT JOIN retail_sim2 ON retail_extra.dayofmonth=retail_sim2.dayofmonth '
				. 'LEFT JOIN retail_unsubscribe on retail_extra.dayofmonth=retail_unsubscribe.dayofmonth '
				. 'LEFT JOIN (SELECT dayofmonth, SUM(totalCost) AS totalCost from retail_promotions GROUP BY dayofmonth) AS retail_promotions_agg on retail_extra.dayofmonth=retail_promotions_agg.dayofmonth '
				. 'WHERE retail_extra.dayofmonth BETWEEN "' . $from_day . '" AND "' . $to_day . '" '
				. 'GROUP BY retail_extra.dayofmonth';
//		print $query;die;
		$data = $this->db->fetchAll($query);
		return $data;
	}

	protected function getPlans() {
		if (empty($this->plans)) {
			$query = 'SELECT plan,planName FROM retail_active WHERE planName IS NOT NULL GROUP BY planName ORDER by plan;';
			$this->plans = $this->db->fetchPairs($query);
		}
		return $this->plans;
	}

	protected function getSendingTypes() {
		return array(
			'Sim',
			'Ups',
		);
	}

	public function getRetailTableParams() {
		$plans = $this->getPlans();
		$retailTableParams = array(
			'title' => 'Retail',
			'fields' => array(
				array(
					'value' => 'dayofmonth',
					'display' => function ($dom) {
						return $dom . ' (' . date("D", strtotime($dom)) . ')';
					},
					'label' => 'Day of month',
					'decimal' => false,
				),
				array(
					'value' => 'totalCustomer',
					'display' => 'totalCustomer',
					'decimal' => 0,
					'label' => 'Count',
					'totals' => false,
					'commonColumn' => 'plans',
				),
				array(
					'value' => 'flatRateRevenue',
					'display' => 'flatRateRevenue',
					'decimal' => 2,
					'label' => 'Revenue',
					'totals' => false,
					'commonColumn' => 'plans',
				),
				array(
					'value' => 'promotionsCost',
					'display' => 'promotionsCost',
					'decimal' => 0,
					'label' => 'Promotions Cost',
					'totals' => FALSE,
				),
				array(
					'value' => 'subsLeft',
					'display' => 'subsLeft',
					'decimal' => 0,
					'label' => 'Subscribers waiting for disconnect',
				),
				array(
					'value' => 'over_plan',
					'display' => 'over_plan',
					'decimal' => 2,
					'label' => 'Over',
					'commonColumn' => 'extra',
				),
				array(
					'value' => 'out_plan',
					'display' => 'out_plan',
					'decimal' => 2,
					'label' => 'Out',
					'commonColumn' => 'extra',
				),
				array(
					'value' => 'newSubs',
					'display' => 'newSubs',
					'decimal' => 0,
					'label' => 'New subscribers',
					'commonColumn' => 'np',
				),
				array(
					'value' => 'churnSubs',
					'display' => 'churnSubs',
					'decimal' => 0,
					'label' => 'Churn total',
					'commonColumn' => 'np',
				),
				array(
					'value' => 'newSubsNotNp',
					'display' => 'newSubsNotNp',
					'decimal' => 0,
					'label' => 'New subs not NP',
				),
				array(
					'value' => 'totalSimCountTotal',
					'display' => 'totalSimCountTotal',
					'decimal' => 0,
					'label' => 'Sum',
					'commonColumn' => 'sim',
				),
				array(
					'value' => 'totalSimCostTotal',
					'display' => 'totalSimCostTotal',
					'decimal' => 0,
					'label' => 'Sim revenue',
				),
				array(
					'value' => 'finalTotalAmount',
					'display' => 'finalTotalAmount',
					'decimal' => 0,
					'label' => 'Final total amount',
					'totals' => false,
				),
			)
		);

//		foreach ($retailTableParams['fields'] as $index => $field) {
//			if ($field['value'] == 'totalCustomer') {
//				$totalCustomerIndex = $index;
//			}
//		}
//		if (isset($totalCustomerIndex)) {
//			foreach ($plans as $planName) {
//				$planFields[] = array(
//					'value' => $planName,
//					'display' => $planName,
//					'decimal' => 0,
//					'label' => $planName,
//					'totals' => false,
//					'commonColumn' => 'plans',
//				);
//			}
//			array_splice($retailTableParams['fields'], $totalCustomerIndex, 0, $planFields);
//		}
		foreach ($retailTableParams['fields'] as $index => $field) {
			if ($field['value'] == 'totalSimCountTotal') {
				$simCountIndex = $index;
			}
		}

		if (isset($simCountIndex)) {
			$sendingTypeFields = array(
				array(
					'value' => 'simCountTotal',
					'display' => 'simCountTotal',
					'decimal' => 0,
					'label' => 'sim',
					'commonColumn' => 'sim',
				),
				array(
					'value' => 'upsCountTotal',
					'display' => 'upsCountTotal',
					'decimal' => 0,
					'label' => 'ups',
					'commonColumn' => 'sim',
				)
			);
			array_splice($retailTableParams['fields'], $simCountIndex, 0, $sendingTypeFields);
		}
		return $retailTableParams;
	}

	public function getCommonColumns() {
		return array(
			'plans' => array(
				'label' => 'Total subscribers',
//				'colspan' => 1 + count($this->getPlans()),
				'colspan' => 2,
			),
			'sim' => array(
				'label' => 'Sim orders count',
				'colspan' => 1 + count($this->getSendingTypes()),
			),
			'extra' => array(
				'label' => 'Extra flat revenue',
				'colspan' => 2,
			),
			'np' => array(
				'label' => 'NP',
				'colspan' => 2,
			),
		);
	}
	
	/**
	 * method test wholesale data looking for inconsistencies
	 * 
	 */
	public function weeklyTestWholesale() {
		$db = Billrun_Factory::config()->getConfigValue('wholesale.db');
		$settings = Billrun_Factory::config()->getConfigValue('cron.wholesale');
		$this->db = Zend_Db::factory('Pdo_Mysql', array(
				'host' => $db['host'],
				'username' => $db['username'],
				'password' => $db['password'],
				'dbname' => $db['name']
		));
		$base_query = 'SELECT * FROM wholesale where product REGEXP "^IL" AND carrier REGEXP "^(M|N)" AND network = "all" '
			. 'AND duration > "' . $settings['duration']['minimum'] . '" ';
		$check_day_of_month = date("Y-m-d", strtotime($settings['checkingDay'] . ' days ago'));
		$ref_day_of_month = date("Y-m-d", strtotime(($settings['checkingDay'] + 7) . ' days ago'));

		$check_day_query = $base_query . ' AND dayofmonth ="' . $check_day_of_month . '"';
		$ref_day_query = $base_query . ' AND dayofmonth ="' . $ref_day_of_month . '"';
		$check_day_data = $this->db->fetchAll($check_day_query);
		if (empty($check_day_data)) {
			Billrun_Factory::log("wholesale error: " . $check_day_of_month . " whoelsale data is missing", Zend_Log::ERR);
		}
		$ref_day_data = $this->db->fetchAll($ref_day_query);

		$rearranged_check_day = $this->reorder_wholesale_data($check_day_data);
		$rearranged_ref_day = $this->reorder_wholesale_data($ref_day_data);
		foreach ($rearranged_check_day as $carrier => $carrier_lines) {
			foreach ($carrier_lines as $product => $line) {
				if (!isset($rearranged_ref_day[$carrier][$product])) {
					continue;
				}
				$ref_duration = $rearranged_ref_day[$carrier][$product]['duration'];
				$diff = abs($line['duration'] / $ref_duration - 1);
				if ($diff > $settings['duration']['diff']) {
					$rpMessage = 'wholesale warning: carrier ' . $carrier . ' product ' . $product . ' on ' . $check_day_of_month
						. ' differs by ' . round($diff * 100) . '%' . ' compared to ' . $ref_day_of_month;
					Billrun_Factory::log($rpMessage, Zend_Log::ERR);
				}
			}
		}
	}

	protected function reorder_wholesale_data($wholesale_data) {
		$rearanged = array();
		foreach ($wholesale_data as $wholesale_line) {
			$rearanged[$wholesale_line['carrier']][$wholesale_line['product']] = $wholesale_line;
		}
		return $rearanged;
	}

}

