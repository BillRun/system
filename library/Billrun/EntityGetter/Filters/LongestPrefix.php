<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Longest Prefix filter
 *
 * @package  calculator
 * @since 5.10
 */
class Billrun_EntityGetter_Filters_LongestPrefix extends Billrun_EntityGetter_Filters_Base {

	protected function updateMatchQuery(&$match, $row) {
		$match = array_merge(
			$match, 
			array($this->params['entity_key'] => $this->getPrefixMatchQuery($this->getRowFieldValue($row, $this->params['line_key'])))
		);
	}
	
	protected function updateAdditionalQuery($row) {
		return array('$unwind' => "$" . $this->params['entity_key']);
	}
	
	protected function updateGroupQuery(&$group, $row) {
		$group['_id'] = array_merge(
			$group['_id'],
			array("pref" => "$" . $this->params['entity_key'])
		);
		$group[$this->getAggregatedPrefixFieldName()] = array('$first' => "$" . $this->params['entity_key']);
	}
	
	protected function updateAdditionaAfterGrouplQuery($row) {
		return array('$match' => array($this->getAggregatedPrefixFieldName() => $this->getPrefixMatchQuery($this->getRowFieldValue($row, $this->params['line_key']))));

	}
	
	protected function updateSortQuery(&$sort, $row) {
		$sort = array_merge($sort, array($this->getAggregatedPrefixFieldName() => -1));
	}
	
	protected function getAggregatedPrefixFieldName() {
		return 'prefix_field_' . str_replace('.', '_', $this->params['entity_key']);
	}
		
	/**
	 * Assistance function to generate 'prefix' field query with current row.
	 * 
	 * @return array query for all prefixes
	 */
	protected function getPrefixMatchQuery($value) {
		return array('$in' => Billrun_Util::getPrefixes(!is_array($value)? $value : current($value)));
	}

}
