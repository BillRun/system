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
class calcCpuPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'calcCpu';

	public function beforeProcessorStore($processor) {
		Billrun_Factory::log('Plugin calc cpu triggered', Zend_Log::INFO);
		$options = array(
			'autoload' => 0,
		);

		$data = &$processor->getData();
		Billrun_Factory::log('Plugin calc cpu rate', Zend_Log::INFO);
		foreach ($data['data'] as &$line) {
			$entity = new Mongodloid_Entity($line);
			$rate = Billrun_Calculator_Rate::getRateCalculator($entity, $options);
			if ($rate->isLineLegitimate($entity)) {
				$rate->updateRow($entity);
			}
				$processor->setQueueRowStep($entity['stamp'], 'rate');
			$line = $entity->getRawData();
		}

		$customerAPISettings = Billrun_Factory::config()->getConfigValue('customer.calculator', array());
		$customerOptions = array(
			'type' => 'customer',
			'calculator' => $customerAPISettings,
		);
		$customerCalc = Billrun_Calculator::getInstance(array_merge($options, $customerOptions));
		if ($customerCalc->isBulk()) {
			$customerCalc->loadSubscribers($data['data']);
		}
		Billrun_Factory::log('Plugin calc cpu customer', Zend_Log::INFO);
		foreach ($data['data'] as &$line) {
			$entity = new Mongodloid_Entity($line);
			if (!isset($entity['usagev']) || $entity['usagev'] === 0) {
				$processor->unsetQueueRow($entity['stamp']);
			}
			else if ($customerCalc->isLineLegitimate($entity)) {
				if ($customerCalc->updateRow($entity) !== FALSE) {
					$processor->setQueueRowStep($entity['stamp'], 'customer');
				}
			} else {
				$processor->setQueueRowStep($entity['stamp'], 'customer');
			}
			$line = $entity->getRawData();
		}

//		// @TODO: make customer price calc in the same way
//		$customerPricingCalc = new Billrun_Calculator_CustomerPricing($options);
//			if ($customerPricingCalc->isLineLegitimate($entity)) {
//				Billrun_Factory::log($entity['stamp'] . ' priced');
//				$customerPricingCalc->updateRow($entity);
//			}
		Billrun_Factory::log('Plugin calc cpu end', Zend_Log::INFO);
	}

}