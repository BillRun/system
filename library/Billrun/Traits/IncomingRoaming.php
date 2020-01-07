<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This Trait is used for classes that need to use incoming roaming logic
 */
trait Billrun_Traits_IncomingRoaming {

	protected $tadigs = array();

	protected function getTadigs() {
		if (empty($this->tadigs)) {
			$tadigs_coll = Billrun_Factory::db()->tadigsCollection();
			$tadigs = $tadigs_coll->query()->cursor();
			foreach ($tadigs as $tadig) {
				foreach ($tadig['mcc_mnc'] as $mccMnc) {
					$this->tadigs[$mccMnc] = $tadig['tadig'];
				}
			}
		}

		return $this->tadigs;
	}

	protected function isRoamingLine($row) {
		return isset($row['incoming_roaming']) && $row['incoming_roaming'];
	}

	public function getTadig($row) {
		$tadigs = $this->getTadigs();
		$imsi = $this->getImsi($row);
		$mccMnc = $this->getMccMnc($imsi);
		return isset($tadigs[$mccMnc]) ? $tadigs[$mccMnc] : false;
	}

	/**
	 * extract IMSI from line
	 * 
	 * @param array $row
	 */
	public function getImsi($row) {
		if (!empty($row['imsi'])) {
			return $row['imsi'];
		}

		if (!empty($row['called_imsi'])) {
			return $row['called_imsi'];
		}

		return '';
	}

	/**
	 * extract MCC-MNC from IMSI
	 * 
	 * @param string $imsi
	 * @return string MCC-MNC
	 */
	public function getMccMnc($imsi) {
		$mcc = substr($imsi, 0, 3);
		$mnc = substr($imsi, 3, 3);
		return $mcc . $mnc;
	}

	protected function getRoamingRateQuery($row, $usage_type) {
		$tadig = $this->getTadig($row);
		if (!$tadig) {
			return false;
		}
		return array(
			'from' => array(
				'$lte' => new MongoDate($row['urt']->sec),
			),
			'to' => array(
				'$gte' => new MongoDate($row['urt']->sec),
			),
			"rates.{$usage_type}" => array(
				'$exists' => true,
			),
			'params.roaming_rate' => true,
			'params.roaming_tadig' => array(
				'$in' => array($tadig, null), // null is for default roaming rate where tadig field does not exist
			),
		);
	}

	protected function getRoamingRateSort($row, $usage_type) {
		return array();
	}

	protected function getRoamingLineRate($row, $usage_type) {
		if (!$this->isRoamingLine($row)) {
			return false;
		}
		$query = $this->getRoamingRateQuery($row, $usage_type);
		if (!$query) {
			return false;
		}
		$sort = $this->getRoamingRateSort($row, $usage_type);
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rate = $rates_coll->query($query)->cursor()->sort($sort)->current();
		if ($rate->isEmpty()) {
			return false;
		}
		$rate->collection($rates_coll);
		return $rate;
	}

}
