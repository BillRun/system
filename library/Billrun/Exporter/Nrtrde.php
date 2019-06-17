<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing NRTRDE exporter
 *
 * @package  Billing
 * @since    2.8
 */
class Billrun_Exporter_Nrtrde extends Billrun_Exporter_Bulk {
	use Billrun_Exporter_Tadigs;
	
	static protected $type = 'nrtrde';
	
	/**
	 * see parent::getQuery
	 */
	protected function getQuery() {
		return array(
			'type' => array(
				'$in' => array(
					'sgsn',
					'nsn',
				),
			),
			'incoming_roaming' => true,
			'usagev' => ['$gt' => 0], //TODO: check if it's possible that we will want to charge line with usagev 0
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
		return new Billrun_Exporter_Nrtrde_Tadig($options);
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

}

