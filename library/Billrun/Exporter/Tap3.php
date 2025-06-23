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
	protected $tadig = 'EMPTY';
	protected $filename = '';//filename by tadig
	protected $periodStartTime = null;
	protected $periodEndTime = null;
	protected $tadigsStorageConfig = [
								'collection' => 'tadigs',
								'base_query' => [],
								'mcc_mnc_field' => 'mcc_mnc'
							  ];

	// Helper OOP hacks
	protected $lastTadig = '';

		protected $splitFilesKey = 'tadig';
	protected $logStamps = array();

	const DEFAULT_FILENAME_PARMS = [
		[
				"param" => "param1",
				"type" => "autoinc",
				"min_value" => 1,
				"date_group" => "e",
				"padding" => [
						"character" => "0",
						"length" => 5,
						"direction" => "left"
				],
				"value" => "now"
		],
];

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

        $generatorOptions = $this->buildGeneratorOptions();
        $generatorOptions = $this->buildTap3Options($generatorOptions);
				$shouldExportByCustomer =  Billrun_Util::getIn($generatorOptions['configByType'],'export_by_customer', false);
				if($shouldExportByCustomer){
					$this->splitFilesKey = 'aid';
					$exportLinesByKey = $this->getLinesByCustomerToExport();
				}else{
					$this->splitFilesKey = 'tadig';
					$exportLinesByKey = $this->getLinesToExport();
				}
				$res = $this->exportByType($exportLinesByKey, $generatorOptions);
        return $res;
	}

	protected function exportByType($exportLinesByKey, $generatorOptions){
		$exported = [];
		$transactionCounter =0;
		
		foreach ($exportLinesByKey as $key => $values) {
			$this->fileName = '';
			$rows = $values['rows'] ?? [];
			$this->tadig = $values['tadig'] ?? null;
			$this->getFileNameForTadig($this->tadig);
			$extraDataLog = [$this->splitFilesKey => $key];
			if(empty($rows)){
				$extraDataLog = array_merge($extraDataLog,['notification' => true]);
			}
			$this->logStamp = $this->getLogStamp($key);
			$this->logStamps[] = $this->logStamp;
			$this->createLogDB($this->logStamp, $extraDataLog);
			$options = array(
				'tadig' => $this->tadig,
				'data' => $rows,
				'time' => $this->getPeriodEndTime(),

			);
			$this->fileGenerator = $this->getTadigExporter(array_merge($generatorOptions,$options));
			$fileExported = $this->fileGenerator->export();
			$this->created_successfully &= !empty($fileExported);
			if(file_exists($fileExported)){
				$exported[] = $fileExported;
			}
			$transactionCounter += $this->fileGenerator->getTransactionsCounter();
		}
		$this->filesExported = $exported;
		if (!$this->created_successfully) {
				Billrun_Factory::log()->log("Export generator was faild writing to the file. File creation failed..", Zend_Log::ALERT);
				return false;
		}
		Billrun_Factory::log("Exported " . $transactionCounter . " lines from " . $this->getCollectionName() . " collection");
						$this->exportLimitRecords =  $transactionCounter == $this->limit ? true : false;		
		return true;
	}

	protected function buildGeneratorOptions() {
        $this->fileNameParams = isset($this->config['filename_params']) ? $this->config['filename_params'] : self::DEFAULT_FILENAME_PARMS;
        $this->fileNameStructure = isset($this->config['filename']) ? $this->config['filename'] : self::DEFAULT_FILENAME;
        $this->fileName = self::DEFAULT_FILENAME;
        //$options['file_name'] = $this->fileName;
        $options['file_type'] = $this->getType();
        $options['is_test_file'] = $this->isTestFile();
        $this->localDir = $this->getFilePath();
        $options['local_dir'] = $this->localDir;
        //$options['file_path'] = $this->localDir . DIRECTORY_SEPARATOR . $this->fileName;
        $rows = $this->loadRows();
				$this->rowsToExport = $this->loadExportRows($rows);
        $options['data'] = $this->rowsToExport;
        $this->headerToExport[0] = $this->getHeaderLine();
        $options['headers'] = $this->headerToExport;
        $this->footerToExport[0] = $this->getTrailerLine();
        $options['trailers'] = $this->footerToExport;
        $options['type'] = $this->config['generator']['type'];
        $options['force_header'] = $this->config['generator']['force_header'] ?? false;
        $options['force_footer'] = $this->config['generator']['force_footer'] ?? false;
        $options['configByType'] = $this->config;
        if ($options['type'] == 'separator') {
            $options['delimiter'] = $this->config['generator']['separator'] ?? ",";
        }
        return $options;
    }


	public function getGeneratedFiles() {
		return $this->filesExported;
	}


	public function getFilePathForTadig($tadig) {
		$filePath = $this->getFilePath();
    return rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->filename;
	}

	public function getFileNameForTadig($tadig) {
		$this->setTap3FileNameSttructure($tadig);
        return  parent::getFileName();
	}

	public function getSequenceNumber() {
		 return $this->params['param1'] ?? null;
	}
	protected function getExportFilePath() {
		return  $this->getFilePathForTadig($this->tadig);
	}
	protected function getFileName() {
		$this->filename = $this->getFileNameForTadig($this->tadig);
		return $this->filename;
	}

	protected function isTestFile() {
		//TODO add  spcific test morde  configuration per tadig / file
		return Billrun_Util::getIn($this->config,'is_test_file', false);
	}


	protected function setTap3FileNameSttructure($tadig) {
		if( !$this->isTestFile() ) {
			$pref = Billrun_Util::getIn($this->config,'filename_structure.prefix.prod', 'CD');
		} else {
			$pref =  Billrun_Util::getIn($this->config,'filename_structure.prefix.test', 'TD');
		}
		$suffix =  Billrun_Util::getIn($this->config,'filename_structure.suffix', '');
		$hpmnTadig = Billrun_Util::getIn($this->config,'hmpn_tadig', '');
		$vpmnTadig = $tadig;
		$sequenceNum =   Billrun_Util::getIn($this->config,'file_seq_param', '[[param1]]');
		$this->fileNameStructure =  $pref . $hpmnTadig . $vpmnTadig . $sequenceNum . $suffix;

	}

	protected function buildTap3Options($currentGenOptions) {
		$currentGenOptions['parent_exporter'] = $this;
		$currentGenOptions['filename_params'] = $this->params;

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

		foreach ($this->rowsToExport as $key => $row) {
			$tadig = $this->getTadig($row);
			if ($tadig === false) {
				Billrun_Log::getInstance()->log('Tadigs ' . $this->exporterType . ' exporter: Cannot get TADIG for row. stamp: ' . $row['stamp'], Zend_log::WARN);
				unset($this->rowsStamps[$row['stamp']]);
				continue;
			}
			if (!isset($ret[$tadig])) {
				$ret[$tadig]['rows'] = array();
			}
			$ret[$tadig]['rows'][] = $row;
			$ret[$tadig]['tadig']= $tadig;
		}
		Billrun_Factory::dispatcher()->trigger('afterGetLinesToExport', array(&$ret, $this->splitFilesKey, $this->config));
		return $ret;
	}

		/**
	 * get lines to export ordered by TADIGs as key, and stamps as values
	 *
	 * @return array
	 */
	protected function getLinesByCustomerToExport() {
		$ret = array();
		$this->loadTadigs();

		foreach ($this->rowsToExport as $key => $row) {
			$aid = $row['aid'];
			$tadig = $this->getTadig($row);
			if ($tadig === false) {
				Billrun_Log::getInstance()->log('Tadigs ' . $this->exporterType . ' exporter: Cannot get TADIG for row. stamp: ' . $row['stamp'], Zend_log::WARN);
				unset($this->rowsStamps[$row['stamp']]);
				continue;
			}
			if (!isset($ret[$aid])) {
				$ret[$aid] = array();
			}
			$ret[$aid]['rows'][] = $row;
			if(isset($ret[$aid]['tadig']) && $tadig != $ret[$aid]['tadig']){
				Billrun_Log::getInstance()->log('Tadigs ' . $this->exporterType . ' exporter: have different tadigs to the same account for aid : $aid. Cannot export row stamp: ' . $row['stamp'], Zend_log::WARN);
				unset($this->rowsStamps[$row['stamp']]);
				continue;
			}
			$ret[$aid]['tadig'] = $tadig;
		}
		Billrun_Factory::dispatcher()->trigger('afterGetLinesToExport', array(&$ret, $this->splitFilesKey, $this->config));
		return $ret;
	}

	/**
	 * load the TADIGs relevant for the lines received
	 */
	protected function loadTadigs() {
		$mccMncs = array();
		foreach ($this->rowsToExport as $row) {
			$mccMnc2 = $this->getMccMnc($row, 2);
			if($mccMnc2) {
				$mccMncs[$mccMnc2] = 1;
			}
			$mccMnc3 = $this->getMccMnc($row, 3);
			if($mccMnc3) {
				$mccMncs[$mccMnc3] = 1;
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
		$mccMnc3 = $this->getMccMnc($row, 3);
		$mccMnc2 = $this->getMccMnc($row, 2);
		return isset($this->tadigs[$mccMnc3]) ? $this->tadigs[$mccMnc3] : (isset($this->tadigs[$mccMnc2]) ? $this->tadigs[$mccMnc2] : false);
	}

	/**
	 * extract MCC-MNC from the row
	 *
	 * @param array $row
	 * @return string
	 */
	protected function getMccMnc($row, $mncDigits = 2) {

		$imsi = $this->getImsi($row);
		return $this->getMccMncFromImsi($imsi, $mncDigits);
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
	protected function getMccMncFromImsi($imsi, $mncDigits = 2) {
		// Extract the MCC and MNC from the IMSI
		$mcc = substr($imsi, 0, 3);
		$mnc = substr($imsi, 3,  $mncDigits);

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

		protected function logDB($stamp, $data) {
			foreach ($this->logStamps as $logStamp){
				parent::logDB($logStamp, $data);
			}  
		}

}

