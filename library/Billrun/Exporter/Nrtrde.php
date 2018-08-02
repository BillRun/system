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
		
		foreach ($this->rowsToExport as $row) {
			$tadig = $this->getTadig($row);
			if (!isset($ret[$tadig])) {
				$ret[$tadig] = array();
			}
			$ret[$tadig][] = $row['stamp'];
		}
		
		return $ret;
	}
	
	/**
	 * gets the relevant TADIG for the row
	 * 
	 * @param array $row
	 * @return string
	 * @todo currently, returns MCC-MNC TADIGs should be taken from DB (BTGT-227) 
	 */
	protected function getTadig($row) {
		$mcc = substr($row['imsi'], 0, 3);
		$mnc = substr($row['imsi'], 3, 3);
		return $mcc . $mnc;
	}

}

