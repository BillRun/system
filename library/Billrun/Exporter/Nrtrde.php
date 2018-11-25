<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing NRTRDE exporter
 *
 * @package  Billing
 * @since    2.8
 */
class Billrun_Exporter_Nrtrde extends Billrun_Exporter_Bulk {
	
	static protected $type = 'nrtrde';
	
	protected $tadigs = array();
	protected $periodStartTime = null;
	protected $periodEndTime = null;
	
	public function __construct($options = array()) {
		parent::__construct($options);
		
		$queryPeriod = $this->getConfig('query_period', '1 minutes');
		$this->periodEndTime = time();
		$this->periodStartTime = strtotime('-' . $queryPeriod, $this->periodEndTime);
	}
	
	/**
	 * see parent::handleExport
	 */
	public function handleExport() {
		$exported = array();
		$lines = $this->getLinesToExport();
		foreach ($lines as $tadig => $stamps) {
			$options = array(
				'tadig' => $tadig,
				'stamps' => $stamps,
				'time' => $this->periodEndTime,
			);
			$exporter = new Billrun_Exporter_Nrtrde_Tadig($options);
			$vpmnExported = $exporter->export();
			$exported = array_merge($exported, $vpmnExported);
		}
		return $exported;
	}
	
	/**
	 * see parent::getQuery
	 */
	protected function getQuery() {
		return array(
			'type' => 'nsn',
			'imsi' => array(
				'$regex' => '^(?!42508)',
			),
			'urt' => array(
				'$gte' => new MongoDate($this->periodStartTime),
				'$lte' => new MongoDate($this->periodEndTime),
			),
		);
	}
	
	/**
	 * see parent::getRecordData
	 */
	protected function getRecordData($row) {
		return $row;
	}
	
	/**
	 * get lines to export ordered by TADIGs as key, and stamps as values
	 * 
	 * @return array
	 */
	protected function getLinesToExport() {
		$ret = array();
		$this->loadRows();
		$this->loadTadigs();
		
		foreach ($this->rowsToExport as $row) {
			$tadig = $this->getTadig($row);
			if ($tadig === false) {
				Billrun_Log::getInstance()->log('NRTRDE exporter: Cannot get TADIG for row. stamp: ' . $row['stamp'], Zend_log::WARN);
				continue;
			}
			if (!isset($ret[$tadig])) {
				$ret[$tadig] = array();
			}
			$ret[$tadig][] = $row['stamp'];
		}
		
		return $ret;
	}
	
	/**
	 * load the TADIGs relevant for the lines received
	 */
	protected function loadTadigs() {
		$mccMncs = array();
		foreach ($this->rowsToExport as $row) {
			$mccMnc = Billrun_Util::getMccMnc($row['imsi']);
			$mccMncs[] = $mccMnc;
		}
		
		$collection = Billrun_Factory::db()->tadigsCollection();
		$query = array(
			'mcc_mnc' => array(
				'$in' => array_values(array_unique($mccMncs)),
			),
		);
		$mappings = $collection->query($query)->cursor();
		foreach ($mappings as $mapping) {
			foreach ($mapping['mcc_mnc'] as $mccMnc) {
				if (isset($this->tadigs[$mccMnc]) && $this->tadigs[$mccMnc] != $mapping['tadig']) {
					Billrun_Log::getInstance()->log('NRTRDE exporter: duplicate definition for MCC-MNC. TADIG: ' . $mapping['tadig'] . ' and TADIG ' . $this->tadigs[$mccMnc], Zend_log::NOTICE);
					continue;
				}
				$this->tadigs[$mccMnc] = $mapping['tadig'];
			}
		}
	}


	/**
	 * gets the relevant TADIG for the row
	 * 
	 * @param array $row
	 * @return string
	 */
	protected function getTadig($row) {
		$mccMnc = Billrun_Util::getMccMnc($row['imsi']);
		return isset($this->tadigs[$mccMnc]) ? $this->tadigs[$mccMnc] : false;
	}

}

