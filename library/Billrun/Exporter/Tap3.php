<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing TAP3 exporter
 * According to Specification Version Number 3, Release Version Number 12 (3.12)
 *
 * @package  Billing
 * @since    2.8
 */
class Billrun_Exporter_Tap3 extends Billrun_Exporter {

	static protected $type = 'tap3';

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

	// Helper OOP hacks
	protected $lastTadig = '';

	public function __construct($options = array()) {
		$this->periodEndTime = time();
		parent::__construct($options);
		//get tading data retrival configuration
		$this->tadigsStorageConfig = Billrun_Util::getIn($options,'exporter.tadigs.storage_config',$this->tadigsStorageConfig);

		$this->exporterType = $this->getExporterType();
		$this->loadConfig($options);
	}

	/**
	 * see parent::handleExport
	 */
	public function generate() {

		Billrun_Factory::log()->log("Billrun_Exporter::generate - starting to generate", Zend_Log::INFO);
        Billrun_Factory::dispatcher()->trigger('beforeExport', array($this));
        $this->beforeExport();

		$exported = [];
		$transactionCounter =0;

        $generatorOptions = $this->buildGeneratorOptions();
        $generatorOptions = $this->buildTap3Options($generatorOptions);
		$lines = $this->getLinesToExport();
		foreach ($lines as $tadig => $rows) {
			$this->createLogDB($this->getLogStamp());
			$options = array(
				'tadig' => $tadig,
				'data' => $rows,
				'time' => $this->getPeriodEndTime(),
			);
			$this->fileGenerator = $this->getTadigExporter(array_merge($generatorOptions,$options));
			$fileExported = $this->fileGenerator->export();
			$this->created_successfully &= !empty($fileExported);
			$exported[] = $fileExported;
			$transactionCounter = $this->fileGenerator->getTransactionsCounter();
		}
		$this->filesExported = $exported;

        if (!$this->created_successfully) {
            Billrun_Factory::log()->log("Export generator was faild writing to the file. File creation failed..", Zend_Log::ALERT);
            return false;
        }

        Billrun_Factory::log("Exported " . $transactionCounter . " lines from " . $this->getCollectionName() . " collection");
        return true;
	}

	public function getGeneratedFiles() {
		return $this->filesExported;
	}


	public function getFilePathForTadig($tadig) {
		$filePath = $this->getFilePath();
		$this->setTap3FileNameSttructure($tadig);
        return rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->getFileName();
	}

	public function getFileNameForTadig($tadig) {
		$this->setTap3FileNameSttructure($tadig);
        return  $this->getFileName();
	}


    protected function getExportFilePath() {
		return  $this->getFilePathForTadig('EMPTY');
    }


	protected function setTap3FileNameSttructure($tadig) {
		$this->fileName='';
		if (Billrun_Factory::config()->isProd()) {
			$pref = Billrun_Util::getIn($this->config,'file_name.prefix.prod', '');
		} else {
			$pref =  Billrun_Util::getIn($this->config,'file_name.prefix.test', '');
		}
		$suffix =  Billrun_Util::getIn($this->config,'file_name.suffix', '');
		$hpmnTadig = Billrun_Util::getIn($this->config,'hmpn_tadig', '');
		$vpmnTadig = $tadig;
		$sequenceNum =   Billrun_Util::getIn($this->config,'file_seq_param', '[[param1]]');
		$this->fileNameStructure =  $pref . $hpmnTadig . $vpmnTadig . $sequenceNum . $suffix;

	}

	protected function buildTap3Options($currentGenOptions) {
		$this->getFileName();
		$currentGenOptions['parent_exporter'] = $this;
		return $currentGenOptions;

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

	
	/**
	 * see parent::getTadigExporter
	 */
	protected function getTadigExporter($options) {
		return new Billrun_Exporter_Tap3_Tadig($options);
	}

	/**
	 * see trait::getExporterType
	 */
	protected function getExporterType() {
		return self::$type;
	}

	/**
	 * see trait::getQueryPeriod
	 */
	protected function getQueryPeriod() {
		return $this->getConfig('query_period', '1 minutes');
	}

	/**
	 * loads configuration files for exporter internal use
	 */
	protected function loadConfig($options) {
		$configPath = preg_replace('/^\./',APPLICATION_PATH,Billrun_Util::getIn($options,'exporter.config_path',''));
		$this->config = array_merge((new Yaf_Config_Ini($configPath))->toArray(),$this->config);
	}

	public function getFileType() {
		$lastTadig = $this->lastTadig ? '_'. $this->lastTadig : '';
        return parent::getFileType() . $lastTadig;
    }


}
