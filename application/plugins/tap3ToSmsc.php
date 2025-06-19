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
		$this->transferDayTap3ToNrtrde = Billrun_Factory::config()->getConfigValue('billrun.tap3_to_nrtrde_transfer_day', "20250621000000");
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
				if( preg_match('/^ims$/i',$row['apn']) ) {
					if( !empty($row['vf_count_days']) ) {
						unset($row['vf_count_days']);
					}
					if( !empty($row['vf_addon_days']) ) {
						unset($row['vf_addon_days']);
					}
				}
			}
		}
		
		if ($calculator->getCalculatorQueueType() == 'pricing' && $row['type'] == 'smsc' && $row['roaming']) {
			$lineTime = $row['urt']->sec;
			$transferDay = strtotime($this->transferDaySmsc);
			if ($lineTime < $transferDay) {
				unset($row['billrun']); 
			}
		}

		if ($calculator->getCalculatorQueueType() == 'pricing' && in_array($row['type'],['tap3','nrtrde','nsn']) ) {
			$lineTime = $row['urt']->sec;
			$transferTap3NrtrdeDay = strtotime($this->transferDayTap3ToNrtrde);
			switch($row['type'])  {
				case 'tap3': if ($lineTime >= $transferTap3NrtrdeDay && in_array($row['usaget'],['call','incoming_call']))  {
								unset($row['billrun']);
							}
					break;

				case 'nrtrde' : if ($lineTime < $transferTap3NrtrdeDay) {
								unset($row['billrun']);
							}
					break;
				case 'nsn' : if ($lineTime >= $transferTap3NrtrdeDay && !empty($row['roaming']) && !empty($row['serving_network']) ) {
								unset($row['billrun']);
							}
					break;
				default: Billrun_Factory::log('How????!',Zend_Log::WARN);
			}
		}
	}

}
