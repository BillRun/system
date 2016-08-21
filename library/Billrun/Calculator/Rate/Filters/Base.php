<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing basic base filter
 *
 * @package  calculator
 * @since 5.0
 */
class Billrun_Calculator_Rate_Filters_Base {
	
	public $params = array();
	
	public function __construct($params = array()) {
		$this->params = $params;
	}

	public function updateQuery(&$match, &$additional, &$group, &$additionalAfterGroup, &$sort, $row) {
		
		$this->updateMatchQuery($match, $row);
		$a = $this->updateAdditionalQuery($row);
		if ($a) {
			$additional[] = $a;
		}
		$this->updateGroupQuery($group, $row);
		$a2 = $this->updateAdditionaAfterGrouplQuery($row);
		if ($a2) {
			$additionalAfterGroup[] = $a2;
		}
		$this->updateSortQuery($sort, $row);
	}
	
	protected function updateMatchQuery(&$match, $row) {
	}
	
	protected function updateAdditionalQuery($row) {
	}
	
	protected function updateGroupQuery(&$group, $row) {
	}
	
	protected function updateAdditionaAfterGrouplQuery($row) {
	}
	
	protected function updateSortQuery(&$sort, $row) {
	}
}
