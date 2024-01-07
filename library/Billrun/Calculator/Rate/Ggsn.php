<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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
	 */
	protected function getLineVolume($row, $usage_type) {
		return $row['fbc_downlink_volume'] + $row['fbc_uplink_volume'];
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		return 'data';
	}

	/**
	 * load the ggsn rates to be used later.
	 */
	protected function loadRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates_query = array(
			'$or' => array(
				$this->rateKeyMapping, 
				array('key' => 'UNRATED')
			)
		);
		$rates = $rates_coll->query($rates_query)->cursor();
		$this->rates = array();
		foreach ($rates as $value) {
			$value->collection($rates_coll);
			if ($value['key'] == 'UNRATED') {
				$this->rates['UNRATED'] = $value;
			} else {
				$this->rates[] = $value;
			}
		}
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {
		$line_time = $row['urt'];
		foreach ($this->rates as $rate) {
			if ($rate['key'] === 'UNRATED') {
				continue;
			}
			$regex = (get_class($rate['params']['sgsn_addresses']) == 'MongoRegex') ? '/' . $rate['params']['sgsn_addresses']->regex . '/' : $rate['params']['sgsn_addresses'];
			if (preg_match($regex, $row['sgsn_address']) && $rate['from'] <= $line_time && $line_time <= $rate['to']) {
				return $rate;
			}
		}
		Billrun_Factory::log()->log("Couldn't find rate for row : " . print_r($row['stamp'], 1), Zend_Log::DEBUG);
		return $this->rates['UNRATED'];
	}

}
