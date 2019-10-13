<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Balances model class
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class BalancesModel extends TableModel {

	public function __construct(array $params = array()) {
		$params['collection'] = 'balances';
		//$params['db'] = 'balances';
		parent::__construct($params);
		$this->search_key = "stamp";
	}

	/**
	 * method to receive the balances lines that over requested date usage
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	public function getBalancesVolume($plan, $data_usage, $from_account_id, $to_account_id, $billrun) {
		$params = array(
			'name' => $plan,
			'time' => Billrun_Billingcycle::getStartTime($billrun),
		);
		$plan_id = Billrun_Factory::plan($params);
		$id = $plan_id->get('_id')->getMongoID();
		$data_usage_bytes = Billrun_Util::megabytesToBytesFormat((int) $data_usage);

		$query = array(
			'aid' => array('$gte' => (int) $from_account_id, '$lte' => (int) $to_account_id),
			'billrun_month' => $billrun,
			'balance.totals.data.usagev' => array('$gt' => (float) $data_usage_bytes),
			'current_plan' => Billrun_Factory::db()->plansCollection()->createRef($id),
		);
//		print_R($query);die;
		return $this->collection->query($query)->cursor()->hint(array('aid' => 1, 'billrun_month' => 1))->limit($this->size);
	}

	protected function getBalancesFields() {
		$basic_columns = Billrun_Config::getInstance()->getConfigValue('admin_panel.balances.table_columns', array());
		$extra_columns = Billrun_Config::getInstance()->getConfigValue('admin_panel.balances.extra_columns', array());
		return array_merge($basic_columns, $extra_columns);
	}

	public function getFilterFields() {
		$months = 6;
		$billruns = array();
		$timestamp = time();
		for ($i = 0; $i < $months; $i++) {
			$billrun_key = Billrun_Billingcycle::getBillrunKeyByTimestamp($timestamp);
			if ($billrun_key >= '201401') {
				$billruns[$billrun_key] = $billrun_key;
			} else {
				break;
			}
			$timestamp = strtotime("1 month ago", $timestamp);
		}
		arsort($billruns);

//		$plansModel = new PlansModel();
//		$plansCursor = $plansModel->getData();
//		$plans = array();
//		foreach ($plansCursor as $p) {
//			$plans[(string) $p->getId()->getMongoID()] = $p["name"];
//		}

		$usage_filter_values = $this->getBalancesFields();
		unset($usage_filter_values['aid'], $usage_filter_values['sid'], $usage_filter_values['billrun_month'], $usage_filter_values['current_plan']);
//		$usage_filter_values = array_merge($basic_columns, $extra_columns);

		$names = Billrun_Factory::db()->plansCollection()->query()->cursor()->sort(array('name' => 1));
		$planNames = array();
		foreach ($names as $name) {
			$planNames[$name['name']] = $name['name'];
		}
		$operators = array(
			'equals' => '=',
			'lt' => '<',
			'lte' => '<=',
			'gt' => '>',
			'gte' => '>=',
		);
		$date = new Zend_Date(null, null, new Zend_Locale('he_IL'));
		$date->set('00:00:00', Zend_Date::TIMES);
		$filter_fields = array(
			'sid' => array(
				'key' => 'sid',
				'db_key' => 'sid',
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'Subscriber No',
				'default' => '',
			),
			'aid' => array(
				'key' => 'aid',
				'db_key' => 'aid',
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'BAN',
				'default' => '',
			),
//			'usage_type' => array(
//				'key' => 'manual_key',
//				'db_key' => 'nofilter',
//				'input_type' => 'multiselect',
//				'display' => 'Usage',
//				'values' => $usage_filter_values,
//				'singleselect' => 1,
//				'default' => array(),
//			),
//			'usage_filter' => array(
//				'key' => 'manual_operator',
//				'db_key' => 'nofilter',
//				'input_type' => 'multiselect',
//				'display' => '',
//				'values' => $operators,
//				'singleselect' => 1,
//				'default' => array(),
//			),
//			'usage_value' => array(
//				'key' => 'manual_value',
//				'db_key' => 'nofilter',
//				'input_type' => 'number',
//				'display' => '',
//				'default' => '',
//			),
			'plan' => array(
				'key' => 'plan',
				'db_key' => 'current_plan',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'ref_coll' => 'plans',
				'ref_key' => 'name',
				'display' => 'Plan',
				'values' => $planNames,
				'default' => array(),
			),
			'date' => array(
				'key' => 'date',
				'db_key' => array('from', 'to'),
				'input_type' => 'date',
				'comparison' => array('$lte', '$gte'),
				'display' => 'Date',
				'default' => $date->toString('YYYY-MM-dd HH:mm:ss'),
			),
		);
		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			0 => array(
				'sid' => array(
					'width' => 2,
				),
				'aid' => array(
					'width' => 2,
				),
			),
			2 => array(
				'plan' => array(
					'width' => 2,
				),
				'date' => array(
					'width' => 2,
				),
			),
//			3 => array(
//				'usage_type' => array(
//					'width' => 2,
//				),
//				'usage_filter' => array(
//					'width' => 1,
//				),
//				'usage_value' => array(
//					'width' => 1,
//				),
//			)
		);
		return $filter_field_order;
	}

	public function getData($filter_query = array()) {
		$resource = parent::getData($filter_query);
		$ret = array();
//		$aggregate = Billrun_Config::getInstance()->getConfigValue('admin_panel.balances.aggregate', false);
//		$aggregate = $conf['admin_panel']['balances']['aggregate'];
		foreach ($resource as $item) {
			if (Billrun_Config::getInstance()->getConfigValue('admin_panel.balances.aggregate', 0)) {
				$totals = array();
				$units = array();
				if (isset($item['balance']['totals'])) {
					foreach ($item['balance']['totals'] as $key => $val) {
						$unit = $item['charging_by_usaget_unit'];
						if (isset($val['cost'])) {
							$totals[] = $val['cost'];
							$units[] = $unit;
						} else if (isset($val['usagev'])) {
							$totals[] = $val['usagev'];
							$units[] = $unit;
						}
					}
				}
				if (isset($item['balance']['cost'])) {
					$totals[] = $item['balance']['cost'];
					$units[] = Billrun_Util::getUsagetUnit('cost');
				}
				$item['totals'] = implode(',', $totals);
				$item['units'] = implode(',', $units);
				$query = array_merge(Billrun_Utils_Mongo::getDateBoundQuery(), array('sid' => $item['sid']));
				$subscriber = Billrun_Factory::subscriber()->load($query);
				if (isset($subscriber['service_provider'])) {
					$item['service_provider'] = $subscriber['service_provider'];
				}
				if (isset($subscriber['plan'])) {
					$item['plan'] = $subscriber['plan'];
				}
			}
			if ($current_plan = $this->getDBRefField($item, 'current_plan')) {
				$item['current_plan'] = $current_plan['name'];
			}
			$this->setRecurringData($item);
			$ret[] = $item;
		}

		$this->_count = $resource->count(false);
		return $ret;
	}
	
	protected function setRecurringData(&$item) {
		if (!$item['recurring']) {
			$item['recurring'] = false;
			$item['next_renew_date'] = '';
			return;
		}
		$item['recurring'] = true;
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query['sid'] = $item['sid'];
		$query['include'][$item['charging_by_usaget']]['pp_includes_name'] = $item['pp_includes_name'];
		$autoRenew = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection()->query()->cursor()->sort(array('next_renew_date' => 1))->limit(1)->current();
		$item['next_renew_date'] = (!$autoRenew->isEmpty() ? $autoRenew->get('next_renew_date') : '');
	}

	public function getSortFields() {
		return $this->getBalancesFields();
	}

}
