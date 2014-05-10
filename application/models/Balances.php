<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
		$params['db'] = 'balances';
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
			'time' => Billrun_Util::getStartTime($billrun),
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
		return $this->collection->query($query)->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'))->hint(array('aid' => 1, 'billrun_month' => 1))->limit($this->size);
	}
	
	protected function getBalancesFields() {
		$basic_columns = Billrun_Factory::config()->getConfigValue('admin_panel.balances.table_columns');
		$extra_columns = Billrun_Factory::config()->getConfigValue('admin_panel.balances.extra_columns');
		
		return array_merge($basic_columns, $extra_columns);
	}


	public function getFilterFields() {
		$months = 6;
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

		$usage_filter_values = $this->getBalancesFields();
		unset($usage_filter_values['aid'], $usage_filter_values['sid'], $usage_filter_values['billrun_month']);
//		$usage_filter_values = array_merge($basic_columns, $extra_columns);
		
		$operators = array(
			'equals' => '=',
			'lt' => '<',
			'lte' => '<=',
			'gt' => '>',
			'gte' => '>=',
		);

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
			'usage_type' => array(
				'key' => 'manual_key',
				'db_key' => 'nofilter',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'display' => 'Usage',
				'values' => $usage_filter_values,
				'singleselect' => 1,
				'default' => array(),
			),
			'usage_filter' => array(
				'key' => 'manual_operator',
				'db_key' => 'nofilter',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'display' => '',
				'values' => $operators,
				'singleselect' => 1,
				'default' => array(),
			),
			'usage_value' => array(
				'key' => 'manual_value[]',
				'db_key' => 'nofilter',
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => '',
				'default' => '',
			),
			'billrun' => array(
				'key' => 'billrun',
				'db_key' => 'billrun_month',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'display' => 'Billrun',
				'values' => $billruns,
				'default' => array(),
			),
		);
		return array_merge($filter_fields, parent::getFilterFields());
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
			),
			1 => array(
				'billrun' => array(
					'width' => 2,
				),
			),
			2 => array(
				'usage_type' => array(
					'width' => 2,
				),
				'usage_filter' => array(
					'width' => 1,
				),
				'usage_value' => array(
					'width' => 1,
				),

			)
		);
		return $filter_field_order;
	}

	public function getData($filter_query = array()) {
		$resource = parent::getData($filter_query);
		$this->_count = $resource->count(false);
		return $resource;
	}
	
	public function getSortFields() {
		return $this->getBalancesFields();
	}

}
