<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * 
 * Base class for the admin filter handler when aggregate.
 * @package  Admin
 * @since    2.8
 */
class Admin_Filter_Aggregate extends Admin_Filter_Base {
	
	/**
	 * Get the query for the filters.
	 * @param AdminController $admin - The admin controller.
	 * @param $table - Name for the current mongo collection.
	 * @param $model - Current model in use.
	 * @return array Query for the filter.
	 */
	public function query($admin, $table) {
		$session = AdminController::getSession($table);
		$model = $admin->getModel();
		$request = $admin->getRequest();
		$filter_fields = $model->getFilterFields();
		$match = array();
		
		$filter = $this->getManualFilters($request, $session, $model);
		if ($filter) {
			$match = array_merge($match, $filter);
		}
		
		$filter_fields_values = array_values($filter_fields);
		foreach ($filter_fields_values as $filter_field) {
			$value = AdminController::setRequestToSession($request, $session, $filter_field['key'], $filter_field['key'], $filter_field['default']);
			if ((!empty($value) || $value === 0 || $value === "0") && $filter_field['db_key'] != 'nofilter' && $filter = $model->applyFilter($filter_field, $value)) {
				$match = array_merge($match, $filter);
			}
		}
		
		$groupBySelect = AdminController::setRequestToSession($request, $session, 'groupBySelect');
		$groupBy = array();
		foreach ($groupBySelect as $groupDataElem) {
			$groupBy[ucfirst($groupDataElem)] = '$' . $groupDataElem;
		}
		
		$columnNames = array();
		$groupData = $this->getGroupData($request, $session, $columnNames);
		$group = array_merge(array('_id' => $groupBy,'sum' => array('$sum' => 1)), $groupData);
		$admin->setAggregateColumns($columnNames);
		return array(
			array(	
				'$match' => $match
			),
			array(
				'$group' => $group
			),
		);
	}
	
	/**
	 * Get the group data.
	 * @param Object $request - The request instance.
	 * @param Object $session - The session instance.
	 * @param array $columnNames - Array to fill with column names.
	 * @return array Query for the group data.
	 */
	protected function getGroupData($request, $session, &$columnNames) {
		$query = false;
		$keys = AdminController::setRequestToSession($request, $session, 'group_data_keys', 'group_data_keys');
		$operators = AdminController::setRequestToSession($request, $session, 'group_data_operators', 'group_data_operators');
		settype($keys, 'array');
		settype($operators, 'array');
		for ($i = 0; $i < count($keys); $i++) {
			$columnName = $keys[$i] . '-' . $operators[$i];
			$columnNames[$columnName] = ucfirst($columnName);
			$query[$columnName] = array('$' . $operators[$i] => '$' . $keys[$i]);
		}
		return $query;
	}
}
