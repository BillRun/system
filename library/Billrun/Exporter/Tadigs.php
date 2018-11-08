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
		$this->tadigs = $this->getTadigs();
	}
	
	/**
	 * get TADIGS used in the exporter in the format:
	 * [TADIG => ['egress_trunk' => [EGRESS_TRUNK_LIST], 'ingress_trunk' => [INGRESS_TRUNK_LIST]]]
	 */
	protected abstract function getTadigs();


	/**
	 * gets the relevant TADIG for the row
	 * 
	 * @param array $row
	 * @return string
	 */
	protected function getTadig($row) {
		$egressTrunk = $row['uf']['egress_trunk_group_id'];
		$ingressTrunk = $row['uf']['ingress_trunk_group_id'];
		foreach ($this->tadigs as $tadig => $params) {
			if (in_array($egressTrunk, $params['egress_trunk']) && in_array($ingressTrunk, $params['ingress_trunk'])) {
				return $tadig;
			}
		}
		return false;
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

