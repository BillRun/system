<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Golan Csv generator class
 * require to generate csvs for comparison with older billing systems / charge using credit guard
 *
 * @package  Billing
 * @since    0.5
 */
class Generator_SubscriberSum extends Billrun_Generator_AggregatedCsv {

	public function __construct($options) {
		self::$type = 'subscribersum';
		parent::__construct($options);
	}

	protected function buildHeader() {
		$this->headers = array('plan', 'total_charge_subs', 'count_subs');
	}

	protected function buildAggregationQuery() {
		$match = array(
			'$match' => array(
				'billrun_key' => $this->stamp,
				'current_plan' => array(
					'$ne' => null,
				),
				'next_plan' => array(
					'$ne' => null,
				),
				'usaget' => 'flat',
			)
		);
		$group = array(
			'$group' => array(
				"_id" => '$next_plan',
				'sum' => array('$sum' => '$cost'),
				'subs_count' => array('$sum' => 1),
			),
		);
		$sort = array(
			'$sort' => array(
				'sum' => -1,
			),
		);
		$this->aggregation_array = array($match, $group, $sort);
	}

	protected function setCollection() {
		$this->collection = Billrun_Factory::db()->billrun_statsCollection();
	}

	protected function setFilename() {
		$this->filename = "subscriber_sum.csv";
	}

}
