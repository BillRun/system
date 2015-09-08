<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * 
 * Class for getting the view params when aggregate
 * @package  ViewParams
 * @since    2.8
 */
class Admin_Viewparams_Aggregate extends Admin_Viewparams_Base {
	
	/**
	 * Get the query type for this handler.
	 * @return string The query type.
	 */
	protected function getQueryType() {
		return 'aggregate';
	}
	
	/**
	 * method to render table view
	 * 
	 * @param array $filter_query - Query to get data for.
	 * @param array $columns the columns to show
	 * 
	 * @return string the render page (HTML)
	 * @todo refactoring this function
	 */
	public function getTableViewParams($model, $columns=array(), $filter_query = array(), $skip = null, $size = null) {
		$groupByKeys = array_keys($filter_query[1]['$group']['_id'] );
		$aggregatedColumns = $this->getAggregateTableColumns($groupByKeys, $columns);
		
		return parent::getTableViewParams($model, $aggregatedColumns, $filter_query, $filter_query, $skip, $size);
	}
	
	/**
	 * Get the columns to present for the aggregate table.
	 * @param array $groupByKeys - The keys to use for aggregation.
	 * @param array $extraColumns - Array of extra column values to be set.
	 * @return array Group columns to show.
	 */
	public function getAggregateTableColumns($groupByKeys, $extraColumns) {
		$group= array();
		
		foreach ($groupByKeys as $key) {
			$group[Mongodloid_AggregatedEntity::$GROUP_BY_IDENTIFIER . '.' . $key] = $key;
		}
			
		$group['sum'] = 'Count';
		return array_merge($group, $extraColumns);
	}
}
