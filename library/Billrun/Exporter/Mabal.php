<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing MABAL exporter
 *
 * @package  Billing
 * @since    2.8
 */
class Billrun_Exporter_Mabal extends Billrun_Exporter_Bulk {
	use Billrun_Exporter_Tadigs;
	
	static protected $type = 'mabal';
	
	/**
	 * see parent::getQuery
	 */
	protected function getQuery() {
		return array(
			'usaget' => 'call',
			'urt' => array(
				'$gte' => new MongoDate($this->getPeriodStartTime()),
				'$lte' => new MongoDate($this->getPeriodEndTime()),
			),
			'uf.ingress_trunk_group_id' => array(
				'$in' => array('437','438','485','486','462','309'),
			),
			'uf.egress_trunk_group_id' => array(
				'$in' => array('326','327'),
			),
		);
	}

	/**
	 * see parent::getTadigExporter
	 */
	protected function getTadigExporter($options) {
		return new Billrun_Exporter_Mabal_Tadig($options);
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
	 * see trait::getTadigs
	 */
	protected function getTadigs() {
		$tadigMapping = $this->getConfig('tadig_mapping', array());
		return array_map(function($params) {
			return array(
				'egress_trunk' => explode(',', $params['egress_trunk']),
				'ingress_trunk' => explode(',', $params['ingress_trunk']),
			);
		}, $tadigMapping);
	}

}

