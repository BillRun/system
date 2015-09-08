<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * 
 * Class for getting the view params when find
 * @package  ViewParams
 * @since    2.8
 */
class Admin_Viewparams_Find extends Admin_Viewparams_Base {
	
	/**
	 * Get the query type for this handler.
	 * @return string The query type.
	 */
	protected function getQueryType() {
		return 'find';
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
		$columnsToSend = array_merge($columns, $model->getTableColumns());
		
		return parent::getTableViewParams($model, $columnsToSend, $filter_query, $filter_query, $skip, $size);
	}
}
