<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * 
 * Interface for getting the view params
 * @package  Admin
 * @since    2.8
 */
interface Admin_Viewparams_I {
	/**
	 * method to render table view
	 * @param Model $model - Current model in use. 
	 * @param array $columns the columns to show
	 * @param array $filter_query - Query to get data for.
	 * 
	 * @return string the render page (HTML)
	 * @todo refactoring this function
	 */
	public function getTableViewParams($model, $columns = array(), $filter_query = array(), $skip = null, $size = null);
}
