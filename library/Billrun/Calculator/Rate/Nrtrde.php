<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for nrtrde records
 *
 * @package  calculator
 * @since    2.9
 */
class Billrun_Calculator_Rate_Nrtrde extends Billrun_Calculator_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'nrtrde';

	/**
	 * Detecting an arate is optional for these usage types
	 * @var array
	 */
	protected $optional_usage_types = array();

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->optional_usage_types = isset($options['calculator']['optional_usage_types']) ? $options['calculator']['optional_usage_types'] : array('incoming_sms');
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 * @deprecated since version 2.9
	 */
	protected function getLineVolume($row, $usage_type) {
		return $row['usagev'];
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 * @deprecated since version 2.9
	 */
	protected function getLineUsageType($row) {
		return $row['usaget'];
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {
		$alpha = $row['alpha3'];
		$line_time = $row['urt'];
		if(!$this->isRatingNeeded($row,$usage_type)) {
			Billrun_Factory::log("Rating  for line {$row['stamp']}  is ignored due to configuration.");
			return false;
		}
		$number_to_rate = $this->number_to_rate($row);
		$call_number_prefixes = Billrun_Util::getPrefixes($number_to_rate);
		$call_number_prefixes[] = null;
		$aggregateBaseMatch = array(
			array(
				'$match' => array(
					 '$or' => [
							[ 'params.serving_networks' => new MongoRegex("/^$alpha/")],
							[ 'params.serving_networks' => "/.*/"]
						],
					'to' => array(
						'$gt' => $line_time,
					),
					'from' => array(
						'$lt' => $line_time,
					),
					'rates.'.$usage_type => array('$exists'=> 1 ),
				),
			),					
		);
		$aggregateSort =  array(
			
			array(
				'$sort' => array(
					'params.prefix' => -1,
				)
			),
			array(
				'$limit' => 1,
			)
		);
		$aggregateUnwind = array(array(
				'$unwind' => '$params.prefix',
			),
			array(
				'$match' => array(
					"params.prefix" => array (
						'$in' => $call_number_prefixes,
					),
				)
			),
		);
		$aggregateNoPrefixMatch = array(
			array(
				'$match' => array( 'params.prefix' => array()),
			),
		);
		$rates_coll = Billrun_Factory::db()->ratesCollection();
//		$rate = $rates_coll->aggregate($aggregate)->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'));
		$rate = $rates_coll->aggregate(array_merge($aggregateBaseMatch, $aggregateUnwind, $aggregateSort));
		if(empty($rate)) {
			$rate = $rates_coll->aggregate(array_merge($aggregateBaseMatch, $aggregateNoPrefixMatch,$aggregateSort));
		}
		
		if(!empty($rate)) {
			$obj_rate = new Mongodloid_Entity(reset($rate));
			$obj_rate->collection($rates_coll);
			return $obj_rate;
		} else {
			$query = array(
				'key' => 'UNRATED',
			);
			$cursor_rate = $rates_coll->query($query)->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'));
			if (!empty($cursor_rate)) {
				$UNrate = $cursor_rate->current();
				$UNrate->collection($rates_coll);
				return $UNrate;			
			}
		}
	}

	protected function isRatingNeeded($row, $usage_type) {
		$ignoreRatingRules = Billrun_Factory::config()->getConfigValue('nrtrde.calculator.ignore_rating');
		foreach($ignoreRatingRules as $ignoreRule) {
			$matched = true;
			foreach($ignoreRule as $ruleCompareType => $rules) {
				foreach($rules as $ruleField =>  $ruleVal) {
					$matched &= !empty($row[$ruleField]) && (
									($ruleCompareType == 'regex' &&  preg_match($ruleVal,$row[$ruleField])) ||
									($ruleCompareType == 'lt' &&  $ruleVal > $row[$ruleField]) ||
									($ruleCompareType == 'gt' &&  $ruleVal < $row[$ruleField]) ||
									($ruleCompareType == 'eq' &&  $ruleVal == $row[$ruleField])
								);
				}
			}
			if($matched) {
				return false;
			}

		}
		return true;
	}

	/**
	 * "e" - data, "9" - outgoing(call/sms), "a" - incoming 
	 * @return number to rate by
	 */
	protected function number_to_rate($row) {
		if (($row['record_type'] == "MTC") && isset($row['callingNumber'])) {
			return $row->get('callingNumber');
		} else if (($row['record_type'] == "MOC") && isset($row['connectedNumber'])) {
			return $row->get('connectedNumber');
		} else {
			Billrun_Factory::log("Couldn't find rateable number for line : {$row['stamp']}");
		}
	}

	public function isLineLegitimate($line) {
		return parent::isLineLegitimate($line) && $line['usaget'] != 'incoming_sms';
	}
}
