<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Match filter
 *
 * @package  calculator
 * @since 5.10
 */
class Billrun_EntityGetter_Filters_Match extends Billrun_EntityGetter_Filters_Base {

	protected function updateMatchQuery(&$match, $row) {
		if ($this->params['entity_key'] !== 'usaget') {
			$match = array_merge($match, array($this->params['entity_key'] => $this->getRowFieldValue($row, $this->params['line_key'])));
		}
	}

}
