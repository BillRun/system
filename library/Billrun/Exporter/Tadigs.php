<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Tadigs exporter, handles export for multiple Tadigs in separate files
 *
 * @package  Billing
 * @since    2.8
 */
trait Billrun_Exporter_Tadigs {

	protected $exporterType = array();
	protected $periodStartTime = null;
	protected $periodEndTime = null;
	protected $tadigs = array();
	
	public function __construct($options = array()) {
		$this->periodEndTime = time();
		parent::__construct($options);
		$this->exporterType = $this->getExporterType();
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
				'prod' => $this->isTadigOnProd($tadig),
				'stamps' => $stamps,
				'time' => $this->getPeriodEndTime(),
			);
			$exporter = $this->getTadigExporter($options);
			$tadigExported = $exporter->export();
			$exported = array_merge($exported, $tadigExported);
		}
		return $exported;
	}
	
	/**
	 * gets exporter for single TADIG
	 */
	abstract protected function getTadigExporter($options);

	abstract protected function getQuery();
	
	abstract protected function getExporterType();
	
	abstract protected function getQueryPeriod();
	
	abstract protected function loadRows();


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
			$tadig = Billrun_Util::getIn($row, 'plmn', false);
			if ($tadig === false) {
				Billrun_Log::getInstance()->log('Tadigs ' . $this->exporterType . ' exporter: Cannot get TADIG for row. stamp: ' . $row['stamp'], Zend_log::WARN);
				continue;
			}
			if (!isset($ret[$tadig])) {
				$ret[$tadig] = array();
			}
			
			$launchDate = $this->getTadigLaunchDate($tadig);
			if (empty($launchDate) || $row['urt']->sec >= $launchDate) {
				$ret[$tadig][] = $row['stamp'];
			}
		}
		
		return $ret;
	}
	
	protected function getTadigs() {
		if (empty($this->tadigs)) {
			$tadigs = array_column($this->rowsToExport, 'plmn');
			$collection = Billrun_Factory::db()->tadigsCollection();
			$query = array(
				'tadig' => array(
					'$in' => array_values(array_unique($tadigs)),
				),
			);
			$mappings = $collection->query($query)->cursor();
			foreach ($mappings as $mapping) {
				$this->tadigs[$mapping['tadig']] = $mapping;
			}
		}
		
		return $this->tadigs;
	}
	
	protected function getTadigLaunchDate($tadig) {
		$tadigs = $this->getTadigs();
		$launchDate = Billrun_Util::getIn($tadigs, [$tadig, 'launch_date'], false);
		return !empty($launchDate) ? $launchDate->sec : false;
	}
	
	protected function isTadigOnProd($tadig) {
		$launchDate = $this->getTadigLaunchDate($tadig);
		return !empty($launchDate) && $launchDate <= $this->periodStartTime;
	}
	
	/**
	 * extract MCC-MNC from the row
	 * 
	 * @param array $row
	 * @return string
	 */
	protected function getMccMnc($row) {
		$imsi = Billrun_Util::getImsi($row);
		return Billrun_Util::getMccMnc($imsi);
	}
	
	/**
	 * gets period start time
	 * 
	 * @return unixtimestamp
	 */
	protected function getPeriodStartTime() {
		if (is_null($this->periodStartTime)) {
			$queryPeriod = $this->getQueryPeriod();
			$this->periodStartTime = strtotime('-' . $queryPeriod, $this->periodEndTime);
		}
		return $this->periodStartTime;
	}
	
	/**
	 * gets period end time
	 * 
	 * @return unixtimestamp
	 */
	protected function getPeriodEndTime() {
		return $this->periodEndTime;
	}

}

