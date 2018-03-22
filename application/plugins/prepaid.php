<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Prepaid plugin for prepaid lines.
 *
 * @package  Application
 * @subpackage Plugins
 * @since    2.8
 */
class prepaidPlugin extends Billrun_Plugin_BillrunPluginBase {

	public function afterCalculatorUpdateRow($row, $calculator) {
		if ($calculator->getCalculatorQueueType() == 'pricing' && !empty($row['prepaid'])) {
				unset($row['billrun']);
				$row['aprice'] = 0;
		}
	}

}