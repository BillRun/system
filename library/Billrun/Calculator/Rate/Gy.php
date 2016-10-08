<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
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
	 * Set data used in inner function to find the rate of the line
	 * 
	 * @param type $row current line to find it's rate
	 */
	protected function setRowDataForQuery($row) {
		parent::setRowDataForQuery($row);
		$mscc_data = $row->get('mscc_data');
		if (isset($mscc_data[0]['rating_group'])) {
			$this->rowDataForQuery['rating_group'] = $mscc_data[0]['rating_group'];
		}
	}

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
			"mcc" => '$params.mcc',
			"rating_group" => '$params.rating_group'
		);
	}
	
	protected function getRatingGroupMatchQuery() {
		return array('$in' => array($this->rowDataForQuery['rating_group']));
	}

}
