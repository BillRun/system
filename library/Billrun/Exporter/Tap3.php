<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing TAP3 exporter
 * According to Specification Version Number 3, Release Version Number 12 (3.12)
 *
 * @package  Billing
 * @since    2.8
 */
class Billrun_Exporter_Tap3 extends Billrun_Exporter_Asn1 {
	use Billrun_Exporter_Tadigs;

	static protected $type = 'tap3';

	/**
	 * see parent::getQuery
	 */
	protected function getQuery() { // TODO: fix query
		return array(
			'type' => array(
				'$in' => array(
					'ggsn',
					'nsn',
				),
			),
			'imsi' => array(
				'$exists' => 1
			),
			'urt' => array(
				'$gte' => new MongoDate($this->getPeriodStartTime()),
				'$lte' => new MongoDate($this->getPeriodEndTime()),
			),
		);
	}
	
	/**
	 * see parent::getTadigExporter
	 */
	protected function getTadigExporter($options) {
		return new Billrun_Exporter_Tap3_Tadig($options);
	}

	/**
	 * see trait::getExporterType
	 */
	protected function getExporterType() {
		return self::$type;
	}

	/**
	 * see trait::getQueryPeriod
	 */
	protected function getQueryPeriod() {
		return $this->getConfig('query_period', '1 minutes');
	}

	/**
	 * TAP3 file name is on TADIG level
	 */
	protected function getFileName() {
		return '';
	}

}
