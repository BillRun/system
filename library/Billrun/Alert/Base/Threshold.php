<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
//require_once __DIR__ . '/../../../application/golan/' . 'subscriber.php';

/**
 * Billing aggregator class for ilds records
 *
 * @package  calculator
 * @since    1.0
 */
abstract class Billrun_Alert_Base_Threshold extends Billrun_Alert {
	
	const DB_STAMP = "threshold_stamp";
	
	protected $aggregated = false;

	/**
	 * execute aggregate
	 * TODO move to a class highter in the inheritance tree (see aggregator_ilds for resonsen why)
	 */
	public function aggregate() {
		$this->dispatcher->trigger('beforeThresholdAlertAggregate', array('aggregator' => $this));
		
		$aggregated = array();
		foreach ($this->data as $item) {
				//aggregate values on imsi
				$lineAggr = isset($aggregated[$this->getImisi($item)]) ? $aggregated[$this->getImisi($item)] : false ;
				$aggregated[$this->getImisi($item)] = $this->aggregateLine($lineAggr, $item);
		}
		$this->aggregated = $aggregated;
		
		$this->dispatcher->trigger('afterThresholdAlertAggregate', array('aggregator' => $this));

	}

	/**
	 * Retrive the Aggregated data.
	 * @return Array containg the aggregated data.
	 */
	public function getAggregated() {
		return $this->aggregated;
	}
	
	/**
	 * load the data to aggregate
	 */
	abstract public function load($initData = true);

	/**
	 * update the CDR line with data to avoid another aggregation
	 *
	 * @param int $subscriber_id the subscriber id to update
	 * @param Mongodloid_Entity $line the billing line to update
	 *
	 * @return boolean true on success else false
	 */
	abstract public function updateLine($data, $line);
		
	/**
	 * Get identifier (IMSI) for a given DB CDR line.
	 * @param Array		$item The DB CDR line to get the identifier for.
	 * @return string	An identifier that corresponde with the CDR line.
	 */
	abstract protected function getImisi($item);
	
	
	/**
	 * Aggregate line values
	 * @param Array|bool	$lineAggr the currently aggregated data that related to the line  by it`s identifier (IMSI).
	 * @param Array			$item The DB CDR line to get the identifier for.
	 * @return string		The aggregated line values.
	 */
	abstract protected function aggregateLine($lineAggr, $item);
	
	protected function save($data) {
		foreach ($data as $coll_name => $coll_data) {
			$coll = $this->db->getCollection($coll_name);
			$coll->save($coll_data);
		}
		return true;
	}

}
