<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * 
 * Base class for the admin filter handler.
 * @package  Admin
 * @since    2.8
 */
class Admin_Filter_Find extends Admin_Filter_Base {
	
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
		$filter_fields = $model->getFilterFields();
		$query = array();
		$request = $admin->getRequest();
		
		$filter = $this->getManualFilters($request, $session, $model);
		if ($filter) {
			$query['$and'][] = $filter;
		}
		
		$filter_fields_array = array_values($filter_fields);

		foreach ($filter_fields_array as $filter_field) {
			$value = AdminController::setRequestToSession($request, $session, $filter_field['key'], $filter_field['key'], $filter_field['default']);
			if ((!empty($value) || $value === 0 || $value === "0") && $filter_field['db_key'] != 'nofilter' && $filter = $model->applyFilter($filter_field, $value)) {
				$query['$and'][] = $filter;
			}
		}
		return $query;
	}
}
