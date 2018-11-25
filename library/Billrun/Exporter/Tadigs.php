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
	protected $tadigs = array();
	protected $periodStartTime = null;
	protected $periodEndTime = null;
	
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
		$this->loadTadigs();
		
		foreach ($this->rowsToExport as $row) {
			$tadig = $this->getTadig($row);
			if ($tadig === false) {
				Billrun_Log::getInstance()->log('Tadigs ' . $this->exporterType . ' exporter: Cannot get TADIG for row. stamp: ' . $row['stamp'], Zend_log::WARN);
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
			$mccMnc = $this->getMccMnc($row);
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
					Billrun_Log::getInstance()->log('Tadigs ' . $this->exporterType . ' exporter: duplicate definition for MCC-MNC. TADIG: ' . $mapping['tadig'] . ' and TADIG ' . $this->tadigs[$mccMnc], Zend_log::NOTICE);
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
		$mccMnc = $this->getMccMnc($row);
		return isset($this->tadigs[$mccMnc]) ? $this->tadigs[$mccMnc] : false;
	}
	
	/**
	 * extract MCC-MNC from the row
	 * 
	 * @param array $row
	 * @return string
	 */
	protected function getMccMnc($row) {
		$imsi = $row['imsi'];
		$mcc = substr($imsi, 0, 3);
		$mnc = substr($imsi, 3, 3);
		return $mcc . $mnc;
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

