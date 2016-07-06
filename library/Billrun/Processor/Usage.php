<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing generic processor
 */
class Billrun_Processor_Usage extends Billrun_Processor {

	protected $defaultUsaget = 'general';
	protected $usagetMapping = null;
	protected $usagevField = null;
	protected $dateField = null;
	protected $dateFormat = null;

	public function __construct($options) {
		parent::__construct($options);
		if (!empty($options['processor']['default_usaget'])) {
			$this->defaultUsaget = $options['processor']['default_usaget'];
		}
		if (!empty($options['processor']['usaget_mapping'])) {
			$this->usagetMapping = $options['processor']['usaget_mapping'];
		}
		if (empty($options['processor']['date_field'])) {
			return FALSE;
		}
		if (!empty($options['processor']['volume_field'])) {
			$this->usagevField = $options['processor']['volume_field'];
		}
		if (!empty($options['processor']['date_format'])){
			$this->dateFormat = $options['processor']['date_format'];
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
			$row['row_number'] = ++$rowCount;
			$processedData['data'][$row['stamp']] = $row;
		}

//		$this->buildTrailer();

		return true;
	}

	public function getBillRunLine($rawLine) {
		$row = $this->filterFields($rawLine);
		if (!is_null($this->dateFormat)){
			$datetime = DateTime::createFromFormat($this->dateFormat, $row[$this->dateField]);
		}
		else{
			$date = strtotime($row[$this->dateField]);
			$datetime = new DateTime();
			$datetime->setTimestamp($date);
		}
			
		$row['urt'] = new MongoDate($datetime->format('U'));
		$row['usaget'] = $this->getLineUsageType($row);
		$row['usagev'] = $this->getLineUsageVolume($row);
		$row['stamp'] = md5(serialize($row));
		$row['type'] = static::$type;
		$row['source'] = self::$type;
		$row['file'] = basename($this->filePath);
		$row['log_stamp'] = $this->getFileStamp();
		$row['process_time'] = date(self::base_dateformat);
		return $row;
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
//		$header['process_time'] = date(self::base_dateformat);
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
		$trailer['process_time'] = date(self::base_dateformat);
		return $trailer;
	}

	protected function getLineUsageType($row) {
		if (!empty($this->usagetMapping)) {
			foreach ($this->usagetMapping as $usagetMapping) {
				if (preg_match($usagetMapping['pattern'], $row[$usagetMapping['src_field']])) {
					if (isset($usagetMapping['usaget'])) {
						return $usagetMapping['usaget'];
					} else {
						return $row['src_field'];
					}
				}
			}
		}
		return $this->defaultUsaget;
	}

	protected function getLineUsageVolume($row) {
		if (!empty($this->usagevField)) {
			if (isset($row[$this->usagevField]) && is_numeric($row[$this->usagevField])) {
				return intval($row[$this->usagevField]);
			}
			Billrun_Factory::log('Usage volume is missing or invalid for file ' . basename($this->filePath), Zend_Log::ALERT);
		}
		return 1;
	}

}
