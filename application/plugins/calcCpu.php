<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Calculator cpu plugin make the calculative operations in the cpu (before line inserted to the DB)
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.9
 */
class calcCpuPlugin extends Billrun_Plugin_BillrunPluginBase  {
	
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'calcCpu';
	
	public function beforeProcessorStore($processor) {
		Billrun_Factory::log('Plugin calc cpu triggered');
		$options = array(
			'autoload' => 0,
			'calculator' => array('bulk' => 0),
		);
//		$customerCalc = new Billrun_Calculator_Customer($options);
//		$customerPricingCalc = new Billrun_Calculator_CustomerPricing($options);
		$data = &$processor->getData();
		foreach ($data['data'] as &$line) {
			$entity = new Mongodloid_Entity($line);
			$rate = Billrun_Calculator_Rate::getRateCalculator($entity, $options);
			if ($rate->isLineLegitimate($entity)) {
				$rate->updateRow($entity);
			}
//			if ($customerCalc->isLineLegitimate($entity)) {
//				Billrun_Factory::log($entity['stamp'] . ' customered');
//				$customerCalc->updateRow($entity);
//			}
//			if ($customerPricingCalc->isLineLegitimate($entity)) {
//				Billrun_Factory::log($entity['stamp'] . ' priced');
//				$customerPricingCalc->updateRow($entity);
//			}
			$line = $entity->getRawData();
		}
	}
}