<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract processor ilds class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class Billrun_Processor_Base_SeparatorFieldLines extends Billrun_Processor {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'separator_field_lines';

	/**
	 * method to parse the data
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log("Resource is not configured well", Zend_Log::ERR);
			return false;
		}

		while ($line = $this->fgetsIncrementLine($this->fileHandler)) {
			$record_type = $this->getLineType($line);

			// @todo: convert each case code snippet to protected method (including triggers)
			switch ($record_type) {
				case 'H': // header
					if (isset($this->data['header'])) {
						Billrun_Factory::log("double header", Zend_Log::ERR);
						return false;
					}
					$this->data['header'] = $this->buildHeader($line);

					break;
				case 'T': //trailer
					if (isset($this->data['trailer'])) {
						Billrun_Factory::log("double trailer", Zend_Log::ERR);
						return false;
					}

					$this->data['trailer'] = $this->buildTrailer($line);

					break;
				case 'D': //data
					if (!isset($this->data['header'])) {
						Billrun_Factory::log("No header found", Zend_Log::ERR);
						return false;
					}

					$row = $this->buildData($line);
					if ($this->isValidDataRecord($row)) {
						$this->data['data'][] = $row;
					}

					break;
				default:
					//raise warning
					break;
			}
		}
		return true;
	}

	protected function buildHeader($line) {
		$this->parser->setStructure($this->header_structure);
		$this->parser->setLine($line);
		// @todo: trigger after header load (including $header)
		$header = $this->parser->parse();

		// @todo: trigger after header parse (including $header)
		$header['source'] = self::$type;
		$header['type'] = static::$type;
		$header['file'] = basename($this->filePath);
		$header['process_time'] = new MongoDate();
		return $header;
	}

	protected function buildTrailer($line) {
		$this->parser->setStructure($this->trailer_structure);
		$this->parser->setLine($line);
		// @todo: trigger after trailer load (including $header, $data, $trailer)
		$trailer = $this->parser->parse();

		// @todo: trigger after trailer parse (including $header, $data, $trailer)
		$trailer['source'] = self::$type;
		$trailer['type'] = static::$type;
		$trailer['header_stamp'] = $this->data['header']['stamp'];
		$trailer['file'] = basename($this->filePath);
		$trailer['process_time'] = new MongoDate();
		return $trailer;
	}

	protected function buildData($line, $line_number = null) {
		$this->parser->setStructure($this->data_structure); // for the next iteration
		$this->parser->setLine($line);
		// @todo: trigger after row load (including $header, $row)
		$row = $this->filterFields($this->parser->parse());
		// @todo: trigger after row parse (including $header, $row)
		$row['source'] = self::$type;
		$row['type'] = static::$type;
		$row['log_stamp'] = $this->getFileStamp();
		$row['file'] = basename($this->filePath);
		$row['process_time'] = new MongoDate();
		if ($this->line_numbers) {
			$row['line_number'] = $this->current_line;
		}
		return $row;
	}

	/**
	 * Check is a given data record is a valid record.
	 * @param $dataLine a structure containing the data record as it will be saved to the DB.
	 * @return true (by default) if the line is valid or false if theres some problem.
	 */
	abstract protected function isValidDataRecord($dataLine);

	/**
	 * (@TODO  duplicate of Billrun_Processor_Base_Binary::filterFields merge both of them to the processor  when  time  are less daire!)
	 * filter the record row data fields from the records
	 * (The required field can be written in the config using <type>.fields_filter)
	 * @param Array		$rawRow the full data record row.
	 * @return Array	the record row with filtered only the requierd fields in it  
	 * 					or if no filter is defined in the configuration the full data record.
	 */
	protected function filterFields($rawRow) {
		$stdFields = array('stamp');
		$row = array();

		$requiredFields = Billrun_Factory::config()->getConfigValue(static::$type . '.fields_filter', array(), 'array');
		if (!empty($requiredFields)) {
			$passThruFields = array_merge($requiredFields, $stdFields);
			foreach ($passThruFields as $field) {
				if (isset($rawRow[$field])) {
					$row[$field] = $rawRow[$field];
				}
			}
		} else {
			return $rawRow;
		}

		return $row;
	}

}
