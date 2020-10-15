<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing rate (product) class
 *
 * @package  Rate
 * @since    5.12
 */
class Billrun_Rate extends Billrun_Entity {

    const PRICING_METHOD_TIERED = 'tiered';
	const PRICING_METHOD_VOLUME = 'volume';

    /**
     * see parent::getCollection
     */
    public static function getCollection() {
        return Billrun_Factory::db()->ratesCollection();
    }
	
	/**
     * see parent::getLoadQueryByParams
     */
	protected function getLoadQueryByParams($params = []) {
        if (isset($params['key'])) {
            return [
                'key' => $params['key'],
            ];
        }

        return false;
	}
	
	public function getPricingMethod() {
		return $this->get('pricing_method', self::PRICING_METHOD_TIERED);
	}

    public function getTotalCharge($usageType, $volume, $params = []) {//$usageType, $volume, $plan = null, $services = array(), $offset = 0, $time = NULL) {
		return $this->getCharges($usageType, $volume, $params)['total'];
	}

    public function getCharges($usageType, $volume, $params = []) {
		$tariff = Billrun_Rate_Tariff::getInstance($this, $usageType, $params);
		$offset = $params['offset'] ?? 0;
		$percentage = 1;
		
		//TODO: handle this case
		$tariff2 = $tariff->getData();
		// if $overrideByPercentage is true --> use the original rate and set the correct percentage
		if (array_keys($tariff2)[0] === 'percentage') {
			$rates = $this->get('rates', []);
			if (isset($rates[$usageType]['BASE'])) {
				$percentage = array_values($tariff2)[0];
				$tariff = $rates[$usageType]['BASE'];
			}
		}
		
		if ($offset) {
			$chargeWoIC = $tariff->getChargeByVolume($offset + $volume) - $tariff->getChargeByVolume($offset);
		} else {
			$chargeWoIC = $tariff->getChargeByVolume($volume);
		}
		$chargeWoIC *= $percentage;
		return [
			'total' => $chargeWoIC,
		];
    }
}
