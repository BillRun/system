
<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2025 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Logic  to  exclude certain CDRs and include  others  on a given date
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.9
 */
class cdrTypeSwitchingPlugin extends Billrun_Plugin_BillrunPluginBase {
	protected $transferDayTap3ToNrtrde ;

	public function __construct() {
		$this->transferDayTap3ToNrtrde = Billrun_Factory::config()->getConfigValue('billrun.tap3_to_nrtrde_transfer_day', "20250701000000");
	}


	public function overrideIsLineLegitimate(&$row, &$lineIsLegitimate, $calculator) {
		if ($calculator->getCalculatorQueueType() == 'pricing' && in_array($row['type'],['tap3','nrtrde','nsn']) ) {
			$lineTime = $row['urt']->sec;
			$transferTap3NrtrdeDay = strtotime($this->transferDayTap3ToNrtrde);
			switch($row['type'])  {
				// case 'tap3': $lineIsLegitimate &= !($lineTime >= $transferTap3NrtrdeDay && in_array($row['usaget'],['call','incoming_call'])) ;
				// 	break;

				case 'nrtrde' : $lineIsLegitimate &= !($lineTime < $transferTap3NrtrdeDay);
					break;

				case 'nsn' : $lineIsLegitimate &= !($lineTime >= $transferTap3NrtrdeDay && !empty($row['roaming']) && !empty($row['serving_network']) );
					break;

				default: Billrun_Factory::log('How????!',Zend_Log::WARN);
			}
		}
	}

}
