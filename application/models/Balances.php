<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
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
		return $this->collection->query($query)->cursor()->hint(array('aid' => 1, 'billrun_month' => 1))->limit($this->size);
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

//		$plansModel = new PlansModel();
//		$plansCursor = $plansModel->getData();
//		$plans = array();
//		foreach ($plansCursor as $p) {
//			$plans[(string) $p->getId()->getMongoID()] = $p["name"];
//		}
		
		$usage_filter_values = $this->getBalancesFields();
		unset($usage_filter_values['aid'], $usage_filter_values['sid'], $usage_filter_values['billrun_month'], $usage_filter_values['current_plan']);
//		$usage_filter_values = array_merge($basic_columns, $extra_columns);
		
		$planNames = array_unique(array_keys(Billrun_Plan::getPlans()['by_name']));
		$planNames = array_combine($planNames, $planNames);
		
		$operators = array(
			'equals' => '=',
			'lt' => '<',
			'lte' => '<=',
			'gt' => '>',
			'gte' => '>=',
		);

		$filter_fields = array(
			'sid' => array(
				'key' => 'sid',
				'db_key' => 'sid',
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'Subscriber id',
				'default' => '',
			),
			'aid' => array(
				'key' => 'aid',
				'db_key' => 'aid',
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'Account id',
				'default' => '',
			),
			'usage_type' => array(
				'key' => 'manual_key',
				'db_key' => 'nofilter',
				'input_type' => 'multiselect',
				'display' => 'Usage',
				'values' => $usage_filter_values,
				'singleselect' => 1,
				'default' => array(),
			),
			'usage_filter' => array(
				'key' => 'manual_operator',
				'db_key' => 'nofilter',
				'input_type' => 'multiselect',
				'display' => '',
				'values' => $operators,
				'singleselect' => 1,
				'default' => array(),
			),
			'usage_value' => array(
				'key' => 'manual_value',
				'db_key' => 'nofilter',
				'input_type' => 'number',
				'display' => '',
				'default' => '',
			),
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
				'sid' => array(
					'width' => 2,
				),
				'aid' => array(
					'width' => 2,
				),
			),
			1 => array(
				'billrun' => array(
					'width' => 2,
				),
			),
			2 => array(
				'plan' => array(
					'width' => 2,
				),
			),
			3 => array(
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
		$ret = array();
		foreach($resource as $item) {
 			if ($current_plan = $this->getDBRefField($item, 'current_plan')) {
				$item['current_plan'] = $current_plan['name'];
			}
			$ret[] = $item;
		}

		$this->_count = $resource->count(false);
		return $ret;
	}
	
	public function getSortFields() {
		return $this->getBalancesFields();
	}

}
