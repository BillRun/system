<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator rate class
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
 * @since    4.0
 * @todo Merge to general internet data rate calculator
 *
 */
class Billrun_Calculator_Rate_Gy extends Billrun_Calculator_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'gy';

	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 * @deprecated since version 2.9
	 */
	protected function getLineVolume($row) {
		return $row['msccData']['usedUnits'];
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 * @deprecated since version 2.9
	 */
	protected function getLineUsageType($row) {
		return 'data';
	}

	/**
	 * return the data rate key
	 * @return string
	 * @deprecated since version 4.3
	 */
	protected function getDataRateKey() {
		return 'INTERNET_BILL_BY_VOLUME';
	}

	protected function getExistsQuery() {
		return array(
			'$exists' => true,
			'$ne' => array(),
		);
	}

	protected function getAggregateId() {
		return array(
			"_id" => '$_id',
			"mcc" => '$params.mcc'
		);
	}

}
