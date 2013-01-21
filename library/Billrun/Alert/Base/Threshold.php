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

	protected $thresholds = array(	'upload' => 1000000,
									'download' => 1000000,
									'duration' => 2400,);
	
	/**
	 * execute aggregate
	 * TODO move to a class highter in the inheritance tree (see aggregator_ilds for resonsen why)
	 */
	public function aggregate() {
		$this->dispatcher->trigger('beforeThresholdAlertAggregate', array( 'extra' => array('type' => static::$type,'alertor' => $this)) );
		
		$aggregated = array();
		foreach ($this->data as $item) {
				//aggregate values on imsi
				$lineAggr = isset($aggregated[$this->getImisi($item)]) ? $aggregated[$this->getImisi($item)] : false ;
				$aggregated[$this->getImisi($item)] = $this->aggregateLine($lineAggr, $item);
		}
		$this->aggregated = $aggregated;
		
		$this->dispatcher->trigger('afterThresholdAlertAggregate', array('data' => &$this->aggregated , 'extra' =>  array('type' => static::$type,'alertor' => $this)) );

	}

	/**
	 * Retrive the Aggregated data.
	 * @return Array containg the aggregated data.
	 */
	public function getAggregated() {
		return $this->aggregated;
	}
	
	/**
	 * Retrive all the Alerts that should be raised sorted by imsi and msisdn (phone numbers)
	 * @return Array containing all the crossed thresholds
	 */
	public function getAlerts() {
		$this->dispatcher->trigger('beforeThresholdAlertDetected', array('data' => &$thresholds, 'extra' =>  array('alertor' => $this, 'type' => static::$type)) );
		$thresholds = array();
		
		foreach($this->aggregated as $imsi => $msisdns ) {
			$msisdnThrs = array();
			foreach($msisdns as  $msisdn => $aggr ) {
				$tmpholds = array();
				foreach($this->thresholds as $key => $thr) {
					if($aggr[$key] > $thr) {
						$tmpholds[$key] =  $aggr[$key];
					}
				}
				if( count($tmpholds) ) {
					$msisdnThrs[$msisdn] = array(	'lines' => $aggr['lines'],
													'usage' => $tmpholds, );		
				}
			}
			if( count($msisdnThrs) ) {
				$thresholds[$imsi] = $msisdnThrs ;		
			}
		}
		$this->dispatcher->trigger('afterThresholdAlertDetected', array('data' => &$thresholds, 'extra' => array('alertor' => $this, 'type' => static::$type)) );
		return count($thresholds) > 0 ? $thresholds : FALSE;
	}
	/**
	 * Handle all the alerts the where found in getAlerts
	 * @param type $thresholds the alerts that crossed the threshold retrived from getAlerts.
	 */
	public function handleAlerts($thresholds) {
		$this->dispatcher->trigger('beforeThresholdAlertHandled', array('data' => &$thresholds, 'extra' => array('type' => static::$type,'alertor' => $this)) );
		foreach ($thresholds as $imsi => $msisdns) {
			$this->dispatcher->trigger('beforeThresholdAlertHandling', array(	'data' => array('thresholds'=>&$thresholds,'key' => $imsi), 
																				'extra' =>  array( 'type' => static::$type, 'alertor' => $this)) );
			foreach ($msisdns as $msisdn => $thrs) {
				$stamp = md5(serialize($thrs['lines']));
				$results = array();
				foreach($thrs['usage'] as $type => $val) {
					 $results[$type] = $this->dispatcher->trigger('thresholdReached', array(
																			'args' => array( 
																				'type'=> static::$type,
																				'thresholdType'=> $type,
																				'threshold' => $this->thresholds[$type],
																				'value'=> $val, 
																				'imsi' => $imsi,
																				'msisdn' => $msisdn,
																				'stamp' => $stamp, ),
																		 'extra' => array('alertor' => $this)) );
				}
				foreach($results as $typeResults) {
					foreach($results as $key => $res) {
						if($res) {
							foreach($thrs['lines'] as $item) {
								//TODO think about it a little more same line can be responsible for serval events. 
								$this->updateLine(array(self::DB_STAMP => $stamp), $item);
							}
							break;
						}
					}
				}
			}
		}
		$this->dispatcher->trigger('afterThresholdAlertHandled', array( 'data' => &$thresholds, 'extra' => array('alertor' => $this, 'type' => static::$type)));
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

}
