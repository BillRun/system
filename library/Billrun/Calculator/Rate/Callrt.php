<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for SMS records received in realltime
 *
 * @package  calculator
 * @since 4.0
 */
class Billrun_Calculator_Rate_Callrt extends Billrun_Calculator_Rate {

	static protected $type = 'callrt';

	/**
	 * Check if a given line should be rated.
	 * @param type $row
	 * @return type
	 */
	protected function shouldLineBeRated($row) {
		return true;
	}

	/**
	 * 
	 * @deprecated since version 2.9
	 */
	protected function getLineUsageType($row) {	
	}

	/**
	 * 
	 * @deprecated since version 2.9
	 */
	protected function getLineVolume($row) {
	}
	
	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row) {
//		$called_number = $this->get_called_number($row);
//		$line_time = $row->get('urt');
//		$usage_type = $row->get('usaget');
		$this->setRowDataForQuery($row);
//		$matchedRate = $this->getRateByParams($called_number, $usage_type, $line_time);
		$matchedRate = $this->getRateByParams($row);

		return $matchedRate;
	}
	
	/**
	 * method to identify the destination of the call
	 * 
	 * @param array $row billing line
	 * 
	 * @return string
	 */
	protected function get_called_number($row) {
		$called_number = $row->get('called_number');
		if (empty($called_number)) {
			$called_number = $row->get('connected_number');
			if (empty($called_number)) {
				$called_number = $row->get('dialed_digits');
			}
		}
		return $called_number;
	}
	
	/**
	 * Assistance function to generate 'prefix' field query with current row.
	 * 
	 * @return array query for 'prefix' field
	 */
	protected function getPrefixMatchQuery() {
		return array('$in' => Billrun_Util::getPrefixes($this->rowDataForQuery['called_number']));
	}
	
	protected function getAggregateId() {
		return array(
			"_id" => '$_id',
			"pref" => '$params.prefix');
	}
	
	protected function getRatesExistsQuery() {
		return array('$exists' => true);
	}
}
