<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for nsn records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Rate_Nsn extends Billrun_Calculator_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "nsn";
	
	public $rowDataForQuery = array();

	public function __construct($options = array()) {
		parent::__construct($options);
		//$this->loadRates();
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 * @deprecated since version 2.9
	 */
	protected function getLineVolume($row) {
		if (in_array($usage_type, array('call', 'incoming_call'))) {
			if (isset($row['duration'])) {
				return $row['duration'];
			} else if ($row['record_type'] == '31') { // terminated call
				return 0;
			}
		}
		if ($usage_type == 'sms') {
			return 1;
		}
		return null;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 * @deprecated since version 2.9
	 */
	protected function getLineUsageType($row) {
		switch ($row['record_type']) {
			case '08':
			case '09':
				return 'sms';
			case '02':
			case '12':
				return 'incoming_call';
			case '11':
			case '01':
			case '30':
			default:
				return 'call';
		}
		return 'call';
	}
	
	/**
	 * Assistance function to generate 'from' field query with current row.
	 * 
	 * @return array query for 'from' field
	 */
	protected function getFromTimeQuery() {
		return array('$lte' => $this->rowDataForQuery['line_time']);
	}

	/**
	 * Assistance function to generate 'to' field query with current row.
	 * 
	 * @return array query for 'to' field
	 */
	protected function getToTimeQuery() {
		return array('$gte' => $this->rowDataForQuery['line_time']);
	}
	
	/**
	 * Assistance function to generate 'prefix' field query with current row.
	 * 
	 * @return array query for 'prefix' field
	 */
	protected function getPrefixMatchQuery() {
		return array('$in' => Billrun_Util::getPrefixes($this->rowDataForQuery['called_number']));
	}

}
