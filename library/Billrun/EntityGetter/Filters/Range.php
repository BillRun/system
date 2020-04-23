<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing range filter
 *
 * @package  calculator
 * @since 5.11
 */
class Billrun_EntityGetter_Filters_Range extends Billrun_EntityGetter_Filters_Match {

	protected function updateMatchQuery(&$match, $row) {
		if ($this->params['entity_key'] !== 'usaget') {
			$comparedValue = $this->getRowFieldValue($row, $this->params['line_key']);
			$filter = array(
				$this->params['entity_key'] . ".min" => array('$lt' => $comparedValue), 
				$this->params['entity_key'] . ".max" => array('$gt' => $comparedValue),
			);
			$match = array_merge($match, $filter);
		}
	}
	
	protected function updatePreOperation($row) {
		return array('$unwind' => "$" . $this->params['entity_key']);
	}


}
