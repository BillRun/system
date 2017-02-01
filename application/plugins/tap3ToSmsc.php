<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculator ildsOneWay plugin create csv file for ilds one way process
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.9
 */
class tap3ToSmscPlugin extends Billrun_Plugin_BillrunPluginBase {

	public function afterCalculatorUpdateRow($row, $calculator) {
		if ($calculator->getCalculatorQueueType() == 'pricing' && $row['type'] == 'tap3' && $row['usaget'] == 'sms') {
			$lineTime = $row['urt']->sec;
			$transferDay = strtotime('20170201235959');
			if ($lineTime > $transferDay) {
				unset($row['billrun']);
			}
		}
		
		if ($calculator->getCalculatorQueueType() == 'pricing' && $row['type'] == 'smsc' && $row['roaming']) {
			$lineTime = $row['urt']->sec;
			$transferDay = strtotime('20170202000000');
			if ($lineTime < $transferDay) {
				unset($row['billrun']); 
			}
		}
	}

}