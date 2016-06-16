<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing basic base filter
 *
 * @package  calculator
 * @since braas
 */
class Billrun_Calculator_Rate_Filters_Base {
	
	public $params = array();
	
	public function __construct($params = array()) {
		$this->params = $params;
	}

	public function updateQuery(&$match, &$group, &$additional, &$sort, $row) {
		
		$this->updateMatchQuery($match, $row);
		$this->updateGroupQuery($group, $row);
		$a = $this->updateAdditionalQuery($row);
		if ($a) {
			$additional[] = $a;
		}
		$this->updateSortQuery($sort, $row);
	}
	
	protected function updateMatchQuery(&$match, $row) {
	}
	
	protected function updateGroupQuery(&$group, $row) {
	}
	
	protected function updateAdditionalQuery($row) {
	}
	
	protected function updateSortQuery(&$sort, $row) {
	}
}
