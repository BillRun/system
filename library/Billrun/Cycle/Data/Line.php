<?php


/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This interface is used to identify lines
 */
interface Billrun_Cycle_Data_Line {
	/**
	 * This function returns an aggregate result
	 */
	public function getLine();
}
