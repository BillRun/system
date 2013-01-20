<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing aggregator class for ilds records
 *
 * @package  calculator
 * @since    1.0
 */
class Billrun_Alert_Ggsn extends Billrun_Alert_Base_Threshold {

	static protected $type = 'ggsn';
	
	protected $thresholds = array(	'upload' => 1000000,
									'download' => 1000000,
									'duration' => 2400,);
	
	public function __construct($options = array()) {
		parent::__construct($options);
		
		if($this->config->alert && $this->config->alert->ggsn) {
			$this->thresholds = array(	'upload' => $this->config->alert->ggsn->upload_threshold,
										'download' => $this->config->alert->ggsn->download_threshold,
										'duration' => $this->config->alert->ggsn->duration_threshold,);
		}
	}

	/**
	 * load the data to aggregate
	 */
	public function load($initData = true) {
		$lines = $this->db->getCollection(self::lines_table)->query()
			->equals("type", 'egsn')->notExists(self::DB_STAMP);

		if ($initData) {
			$this->data = array();
		}


		foreach ($lines as $entity) {
			$this->data[] = $entity;
		}

		$this->log->log("aggregator entities loaded: " . count($this->data), Zend_Log::INFO);
	}

	protected function save($data) {
		foreach ($data as $coll_name => $coll_data) {
			$coll = $this->db->getCollection($coll_name);
			$coll->save($coll_data);
		}
		return true;
	}

	protected function aggregateLine($lineAggr, $item) {
		if(!$lineAggr) {
			$lineAggr = array();
		}
		$msisdn = $item->get('served_msisdn');
		if(!isset($lineAggr[$msisdn])) {
			$lineAggr[$msisdn] = array(
								'upload'=> 0,
								'download'=> 0,
								'duration'=> 0,
								'lines' => array(),
							);
		}
		$lineAggr[$msisdn]['upload'] += floatval($item->get('fbc_uplink_volume'));
		$lineAggr[$msisdn]['download'] += floatval($item->get('fbc_downlink_volume'));
		$lineAggr[$msisdn]['duration'] += floatval($item->get('duration'));
		$lineAggr[$msisdn]['lines'][] = $item;
		return $lineAggr;
	}

	protected function getImisi($item) {
		return $item->get('served_imsi');
		
	}

	public function getThresholdsReached() {
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
		return count($thresholds) > 0 ? $thresholds : FALSE;
	}

	public function handleThresholds($thresholds) {
		foreach ($thresholds as $imsi => $msisdns) {
			foreach ($msisdns as $msisdn => $thrs) {
				$stamp = md5(serialize($thrs['lines']));
				$results = array();
				foreach($thrs['usage'] as $type => $val) {
					 $results[$type] = $this->dispatcher->trigger('thresholdReached', array(	'alertor' => $this,
																			'args' => array( 
																				'type'=> static::$type,
																				'thresholdType'=> $type,
																				'threshold' => $this->thresholds[$type],
																				'value'=> $val, 
																				'imsi' => $imsi,
																				'msisdn' => $msisdn,
																				'stamp' => $stamp, ),
																		));
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
	}

	public function updateLine($data, $line) {
		$lineData = $line->getRawData();
		$newData = array_merge($lineData, $data);
		$line->setRawData($newData);
		$this->db->getCollection(self::lines_table)->save($line);
	}

}
