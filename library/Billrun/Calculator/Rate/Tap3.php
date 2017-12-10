<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for tap3 records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Rate_Tap3 extends Billrun_Calculator_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'tap3';

	/**
	 * Detecting an arate is optional for these usage types
	 * @var array
	 */
	protected $optional_usage_types = array();

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->optional_usage_types = isset($options['calculator']['optional_usage_types']) ? $options['calculator']['optional_usage_types'] : array('incoming_sms');
		$this->loadRates();
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 * @deprecated since version 2.9
	 */
	protected function getLineVolume($row) {
		$volume = null;
		switch ($usage_type) {
			case 'sms' :
			case 'incoming_sms' :
				$volume = 1;
				break;

			case 'call' :
			case 'incoming_call' :
				$volume = $row->get('basicCallInformation.TotalCallEventDuration');
				break;

			case 'data' :
				$volume = $row->get('download_vol') + $row->get('upload_vol');
				break;
		}
		return $volume;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 * @deprecated since version 2.9
	 */
	protected function getLineUsageType($row) {

		$usage_type = null;

		$record_type = $row['record_type'];
		if (isset($row['tele_srv_code'])) {
			$tele_service_code = $row['tele_srv_code'];
			if ($tele_service_code == '11') {
				if ($record_type == '9') {
					$usage_type = 'call'; // outgoing call
				} else if ($record_type == 'a') {
					$usage_type = 'incoming_call'; // incoming / callback
				}
			} else if ($tele_service_code == '22') {
				if ($record_type == '9') {
					$usage_type = 'sms';
				}
			} else if ($tele_service_code == '21') {
				if ($record_type == 'a') {
					$usage_type = 'incoming_sms';
				}
			}
		} else if (isset($row['bearer_srv_code'])) {
			if ($record_type == '9') {
				$usage_type = 'call';
			} else if ($record_type == 'a') {
				$usage_type = 'incoming_call';
			}
		} else if ($record_type == 'e') {
			$usage_type = 'data';
		}

		return $usage_type;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row) {
		$line_time = $row['urt'];
		$serving_network = $row['serving_network'];
		$sender = isset($row['sending_source']) ? $row['sending_source'] : false;
		$matchedRate = false;
		$prefix_length_matched = 0;

		if (!is_null($serving_network)) {
			$call_number = $this->number_to_rate($row);
			if ($call_number) {
				$call_number = preg_replace("/^[^1-9]*/", "", $call_number);
				$call_number_prefixes = Billrun_Util::getPrefixes($call_number);
			}
			$potential_rates = array();
			if (isset($this->rates['by_names'][$serving_network])) {
				foreach ($this->rates['by_names'][$serving_network] as $named_rate) {
					if (is_array($named_rate['params']['sending_sources'])) {
						if (!$sender || (isset($named_rate['params']['sending_sources']) && in_array($sender, $named_rate['params']['sending_sources']))) {
							$potential_rates[] = $named_rate;
						}
					} else {
						if (is_string($named_rate['params']['sending_sources'])) {
							if (!$sender || (isset($named_rate['params']['sending_sources']) && preg_match($named_rate['params']['sending_sources'], $sender))) {
								$potential_rates[] = $named_rate;
							}
						}
					}
				}
			}
			if (!empty($this->rates['by_regex'])) {
				foreach ($this->rates['by_regex'] as $regex => $regex_rates) {
					if (preg_match($regex, $serving_network)) {
						foreach ($regex_rates as $regex_rate) {
							if (is_array($regex_rate['params']['sending_sources'])) {
								if (!$sender || (isset($regex_rate['params']['sending_sources']) && in_array($sender, $regex_rate['params']['sending_sources']))) {
									$potential_rates[] = $regex_rate;
								}
							} else {
								if (is_string($regex_rate['params']['sending_sources'])) {
									if (!$sender || (isset($regex_rate['params']['sending_sources']) && preg_match($regex_rate['params']['sending_sources'], $sender))) {
										$potential_rates[] = $regex_rate;
									}
								}
							}
						}
					}
				}
			}

			foreach ($potential_rates as $rate) {
				if (isset($rate['rates'][$row['usaget']])) {
					if ($rate['from'] <= $line_time && $rate['to'] >= $line_time) {
						if ((!$matchedRate && empty($rate['params']['prefix'])) || (is_array($rate['params']['serving_networks']) && !$prefix_length_matched)) { // array of serving networks is stronger then regex of serving_networks
							$matchedRate = $rate;
						}
						if (isset($call_number_prefixes) && !empty($rate['params']['prefix'])) {
							if (!isset($rate['params']['fullEqual'])) {
								foreach ($call_number_prefixes as $prefix) {
									if (in_array($prefix, $rate['params']['prefix']) && strlen($prefix) > $prefix_length_matched) {
										$prefix_length_matched = strlen($prefix);
										$matchedRate = $rate;
									}
								}
							} else {
								if (in_array($call_number, $rate['params']['prefix']) && strlen($call_number) > $prefix_length_matched) {
									$prefix_length_matched = strlen($call_number);
									$matchedRate = $rate;
								}
							}
						}
					}
				}
			}
		}

		if ($matchedRate === FALSE && !in_array($row['usaget'], $this->optional_usage_types)) {
			$matchedRate = $this->rates['UNRATED'];
		}

		return $matchedRate;
	}

	/**
	 * Get the header data  of the file that a given TAP3 CDR line belongs to. 
	 * @param type $line the cdr  lline to get the header for.
	 * @return Object representing the file header of the line.
	 */
	protected function getLineHeader($line) {
		return Billrun_Factory::db()->logCollection()->query(array('header.stamp' => $line['log_stamp']))->cursor()->current();
	}

	/**
	 * Caches the rates in the memory for fast computations
	 */
	protected function loadRates() {
		$query = array(
			'$or' => array(
				array(
					'params.serving_networks' => array(
						'$exists' => true,
					),
				),
				array(
					'key' => 'UNRATED',
				),
			),
		);
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = $rates_coll->query($query)->cursor();
		foreach ($rates as $rate) {
			$rate->collection($rates_coll);
			if (is_array($rate['params']['serving_networks'])) {
				foreach ($rate['params']['serving_networks'] as $serving_network) {
					$this->rates['by_names'][$serving_network][] = $rate;
				}
			} else if (is_string($rate['params']['serving_networks'])) {
				$this->rates['by_regex'][$rate['params']['serving_networks']][] = $rate;
			} else if ($rate['key'] == 'UNRATED') {
				$this->rates['UNRATED'] = $rate;
			}
		}
	}

	/**
	 * "e" - data, "9" - outgoing(call/sms), "a" - incoming 
	 * @return number to rate by
	 */
	protected function number_to_rate($row) {
		if ($row['record_type'] == "e") {
			return NULL;
		} else if (($row['record_type'] == "9") && isset($row['called_number'])) {
			return $row->get('called_number');
		} else if (($row['record_type'] == "a") && isset($row['calling_number'])) {
			return $row->get('calling_number');
		} else {
			Billrun_Factory::log("Couldn't find rateable number for line : {$row['stamp']}");
		}
	}

}

?>
