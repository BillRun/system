<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Longest Prefix rate filter
 *
 * @package  calculator
 * @since braas
 */
class Billrun_Calculator_Rate_Filters_LongestPrefix extends Billrun_Calculator_Rate_Filters_Base {

	protected function updateMatchQuery(&$match, $row) {
	}
	
	protected function updateGroupQuery(&$group, $row) {
	}
	
	protected function updateAdditionalQuery($row) {
	}
	
	protected function updateSortQuery(&$sort, $row) {
	}
		
	/**
	 * Assistance function to generate 'prefix' field query with current row.
	 * 
	 * @return array query for all prefixes
	 */
	protected function getPrefixMatchQuery() {
		return array('$in' => Billrun_Util::getPrefixes($this->rowDataForQuery['called_number']));
	}

}
