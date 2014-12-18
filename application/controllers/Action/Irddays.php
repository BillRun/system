<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Refund action class
 *
 * @package  Action
 * @since    1.0
 */
class IrddaysAction extends Action_Base {

	/**
	 * method to execute the refund
	 * it's called automatically by the api main controller
	 * on vadofone
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute ird days API call", Zend_Log::INFO);
		$request = $this->getRequest();
		$sid = intval($request->get("sid"));
		$results = $this->count_days($sid);
		if (isset($results[0]["count"])) {
			$days = $results[0]["count"];
		} else {
			$days = 0;
		}
		$this->getController()->setOutput(array(
			'status' => 1,
			'desc' => 'success',
			'input' => $request,
			'details' => array(
				'days' => $days,
				'min_day' => 40,
				'max_day' => 40,
			)
		));
	}

	/**
	 * for subscriber with LARGE_PREIUM (?KOSHER) counts the number of days he used he's phone abroad
	 * in the current year based on fraud lines 
	 * @param type $sid
	 * @return number of days 
	 */
	public function count_days($sid) {
		$this_year = date("Y");
		$ggsn_fields = Billrun_Factory::config()->getConfigValue('ggsn.fraud.groups.vodafone15');
		$sender = Billrun_Factory::config()->getConfigValue('nrtrde.fraud.groups.vodafone15');
		$match = array(
			'$match' => array(
				'subscriber_id' => $sid,
				'plan' => array('$in' => array('LARGE_PREMIUM', 'LARGE_KOSHER_PREMIUM')),
				'$or' => array(
					array_merge(
						array(
						'type' => "ggsn",
						'record_opening_time' => new MongoRegex("/^$this_year/"),
						), $ggsn_fields
					),
					array(
						'type' => "nrtrde",
						'callEventStartTimeStamp' => new MongoRegex("/^$this_year/"),
						'sender' => array('$in' => $sender),
						'$or' => array(
							array(
								'record_type' => "MTC"
							),
							array(
								'record_type' => "MOC",
								'connectedNumber' => new MongoRegex('/^972/')
							)
						)
					),
				),
			)
		);

		$group = array(
			'$group' => array(
				'_id' => array('$substr' =>
					array(
						array('$ifNull' => array('$record_opening_time', '$callEventStartTimeStamp')),
						4,
						4
					)
				),
				count => array('$sum' => 1),
			)
		);

		$group2 = array(
			'$group' => array(
				'_id' => null,
				count => array('$sum' => 1),
			)
		);

		$res = Billrun_Factory::db()->linesCollection()->aggregate($match, $group, $group2);
		return $res;
	}

}
