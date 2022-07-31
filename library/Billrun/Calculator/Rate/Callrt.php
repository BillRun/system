<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
	protected $usaget = 'call';

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['usaget'])) {
			$this->usaget = $options['usaget'];
		}
	}

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
	 * method to identify the destination of the call
	 * 
	 * @param array $row billing line
	 * 
	 * @return string
	 */
	protected function get_called_number($row) {
		$called_number = $row->get('called_number');
		if (empty($called_number)) {
			$called_number = $row->get('dialed_digits');
			if (empty($called_number)) {
				$called_number = $row->get('connected_number');
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
		return array('$in' => Billrun_Util::getPrefixes($this->getCleanNumber($this->rowDataForQuery['called_number'])));
	}

	protected function getAggregateId() {
		return array(
			"_id" => '$_id',
			"pref" => '$params.prefix',
			"msc" => '$params.msc'
		);
	}

	protected function getRatesExistsQuery($row, $key) {
		$keyUsaget = str_replace('rates.', '', $key);
		if ($this->usaget === $keyUsaget) {
			return array(
				'$exists' => true,
				'$ne' => array(),
			);
		}
		return null;
	}

}
