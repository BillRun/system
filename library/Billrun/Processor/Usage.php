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
	
	/**
	 * override price definitions
	 * @var type float
	 */
	protected $prepricedMapping = null;
	
	/**
	 * 
	 * the time zone field defined by the user
	 * @var type string
	 */
	protected $timeZone = null;
	
	/**
	 * 
	 * user fields to include in stamp calculation
	 * @var array
	 */
	protected $stampFields = array();

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
		if (!empty($options['processor']['timezone_field'])) {
			$this->timeZone = $options['processor']['timezone_field'];
		}

		$this->dateField = $options['processor']['date_field'];
		$this->prepricedMapping = Billrun_Factory::config()->getFileTypeSettings($options['file_type'], true)['pricing'];
		
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
			Billrun_Factory::dispatcher()->trigger('beforeLineMediation', array($this, static::$type, &$parsedRow));
			$row = $this->getBillRunLine($parsedRow);
			Billrun_Factory::dispatcher()->trigger('afterLineMediation', array($this, static::$type, &$row));
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
		$row['timezone'] = $datetime->getOffset();
		$row['type'] = static::$type;
		$row['usaget'] = $this->getLineUsageType($row);
		$usagev = $this->getLineUsageVolume($row['uf'], $row['usaget']);
		if ($usagev === false) {
			return false;
		}
		$row['usagev_unit'] = $this->usagevUnit;
		$row['usagev'] = $usagev;
		if ($this->isLinePrepriced($row['usaget'])) {
			$row['prepriced'] = true;
		}
		$row['connection_type'] = isset($row['connection_type']) ? $row['connection_type'] : 'postpaid';
		$row['stamp'] = md5(serialize(!empty($this->stampFields) ? $this->stampFields : $row));
		$row['source'] = self::$type;
		$row['file'] = basename($this->filePath);
		$row['log_stamp'] = $this->getFileStamp();
		$row['process_time'] = new MongoDate();
		return $row;
	}
	
	protected function getRowDateTime($row) {
		return Billrun_Processor_Util::getRowDateTime($row['uf'], $this->dateField, $this->dateFormat, $this->timeField, $this->timeFormat, $this->timeZone);
	}

	/**
	 * filter the record row data fields from the records
	 * (The required field can be written in the config using <type>.fields_filter)
	 * @param Array		$rawRow the full data record row.
	 * @return Array	the record row with filtered only the requierd fields in it  
	 * 					or if no filter is defined in the configuration the full data record.
	 */
	protected function filterFields($rawRow) {
		$parserFields = Billrun_Factory::config()->getParserStructure(static::$type);
		foreach ($parserFields as $field) {
			if (isset($field['checked']) && $field['checked'] === false) {
				unset($rawRow[$field['name']]); 
			}
		}
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

	protected function getLineUsageType($row) {
		$userFields = $row['uf'];
		if (!empty($this->usagetMapping)) {
			foreach ($this->usagetMapping as $usagetMapping) {
				if (!isset($usagetMapping['pattern'], $usagetMapping['src_field']) && !isset($usagetMapping['conditions'])) {
					$this->usagevUnit = isset($usagetMapping['unit']) ? $usagetMapping['unit'] : 'counter';
					$this->volumeType = isset($usagetMapping['volume_type']) ? $usagetMapping['volume_type'] : 'field';
					$this->volumeSrc = isset($usagetMapping['volume_src']) ? $usagetMapping['volume_src'] : array();
					return $usagetMapping['usaget'];
				}

				if (!isset($usagetMapping['conditions'])) { // backward compatibility
					if (isset($usagetMapping['src_field'])) {
						$usagetMapping['conditions'][0]['src_field'] = $usagetMapping['src_field'];
					}
					if (isset($usagetMapping['pattern'])) {
						$usagetMapping['conditions'][0]['pattern'] = $usagetMapping['pattern'];
					}
					$usagetMapping['conditions'][0]['op'] = '$eq';
				}

				$matchedConditions = true;
				foreach ($usagetMapping['conditions'] as $condition) {
					if (Billrun_Util::isValidRegex($condition['pattern'])) {
						$condition['op'] = '$regex';
					}
					if (empty($condition['op'])) {
						$condition['op'] = '$eq';
					}
					$query = array($condition['src_field'] => array($condition['op'] => $condition['pattern']));
					$matchedConditions = $matchedConditions && Billrun_Utils_Arrayquery_Query::exists($userFields, $query);
					if (!$matchedConditions) {
						break;
					}
				}

				if (($matchedConditions)) {
					$this->usagevUnit = isset($usagetMapping['unit']) ? $usagetMapping['unit'] : 'counter';
					$this->volumeType = isset($usagetMapping['volume_type']) ? $usagetMapping['volume_type'] : 'field';
					$this->volumeSrc = isset($usagetMapping['volume_src']) ? $usagetMapping['volume_src'] : array();
					// set the fields that will be used for line stamp calaulation
					$stampFields = Billrun_Util::getIn($usagetMapping, 'stamp_fields', []);
					$this->stampFields = $this->getStampFields($stampFields, $row);
					return $usagetMapping['usaget'];
				}
			}
		}
		return $this->defaultUsaget;
	}

	protected function getLineUsageVolume($userFields, $usaget = null, $falseOnError = false) {
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
				$usagev = Billrun_util::getIn($userFields, $usagevField);
				if (!is_null($usagev) && !is_null($usaget)) {
					$usagev = Billrun_Utils_Units::convertVolumeUnits($usagev, $usaget, $this->usagevUnit, true);
				}
				if (!is_null($usagev) && is_numeric($usagev)) {
					$volume += floatval($usagev);
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
	
	protected function getStampFields($paths = [], $row = []) {
		$stampFields = array();
		foreach ($paths as $fieldPath) {
			$fieldName = end(explode('.', $fieldPath));
			$stampFields[$fieldName] = Billrun_Util::getIn($row, $fieldPath, []);
		}
		if (!empty($row['type'])) {			
			$stampFields['type'] = $row['type'];
		}
		return $stampFields;
	}
	
	/**
	 * Checks if the line is prepriced (aprice was supposed to be received in the CDR)
	 * 
	 * @return boolean
	 */
	protected function isLinePrepriced($usaget) {
		return !empty($this->prepricedMapping[$usaget]);
	}
	
}
