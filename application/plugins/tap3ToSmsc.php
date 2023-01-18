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
	protected $transferDaySmsc;
	
	public function __construct() {
		$this->transferDaySmsc = Billrun_Factory::config()->getConfigValue('billrun.tap3_to_smsc_transfer_day', "20170301000000");
	}


	public function afterCalculatorUpdateRow($row, $calculator) {
		if ($calculator->getCalculatorQueueType() == 'pricing' && $row['type'] == 'tap3' &&
			// ignore SMS from tap3
			 ( $row['usaget'] == 'sms'
				||
			//ignore data usage  for  VoLTE calls
				preg_match('/^ims$/i',$row['apn']) )
		) {
			$lineTime = $row['urt']->sec;
			$transferDay = strtotime($this->transferDaySmsc);
			if ($lineTime >= $transferDay) {
				unset($row['billrun']);
			}
		}
		
		if ($calculator->getCalculatorQueueType() == 'pricing' && $row['type'] == 'smsc' && $row['roaming']) {
			$lineTime = $row['urt']->sec;
			$transferDay = strtotime($this->transferDaySmsc);
			if ($lineTime < $transferDay) {
				unset($row['billrun']); 
			}
		}
	}

}
