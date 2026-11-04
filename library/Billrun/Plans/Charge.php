<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Factory used to get a plan charge
 *
 * @package  Plans
 * @since    5.2
 */
class Billrun_Plans_Charge {

	/**
	 *
	 * @var Billrun_DataTypes_CycleTime
	 */
	protected $cycle;

	/**
	 * Get the charges
	 * @return array.
	 */
	public function charge($entityData, Billrun_DataTypes_CycleTime $cycle) {
		$results = array();

		$chargeObj = $this->getChargeObject($entityData);
		if (!$chargeObj) {
			return $results;
		}

		// Get the charge.
		$charge = $chargeObj->getPrice(Billrun_Util::getFieldVal($entityData['quantity'], 1));

		if ($charge !== null) {
			$results['charge'] = $charge;
		}

		// Check if has refund
		if ($chargeObj instanceof Billrun_Plans_Charge_Upfront) {
			$refund = $chargeObj->getRefund($cycle,Billrun_Util::getFieldVal($entityData['quantity'], 1));
			if ($refund !== null && !Billrun_Factory::config()->getConfigValue('billrun.flats.generate_zero_refunds',true)) {
				// drop zero value refunds - the whole refund in the legacy single refund shape, per
				// row in the reconciliation rows list shape
				$refund = isset($refund['value'])
					? (!empty($refund['value']) ? $refund : null)
					: array_values(array_filter($refund, function ($row) { return !empty($row['value']); }));
			}
			if (!empty($refund)) {
				$results['refund'] = $refund;
			}
		}
		return $results;
	}

	/**
	 *
	 * @param type $plan
	 * @return Billrun_Plans_Charge_Base
	 */
	public function getChargeObject($plan) {
		$object = __CLASS__;
		//TODO change this to configurtion based mapping
		Billrun_Factory::dispatcher()->trigger('beforeGetPlanChargeObject', array(&$plan));
		if(empty($plan['balance_period'])) {
			//Should  the  charge be  upfornt or  arrears
			$object .=!empty($plan['upfront']) ? '_Upfront' : '_Arrears';
			//Should the charge  be unprorated?
			$object .=!isset($plan['prorated']) || !empty($plan['prorated']) ? '' : '_Notprorated';
			//Should we  use  a diffrent peroid  then monthly charge?
			$object .= isset($plan['recurrence']) 	? '_' . (empty($plan['recurrence']['frequency'])		?
																ucfirst($plan['recurrence']['periodicity']) :
																'Custom')
													: '_Month';
		} else {
			$object .=  "_Singleperiod";
		}
		// Check if exists
		if (!class_exists($object)) {
			Billrun_Factory::log("Could not find class: " . print_r($object, 1));
			return null;
		}

		return new $object($plan);
	}

}
