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
		$match = array_merge(
			$match, 
			array($this->params['rate_key'] => $this->getPrefixMatchQuery($row[$this->params['line_key']]))
		);
	}
	
	protected function updateAdditionalQuery($row) {
		return array('$unwind' => "$" . $this->params['rate_key']);
	}
	
	protected function updateGroupQuery(&$group, $row) {
		$group['_id'] = array_merge(
			$group['_id'],
			array("pref" => "$" . $this->params['rate_key'])
		);
		$group[$this->getAggregatedPrefixFieldName()] = array('$first' => "$" . $this->params['rate_key']);
	}
	
	protected function updateAdditionaAfterGrouplQuery($row) {
		return array('$match' => array($this->getAggregatedPrefixFieldName() => $this->getPrefixMatchQuery($row[$this->params['line_key']])));

	}
	
	protected function updateSortQuery(&$sort, $row) {
		$sort = array_merge($sort, array($this->getAggregatedPrefixFieldName() => -1));
	}
	
	protected function getAggregatedPrefixFieldName() {
		return 'prefix_field_' . str_replace('.', '_', $this->params['rate_key']);
	}
		
	/**
	 * Assistance function to generate 'prefix' field query with current row.
	 * 
	 * @return array query for all prefixes
	 */
	protected function getPrefixMatchQuery($value) {
		return array('$in' => Billrun_Util::getPrefixes($value));
	}

}
