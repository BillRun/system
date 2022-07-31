<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator rate class
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
 * @since    0.5
 *
 */
class Billrun_Calculator_Rate_Ggsn extends Billrun_Calculator_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'ggsn';

	/**
	 *
	 * @var type 
	 */
	protected $rates = array();

	/**
	 * @see Billrun_Calculator_Base_Rate
	 * @var type 
	 */
	protected $rateKeyMapping = array('params.sgsn_addresses' => array('$exists' => true));

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->loadRates();
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 * @deprecated since version 2.9
	 */
	protected function getLineVolume($row) {
		return $row['fbc_downlink_volume'] + $row['fbc_uplink_volume'];
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 * @deprecated since version 2.9
	 */
	protected function getLineUsageType($row) {
		return 'data';
	}

	/**
	 * load the ggsn rates to be used later.
	 */
	protected function loadRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = $rates_coll->query($this->rateKeyMapping)->cursor();
		$this->rates = array();
		foreach ($rates as $value) {
			$value->collection($rates_coll);
			$this->rates[] = $value;
		}
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row) {
		$line_time = $row['urt'];
		foreach ($this->rates as $rate) {
			if (preg_match($rate['params']['sgsn_addresses'], $row['sgsn_address']) && $rate['from'] <= $line_time && $line_time <= $rate['to']) {
				return $rate;
			}
		}
		Billrun_Factory::log("Couldn't find rate for row : " . print_r($row['stamp'], 1), Zend_Log::DEBUG);
		return FALSE;
	}

}
