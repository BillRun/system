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
trait Billrun_Exporter_Traits_Tadigs {

	protected $exporterType = array();
	protected $tadigs = array();
	protected $periodStartTime = null;
	protected $periodEndTime = null;
	protected $tadigsStorageConfig = [
								'collection' => 'tadigs',
								'base_query' => [],
								'mcc_mnc_field' => 'mcc_mnc'
							  ];
	protected $filesExported =[];
	
	public function __construct($options = array()) {
		$this->periodEndTime = time();
		parent::__construct($options);
		//get tading data retrival configuration
		$this->tadigsStorageConfig = Billrun_Util::getIn($options,'configByType.exporter.tadigs.storage_config',$this->tadigsStorageConfig);

		$this->exporterType = $this->getExporterType();
	}
	
	/**
	 * see parent::handleExport
	 */
	public function generate() {
		$exported = [];
		$lines = $this->getLinesToExport();
		foreach ($lines as $tadig => $rows) {
			$options = array(
				'tadig' => $tadig,
				'data' => $rows,
				'time' => $this->getPeriodEndTime(),
			);
			$exporter = $this->getTadigExporter(array_merge($this->options,$options));
			$fileExported = $exporter->export();
			$exported[] = $fileExported;
		}
		$this->filesExported = $exported;
		return count($exported);
	}

	public function getGeneratedFiles() {
		return $this->filesExported;
	}
	
	/**
	 * gets exporter for single TADIG
	 */
	abstract protected function getTadigExporter($options);

	
	abstract protected function getExporterType();
	
	abstract protected function getQueryPeriod();
	


	/**
	 * see parent::getRecordData
	 */
	protected function getRecordData($row) {
		// return $row;
		$fieldsMapping = $this->getFieldsMapping($row);
		return $this->mapFields($fieldsMapping, $row);
	}
	
	/**
	 * get lines to export ordered by TADIGs as key, and stamps as values
	 * 
	 * @return array
	 */
	protected function getLinesToExport() {
		$ret = array();
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
			$ret[$tadig][] = $row;
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
			if($mccMnc) {
				$mccMncs[$mccMnc] = 1;
			}
		}
		$mccMncs = array_map(function($e){return (string) $e;},array_keys($mccMncs));
		
		$collection = Billrun_Factory::db()->{$this->tadigsStorageConfig['collection'].'Collection'}();
		$query = array_merge($this->tadigsStorageConfig['base_query'], [
						$this->tadigsStorageConfig['mcc_mnc_field']=> array(
							'$in' => array_values(array_unique($mccMncs)),
						),
					]);
		$mappings = $collection->query($query)->cursor();
		foreach ($mappings as $mapping) {
			$mccmncArr =  is_array($mapping['mcc_mnc']) ? $mapping['mcc_mnc'] : [$mapping['mcc_mnc']];
			foreach ($mccmncArr as $mccMnc) {
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

		$imsi = $this->getImsi($row);
		return $this->getMccMncFromImsi($imsi);
	}

	/**
	* Retrieves the IMSI (International Mobile Subscriber Identity) from a row of data using a mapping of IMSI fields.
	*
	* @param array $row The row of data containing IMSI fields.
	* @return string|null The IMSI value if found in any of the mapped fields, or null if not found.
	*/
	protected function getImsi($row) {
		$rowMappingFields = $this->config['row_mapping_fields'];
		foreach($rowMappingFields as $imsiField) {
			if( !empty(Billrun_Util::getIn($row,$imsiField,false)) ) {
				return Billrun_Util::getIn($row,$imsiField,false);
			}
		}
		return null;
	}
	
	/**
	* Extracts the Mobile Country Code (MCC) and Mobile Network Code (MNC) from an IMSI (International Mobile Subscriber Identity).
	*
	* @param string $imsi The IMSI to extract MCC and MNC from.
	* @return string containing the extracted MCC and MNC.
	*/
	protected function getMccMncFromImsi($imsi) {
		// Extract the MCC and MNC from the IMSI
		$mcc = substr($imsi, 0, 3);
		$mnc = substr($imsi, 3, 2);

		// Return the MCC and MNC as an associative array
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

