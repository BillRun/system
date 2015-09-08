<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * 
 * Interface for getting query for filters
 * @package  Admin
 * @since    2.8
 */
interface Admin_Filter_I {
	
	/**
	 * Get the query for the filters.
	 * @param AdminController $admin - The admin controller.
	 * @param $table - Name for the current mongo collection.
	 * @param $model - Current model in use.
	 * @return array Query for the filter.
	 */
	public function query($admin, $table);
}
