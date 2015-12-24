<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator rate class
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
 * @since    2.9
 *
 */
class Billrun_Calculator_Rate_Ggsnintl extends Billrun_Calculator_Rate {

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
		$this->countryMapping = Billrun_Factory::config()->getConfigValue('country.mapping',array());
		$this->loadRates();
		
	}
	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 * @deprecated since version 2.9
	 */
	protected function getLineVolume($row, $usage_type) {
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
		
		$query = array(
			'$or' => array(
				array(
					'alpha3' => array(
						'$exists' => true,
					),
					'rates.data' => array(
						'$exists' => true,
					),
				),
				array(
					'key' => 'UNRATED',
				),
			),
		);
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = $rates_coll->query($query)->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'));
		foreach ($rates as $rate) {
			$rate->collection($rates_coll);
			 if ($rate['key'] == 'UNRATED') {
				$this->rates['UNRATED'] = $rate;
			} else {
//				if (isset($this->rates['by_names'][$rate['alpha3']]) && $this->rates['by_names'][$rate['alpha3']][0]['key'] != $rate['key'] ) {
//					print('found doubles:'.$rate['key'].'   '.$rate['alpha3']. '    '.' '.$this->rates['by_names'][$rate['alpha3']][0]['key'].PHP_EOL );
//				}
				$this->rates['by_names'][$rate['alpha3']][] = $rate;
			}
		}
	}
	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {
		$line_alpha3 = $this->getLineAlpha3($row);
		print($line_alpha3.PHP_EOL);
		$line_time = $row['urt'];
		if(isset($this->rates['by_names'][$line_alpha3])){
			foreach ($this->rates['by_names'][$line_alpha3] as $rate) {
				if ( $rate['from'] <= $line_time && $line_time <= $rate['to']) {
					print($rate['key'].PHP_EOL);
					return $rate;
				}

			}
		}
		Billrun_Factory::log()->log("Couldn't find rate for row : " . print_r($row['stamp'], 1), Zend_Log::DEBUG);
		return FALSE;
	}

	protected function getLineAlpha3($row) {
		foreach($this->countryMapping as $key => $address_list){
			foreach ($address_list['sgsn_address'] as $address){
				$ip_and_mask = explode('/',$address); 
				$network = $ip_and_mask[0];
				$mask = $ip_and_mask[1];
				if(Utilities_IpFunctions::cidr_match($row['sgsn_address'], $network, $mask)){
					return $key;
				}
			}
		}
	}
	
}
