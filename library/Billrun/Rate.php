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
class Billrun_Rate extends Mongodloid_Entity {

    const PRICING_METHOD_TIERED = 'tiered';
	const PRICING_METHOD_VOLUME = 'volume';

	public function __construct($data = []) {
		parent::__construct($data, Billrun_Factory::db()->ratesCollection());
	}
		
	/**
	 * get Rate's pricing method
	 *
	 * @return string one of tiered/volume
	 */
	public function getPricingMethod() {
		return $this->get('pricing_method') ?? self::PRICING_METHOD_TIERED;
	}
    
    /**
     * get total charge
     *
     * @param  string $usageType
     * @param  float $volume
     * @param  array $params
     * @return float
     */
    public function getTotalCharge($usageType, $volume, $params = []) {
		return $this->getCharges($usageType, $volume, $params)['total'];
	}
    
    /**
     * get all charges
     *
     * @param  string $usageType
     * @param  float $volume
     * @param  array $params
     * @return array
     */
    public function getCharges($usageType, $volume, $params = []) {
		$tariff = new Billrun_Rate_Tariff($this, $usageType, $params);
		$offset = $params['offset'] ?? 0;
		if ($offset) {
			$chargeWoIC = $tariff->getChargeByVolume($offset + $volume) - $tariff->getChargeByVolume($offset);
		} else {
			$chargeWoIC = $tariff->getChargeByVolume($volume);
		}
		
		$chargeWoIC *= $tariff->getPercentage();
		return [
			'total' => $chargeWoIC,
		];
    }
}
