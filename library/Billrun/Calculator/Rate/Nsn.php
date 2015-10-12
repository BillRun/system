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
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row) {
		$this->rowDataForQuery = array(
			'line_time' => $row->get('urt'),
			'called_number' => $row->get('called_number'),
		);
		
		return $this->getRateByParams();
	}

	/**
	 * Get a matching rate by config params
	 * @return Mongodloid_Entity the matched rate or false if none found
	 */
	protected function getRateByParams() {		
		$query = $this->getRateQuery();
		$matchedRateCursor = Billrun_Factory::db()->ratesCollection()->aggregate($query)->current();
		
		if (empty($matchedRate)) {
			return false;
		}
		return $matchedRate;
	}
	
	/**
	 * Builds aggregate query from config
	 * 
	 * @return string mongo query
	 */
	protected function getRateQuery() {
		$pipelines = Billrun_Config::getInstance()->getConfigValue('rate_pipeline.' . self::$type);
		$query = array();
		foreach ($pipelines as $currPipeline) {
			foreach ($currPipeline as $pipelineOperator => $pipeline) {
				$pipelineValue = '';
				if (is_array($pipeline)) {
					foreach ($pipeline as $key => $value) {
						if (isset($value['classMethod'])) {
							$pipelineValue[$key] = call_user_method($value['classMethod'], $this);
						} else {
							$pipelineValue[$key] = (is_numeric($value)) ? intval($value) : $value;
						}
					}
				} else {
					$pipelineValue = (is_numeric($pipeline)) ? intval($pipeline) : $pipeline;
				}

				$query[] = array('$' . $pipelineOperator => $pipelineValue);
			}
		}
		
		return $query;
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
