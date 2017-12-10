<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
	protected function getLineVolume($row) {
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
	protected function getLineRate($row) {
		$aplha3 = $row['alpha3'];
		$line_time = $row['urt'];
		$number_to_rate = $this->number_to_rate($row);
		$call_number_prefixes = Billrun_Util::getPrefixes($number_to_rate);
		$aggregate = array(
			array(
				'$match' => array(
					'params.serving_networks' => new MongoRegex("/^$aplha3/"),
					'to' => array(
						'$gt' => $line_time,
					),
					'from' => array(
						'$lt' => $line_time,
					),
				),
			),
			array(
				'$unwind' => '$params.prefix',
			),
			array(
				'$match' => array(
					"params.prefix" => array(
						'$in' => $call_number_prefixes,
					)
				)
			),
			array(
				'$sort' => array(
					'params.prefix' => -1,
				)
			),
			array(
				'$limit' => 1,
			)
		);
		$rates_coll = Billrun_Factory::db()->ratesCollection();
//		$rate = $rates_coll->aggregate($aggregate)->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'));
		$rate = $rates_coll->aggregate($aggregate);
		if (!empty($rate)) {
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

	/**
	 * "e" - data, "9" - outgoing(call/sms), "a" - incoming 
	 * @return number to rate by
	 */
	protected function number_to_rate($row) {
		if (($row['record_type'] == "MTC") && isset($row['callingNumber'])) {
			return $row->get('calling_number');
		} else if (($row['record_type'] == "MOC") && isset($row['connectedNumber'])) {
			return $row->get('connectedNumber');
		} else {
			Billrun_Factory::log("Couldn't find rateable number for line : {$row['stamp']}");
		}
	}

}

?>
