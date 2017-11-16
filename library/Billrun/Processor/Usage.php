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

	/**
	 * default usaget type used in case no other matches
	 * @var type string
	 */
	protected $defaultUsaget = 'general';
	
	/**
	 * usage type mapping options
	 * @var type array
	 */
	protected $usagetMapping = null;
	
	/**
	 * unit of measure used for the received volume
	 * @var type string
	 */
	protected $usagevUnit = 'counter';
	
	/**
	 * volume type used for the line, can be "field" and then taken from fields in the line, or "value" and then it's hard-coded value
	 * @var type string
	 */
	protected $volumeType = 'field';
	
	/**
	 * field names used to get line volume, or hard coded value
	 * @var type array / string
	 */
	protected $volumeSrc = array();
	
	/**
	 * the field's name where the date is located
	 * @var type  string
	 */
	protected $dateField = null;
	
	/**
	 * the date format (not mandatory)
	 * @var type string
	 */
	protected $dateFormat = null;
	
	/**
	 * the field's name where the time is located (in case of separate field time)
	 * @var type string
	 */
	protected $timeField = null;
	
	/**
	 * the time format (not mandatory, in case of separate field time)
	 * @var type string
	 */
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
		if (!empty($options['processor']['default_volume_type'])) {
			$this->volumeType = $options['processor']['default_volume_type'];
		}
		if (!empty($options['processor']['default_volume_src'])) {
			$this->volumeSrc = $options['processor']['default_volume_src'];
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
		$prepricedFields = $this->getPrepricedFields($row['usaget'], static::$type);
		if (!empty($prepricedFields['aprice_field'])) {
			$row['prepriced'] = true;
			$row['aprice'] = $this->getLineAprice($row['uf'], $prepricedFields);
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
					$this->volumeType = isset($usagetMapping['volume_type']) ? $usagetMapping['volume_type'] : 'field';
					$this->volumeSrc = isset($usagetMapping['volume_src']) ? $usagetMapping['volume_src'] : array();
					return $usagetMapping['usaget'];
				}
				if (isset($userFields[$usagetMapping['src_field']])) {
					if (!Billrun_Util::isValidRegex($usagetMapping['pattern'])) {
						$usagetMapping['pattern'] = "/^" . preg_quote($usagetMapping['pattern']) . "$/";
					}
					if (preg_match($usagetMapping['pattern'], $userFields[$usagetMapping['src_field']])) {
						$this->usagevUnit = isset($usagetMapping['unit']) ? $usagetMapping['unit'] : 'counter';
						$this->volumeType = isset($usagetMapping['volume_type']) ? $usagetMapping['volume_type'] : 'field';
						$this->volumeSrc = isset($usagetMapping['volume_src']) ? $usagetMapping['volume_src'] : array();
						return $usagetMapping['usaget'];
					}
				}
			}
		}
		return $this->defaultUsaget;
	}

	protected function getLineUsageVolume($userFields, $falseOnError = false) {
		$volume = 0;
		if ($this->volumeType === 'value') {
			if (!is_numeric($this->volumeSrc)) {
				Billrun_Factory::log('Usage volume value "' . $this->volumeSrc . '" is invalid ' . basename($this->filePath), Zend_Log::ALERT);
				return $falseOnError ? false : 0;
			}
			return floatval($this->volumeSrc);
		}
		$usagevFields = is_array($this->volumeSrc) ? $this->volumeSrc : array($this->volumeSrc);
		if (!empty($usagevFields)) {
			foreach ($usagevFields as $usagevField) {
				if (isset($userFields[$usagevField]) && is_numeric($userFields[$usagevField])) {
					$volume += floatval($userFields[$usagevField]);
				}
				else {
					Billrun_Factory::log('Usage volume field ' . $usagevField . ' is missing or invalid for file ' . basename($this->filePath), Zend_Log::ALERT);
					if ($falseOnError) {
						return false;
					}
				}
			}
		}
		return $volume;
	}

	/**
	 * Get the prepriced value received in the CDR
	 * 
	 * @param type $userFields
	 * @return aprice if the field found, false otherwise
	 */
	protected function getLineAprice($userFields, $prepricedFields) {
		$apriceField = isset($prepricedFields['aprice_field']) ? $prepricedFields['aprice_field'] : null;
		$apriceMult = isset($prepricedFields['aprice_field']) ? $prepricedFields['aprice_mult'] : null;
		if (isset($userFields[$apriceField]) && is_numeric($userFields[$apriceField])) {
			$aprice = $userFields[$apriceField];
			if (!is_null($apriceMult) && is_numeric($apriceMult)) {
				$aprice *= $apriceMult;
			}
			return $aprice;
		}
		
		Billrun_Factory::log('Price field "' . $apriceField . '" is missing or invalid for file ' . basename($this->filePath), Zend_Log::ALERT);
		return false;
	}
	
	protected function getPrepricedFields($usaget, $type) {
		$prepricedSettings = Billrun_Factory::config()->getFileTypeSettings($type, true)['pricing'];
		$prericedByUsaget = Billrun_Util::getIn($prepricedSettings, array($usaget), array());
		return $prericedByUsaget;
	}
}
