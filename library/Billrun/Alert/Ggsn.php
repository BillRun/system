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

	public function updateLine($data, $line) {
		$lineData = $line->getRawData();
		$newData = array_merge($lineData, $data);
		$line->setRawData($newData);
		$this->db->getCollection(self::lines_table)->save($line);
	}

}
