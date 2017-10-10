<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing generic processor
 */
class Billrun_Processor_Usage extends Billrun_Processor {

	protected $defaultUsaget = 'general';
	protected $usagetMapping = null;
	protected $usagevUnit = 'counter';
	protected $usagevFields = array();
	protected $apriceField = null;
	protected $dateField = null;
	protected $dateFormat = null;
	protected $timeField = null;
	protected $timeFormat = null;

	public function __construct($options) {
		parent::__construct($options);
		if (!empty($options['processor']['default_usaget'])) {
			$this->defaultUsaget = $options['processor']['default_usaget'];
		}
		if (!empty($options['processor']['default_unit'])) {
			$this->usagevUnit = $options['processor']['default_unit'];
		}
		if (!empty($options['processor']['usaget_mapping'])) {
			$this->usagetMapping = $options['processor']['usaget_mapping'];
		}
		if (empty($options['processor']['date_field'])) {
			return FALSE;
		}
		if (isset($options['processor']['volume_field'])) {
			if (!is_array($options['processor']['volume_field'])) {
				$options['processor']['volume_field'] = array($options['processor']['volume_field']);
			}
			$this->usagevFields = $options['processor']['volume_field'];
		}
		if (!empty($options['processor']['date_format'])){
			$this->dateFormat = $options['processor']['date_format'];
		}
		if (!empty($options['processor']['time_format'])){
			$this->timeFormat = $options['processor']['time_format'];
		}
		if (!empty($options['processor']['time_field'])){
			$this->timeField = $options['processor']['time_field'];
		}
		if (!empty($options['processor']['aprice_field'])){
			$this->apriceField = $options['processor']['aprice_field'];
		}
		
		$this->dateField = $options['processor']['date_field'];
	}

	protected function processLines() {
//		$this->buildHeader();
		$parser = $this->getParser();
		$parser->parse($this->fileHandler);
		$processedData = &$this->getData();
		$processedData['header'] = array('header' => TRUE); //TODO
		$processedData['trailer'] = array('trailer' => TRUE); //TODO
		$parsedData = $parser->getDataRows();
		$rowCount = 0;
		foreach ($parsedData as $parsedRow) {
			$row = $this->getBillRunLine($parsedRow);
			if (!$row){
				return false;
			}
			$row['row_number'] = ++$rowCount;
			$processedData['data'][$row['stamp']] = $row;
		}

//		$this->buildTrailer();

		return true;
	}

	public function getBillRunLine($rawLine) {
		$row['uf'] = $this->filterFields($rawLine);

		$datetime = $this->getRowDateTime($row);
		if (!$datetime) {
			Billrun_Factory::log('Cannot set urt for line. Data: ' . print_R($row, 1), Zend_Log::ALERT);
			return false;
		}
		
		$row['eurt'] = $row['urt'] = new MongoDate($datetime->format('U'));	
		$row['usaget'] = $this->getLineUsageType($row['uf']);
		$usagev = $this->getLineUsageVolume($row['uf']);
		$row['usagev_unit'] = $this->usagevUnit;
		$row['usagev'] = Billrun_Utils_Units::convertVolumeUnits($usagev, $row['usaget'], $this->usagevUnit, true);
		if ($this->isLinePrepriced()) {
			$row['prepriced'] = true;
			$row['aprice'] = $this->getLineAprice($row['uf']);
		}
		$row['connection_type'] = isset($row['connection_type']) ? $row['connection_type'] : 'postpaid';
		$row['stamp'] = md5(serialize($row));
		$row['type'] = static::$type;
		$row['source'] = self::$type;
		$row['file'] = basename($this->filePath);
		$row['log_stamp'] = $this->getFileStamp();
		$row['process_time'] = new MongoDate();
		return $row;
	}
	
	protected function getRowDateTime($row) {
		return Billrun_Processor_Util::getRowDateTime($row['uf'], $this->dateField, $this->dateFormat, $this->timeField, $this->timeFormat);
	}

	/**
	 * filter the record row data fields from the records
	 * (The required field can be written in the config using <type>.fields_filter)
	 * @param Array		$rawRow the full data record row.
	 * @return Array	the record row with filtered only the requierd fields in it  
	 * 					or if no filter is defined in the configuration the full data record.
	 */
	protected function filterFields($rawRow) {
		$row = array();
		$requiredFields = Billrun_Factory::config()->getConfigValue(static::$type . '.fields_filter', array(), 'array');
		if (!empty($requiredFields)) {
			foreach ($requiredFields as $field) {
				if (isset($rawRow[$field])) {
					$row[$field] = $rawRow[$field];
				}
			}
		} else {
			return $rawRow;
		}

		return $row;
	}

//	protected function buildHeader($line) {
//		$this->parser->setStructure($this->header_structure);
//		$this->parser->setLine($line);
//		$header = $this->parser->parse();
//		$header['source'] = self::$type;
//		$header['type'] = static::$type;
//		$header['file'] = basename($this->filePath);
//		$header['process_time'] = date(self::base_datetimeformat);
//		return $header;
//	}

	protected function buildTrailer($line) {
		$this->parser->setStructure($this->trailer_structure);
		$this->parser->setLine($line);
		$trailer = $this->parser->parse();
		$trailer['source'] = self::$type;
		$trailer['type'] = static::$type;
		$trailer['header_stamp'] = $this->data['header']['stamp'];
		$trailer['file'] = basename($this->filePath);
		$trailer['process_time'] = new MongoDate();
		return $trailer;
	}

	protected function getLineUsageType($userFields) {
		if (!empty($this->usagetMapping)) {
			foreach ($this->usagetMapping as $usagetMapping) {
				if (!isset($usagetMapping['pattern'], $usagetMapping['src_field'])) {
					$this->usagevUnit = isset($usagetMapping['unit']) ? $usagetMapping['unit'] : 'counter';
					return $usagetMapping['usaget'];
				}
				if (isset($userFields[$usagetMapping['src_field']])) {
					if (!Billrun_Util::isValidRegex($usagetMapping['pattern'])) {
						$usagetMapping['pattern'] = "/^" . preg_quote($usagetMapping['pattern']) . "$/";
					}
					if (preg_match($usagetMapping['pattern'], $userFields[$usagetMapping['src_field']])) {
						$this->usagevUnit = isset($usagetMapping['unit']) ? $usagetMapping['unit'] : 'counter';
						return $usagetMapping['usaget'];
					}
				}
			}
		}
		return $this->defaultUsaget;
	}

	protected function getLineUsageVolume($userFields) {
		$volume = 0;
		if (!empty($this->usagevFields)) {
			foreach ($this->usagevFields as $usagevField) {
				if (isset($userFields[$usagevField]) && is_numeric($userFields[$usagevField])) {
					$volume += intval($userFields[$usagevField]);
				}
				else {
					Billrun_Factory::log('Usage volume field ' . $usagevField . ' is missing or invalid for file ' . basename($this->filePath), Zend_Log::ALERT);
				}
			}
		}
		return $volume;
	}
	
	/**
	 * Checks if the line is prepriced (aprice was supposed to be received in the CDR)
	 * 
	 * @return boolean
	 */
	protected function isLinePrepriced() {
		return !empty($this->apriceField);
	}

	/**
	 * Get the prepriced value received in the CDR
	 * 
	 * @param type $userFields
	 * @return aprice if the field found, false otherwise
	 */
	protected function getLineAprice($userFields) {
		if (isset($userFields[$this->apriceField]) && is_numeric($userFields[$this->apriceField])) {
			return $userFields[$this->apriceField];
		}
		
		Billrun_Factory::log('Price field "' . $this->apriceField . '" is missing or invalid for file ' . basename($this->filePath), Zend_Log::ALERT);
		return false;
	}

}
