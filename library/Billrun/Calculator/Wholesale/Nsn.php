<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing wholesale calculator class for NSN records 
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Wholesale_Nsn extends Billrun_Calculator_Wholesale {

	const MAIN_DB_FIELD = 'pzone';

	protected $ratingField = self::MAIN_DB_FIELD;

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->loadRates();
	}

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines = $this->getQueuedLines(array());  //array('type'=> 'nsn')
		return $lines;
	}

	/**
	 * Write the calculation into DB
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array($row, $this));
		//Billrun_Factory::log()->log("Line start : getLineZone  start : ".microtime(true));
		$rate = $this->getLineZone($row, $row['usaget']);
		//Billrun_Factory::log()->log(" getLineZone  end : ".microtime(true));
		$current = $row->getRawData();

		$added_values = array(
			$this->ratingField => $rate instanceof Mongodloid_Entity ? $rate->createRef() : $rate,
		);

		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array($row, $this));
		return $row;
	}

	/**
	 * TODO remove
	 * @see Billrun_Calculator_Rate::getLineRate
	 *
	 */
	protected function getLineZone($row, $usage_type) {
		//TODO  change this  to be configurable.
		if ($usage_type == 'call') {
			$called_number = $row->get('called_number');
		} else {
			$called_number = preg_replace('/[^\d]/', '', preg_replace('/^0+/', '', ( $row['called_number'])));
			if (strlen($called_number) < 10) {
				$called_number = preg_replace('/^(?!972)/', '972', $called_number);
			}
		}

//		Billrun_Factory::log()->log($called_number);
		$line_time = $row->get('urt');

		$called_number_prefixes = $this->getPrefixes($called_number);
		$carrier_cg = $row->get('out_circuit_group');
		$matchedRate = false;
		if ($usage_type == 'call') {
			foreach ($called_number_prefixes as $prefix) {
				if (isset($this->rates[$prefix])) {
					foreach ($this->rates[$prefix] as $rate) {
						if ($rate['from'] <= $line_time && $rate['to'] >= $line_time) {
							foreach ($rate['params']['out_circuit_group'] as $groups) {
								if ($groups['from'] <= $carrier_cg && $groups['to'] >= $carrier_cg) {
									$matchedRate = $rate;
								}
							}
						}
					}
				}
			}
		} else {
			foreach ($called_number_prefixes as $prefix) {
				if (isset($this->rates[$prefix])) {
					foreach ($this->rates[$prefix] as $rate) {
						if ($rate['from'] <= $line_time && $rate['to'] >= $line_time) {
							$matchedRate = $rate;
						}
					}
				}
			}
		}
		return $matchedRate;
		/*
		  $rates = Billrun_Factory::db()->ratesCollection();
		  $zoneKey= false;

		  $base_match = array(
		  '$match' => array(
		  'params.prefix' => array(
		  '$in' => $called_number_prefixes,
		  ),
		  'rates.' . $usage_type => array('$exists' => true),
		  'from' => array(
		  '$lte' => $line_time,
		  ),
		  'to' => array(
		  '$gte' => $line_time,
		  ),
		  )
		  );

		  if($usage_type == 'call') {
		  $carrier_cg = $row->get('out_circuit_group');
		  $base_match['$match']['params.out_circuit_group'] = array(
		  '$elemMatch' => array(
		  'from' => array(
		  '$lte' => $carrier_cg,
		  ),
		  'to' => array(
		  '$gte' => $carrier_cg
		  )
		  )
		  );
		  }

		  $unwind = array(
		  '$unwind' => '$params.prefix',
		  );

		  $sort = array(
		  '$sort' => array(
		  'params.prefix' => -1,
		  ),
		  );

		  $match2 = array(
		  '$match' => array(
		  'params.prefix' => array(
		  '$in' => $called_number_prefixes,
		  ),
		  )
		  );

		  $matched_rates = $rates->aggregate($base_match, $unwind, $sort, $match2);
		  if (!empty($matched_rates)) {
		  $zoneKey =new Mongodloid_Entity(reset($matched_rates),$rates);
		  }
		  if( $matchedRate['key'] != $zoneKey['key']) {
		  Billrun_Factory::log()->log("NO MATCH !!!! : " . print_r($row->getRawData(),1). " current rate : " .print_r($matchedRate->getRawData(),1) . "  :  " . print_r($zoneKey->getRawData(),1));
		  }

		  return $zoneKey;
		 */
	}

	/**
	 * load the ggsn rates to be used later.
	 */
	protected function loadRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = $rates_coll->query()->cursor();
		$this->rates = array();
		foreach ($rates as $rate) {
			$rate->collection($rates_coll);
			if (isset($rate['params']['prefix'])) {
				foreach ($rate['params']['prefix'] as $prefix) {
					$this->rates[$prefix][] = $rate;
				}
			} else {
				$this->rates['noprefix'][] = $rate;
			}
		}
		Billrun_Factory::log()->log("Loaded " . count($this->rates) . " rates");
	}

	/**
	 * get all the prefixes from a given number
	 * @param type $str
	 * @return type
	 */
	protected function getPrefixes($str) {
		//TODO  change this  to be configurable.
		$str = preg_replace("/^01\d/", "", $str);
		$prefixes = array();
		for ($i = 0; $i < strlen($str); $i++) {
			$prefixes[] = substr($str, 0, $i + 1);
		}
		return $prefixes;
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate()
	 */
	public function isLineLegitimate($line) {
		return $line['type'] == 'nsn' &&
			in_array($line['usaget'], array('call', 'sms', 'incoming_call')) &&
			in_array($line['record_type'], $this->wholesaleRecords);
	}

}
