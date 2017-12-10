<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Match rate filter
 *
 * @package  calculator
 * @since 5.0
 */
class Billrun_Calculator_Rate_Filters_Match extends Billrun_Calculator_Rate_Filters_Base {

	protected function updateMatchQuery(&$match, $row) {
		if ($this->params['rate_key'] !== 'usaget') {
			$match = array_merge($match, array($this->params['rate_key'] => $this->getRowFieldValue($row, $this->params['line_key'])));
		}
	}

}
