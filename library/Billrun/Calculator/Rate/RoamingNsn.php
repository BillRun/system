<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for incoming roaming NSN records
 *
 * @package  calculator
 */
class Billrun_Calculator_Rate_RoamingNsn extends Billrun_Calculator_Rate_Nsn {
	use Billrun_Traits_IncomingRoaming {
		getRoamingRateQuery as incomingRoamingGetRoamingRateQuery;
	}
	
	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		if ($row['record_type'] == '09') {
			return 'incoming_sms';
		}
		
		return parent::getLineUsageType($row);
	}
	
	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 */
	protected function getLineVolume($row, $usage_type) {
		if (in_array($usage_type, array('sms', 'incoming_sms'))) {
			return 1;
		}
		
		return parent::getLineVolume($row, $usage_type);
	}
	
	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {
		return $this->getRoamingLineRate($row, $usage_type);
	}
	
	/**
	 * @see Billrun_Calculator_Rate::getAdditionalProperties
	 */
	protected function getAdditionalProperties() {
		$props = parent::getAdditionalProperties();
		$props[] = 'plmn';
		return $props;
	}
	
	protected function getRoamingRateQuery($row, $usage_type) {
		$query = $this->incomingRoamingGetRoamingRateQuery($row, $usage_type);
		if (!$query) {
			return false;
		}
		$alpha3 = $this->getAlpha3($row, $usage_type);
		if (!$alpha3) {
			return false;
		}
		
		$query['params.roaming_alpha3'] = array(
			'$in' => array($alpha3, null), // null is for default rate where alpha3 field does not exist
		);
		
		// assuming rates has only alpha3 OR zone (and not both)
		$zone = $this->getZone($alpha3, $row);
		$query['params.roaming_zone'] = array(
			'$in' => array($zone, null), // null is for default rate where zone field does not exist
		);

		return $query;
	}
	
	protected function getRoamingRateSort($row, $usage_type) {
		return array(
			'params.roaming_alpha3' => -1,
			'params.roaming_zone' => -1,
		);
	}
	
	protected function getAlpha3($row, $usage_type) {
		$prefixes = Billrun_Util::getPrefixes($row['called_number']);
		if (in_array('972', $prefixes)) {
			return 'ISR';
		}
		$match = array(
			'from' => array(
				'$lte' => new MongoDate($row['urt']->sec),
			),
			'to' => array(
				'$gte' => new MongoDate($row['urt']->sec),
			),
			'alpha3' => array(
				'$exists' => true,
			),
			'kt_prefixes' => array(
				'$in' => $prefixes,
			),
		);
		$unwind = '$kt_prefixes';
		$group = array(
			'_id' => array(
				'_id' => '$_id',
				'pref' => '$kt_prefixes',
			),
			'kt_prefixes' => array(
				'$first' => '$kt_prefixes',
			),
			'key' => array(
				'$first' => '$key',
			),
			'alpha3' => array(
				'$first' => '$alpha3',
			),
		);
		$match2 = array(
			'kt_prefixes' => array(
				'$in' => $prefixes,
			),
		);
		$sort = array(
			'kt_prefixes' => -1,
		);
		$aggregateQuery = array(
			array('$match' => $match),
			array('$unwind' => $unwind),
			array('$group' => $group),
			array('$match' => $match2),
			array('$sort' => $sort),
			array('$limit' => 1),
		);
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rate = $rates_coll->aggregate($aggregateQuery);
		if (empty($rate) || !isset($rate[0]['alpha3'])) {
			return '';
		}
		return $rate[0]['alpha3'];
	}
	
	protected function getZone($alpha3, $row) {
		$zones_coll = Billrun_Factory::db()->zonesCollection();

		$query = [
			'from' => [
				'$lte' => new MongoDate($row['urt']->sec),
			],
			'to' => [
				'$gte' => new MongoDate($row['urt']->sec),
			],
			'alpha3' => [
				'$in' => [$alpha3],
			],
		];
		
		$zone = $zones_coll->query($query)->cursor()->current();
		
		if (!$zone || $zone->isEmpty()) {
			return null;
		}
		
		return $zone['zone'];
	}
	
}
