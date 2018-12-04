<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing parser class for fixed size
 *
 * @package  Billing
 * @since    0.5
 * @todo should make first derivative parser text and then fixed parser will inherited text parser
 */
abstract class Billrun_Parser_Csv extends Billrun_Parser {

	const HEADER_LINE = 1;
	const DATA_LINE = 2;
	const TRAILER_LINE = 3;

	/**
	 *
	 * @var array the structure of the parser line
	 */
	protected $headerStructure;
	protected $trailerStructure;
	protected $dataStructure;

	/**
	 *
	 * @var array the structure of the parser line
	 */
	protected $structure;
	protected $lineTypes;
	protected $hasHeader;
	protected $hasFooter;

	
	public function __construct($options) {
		parent::__construct($options);
		if (isset($options['data_structure']) || isset($options['structure'])) {
			$this->dataStructure = isset($options['data_structure']) ? $options['data_structure'] : $options['structure'];
		}
		if (isset($options['header_structure'])){
			$this->headerStructure = $options['header_structure'];
		}
		if (isset($options['trailer_structure'])){
			$this->trailerStructure= $options['trailer_structure'];
		}
		if (isset($options['line_types'])) {
			$this->setLineTypes($options['line_types']);
		}
		$this->hasHeader =  (isset($options['csv_has_header']) ? $options['csv_has_header'] : false);
		$this->hasFooter =  (isset($options['csv_has_footer']) ? $options['csv_has_footer'] : false);
	}

	/**
	 * method to set structure of the parsed file
	 * @param array $structure the structure of the parsed file
	 *
	 * @return Billrun_Parser_Fixed self instance
	 */
	public function setStructure($structure) {
		$this->structure = $structure;
		return $this;
	}


	public function setLineTypes($lineTypes) {
		$this->lineTypes = $lineTypes;
	}

	/**
	 * general method to parse
	 * @param resource $fp
	 */
	public function parse($fp) {
		$totalLines = 0;
		$skippedLines = 0;
		$this->dataRows = array();
		$this->headerRows = array();
		$this->trailerRows = array();
		
		if ($this->hasHeader) {
			$this->getLine($fp);
		}
		while ($line = $this->getLine($fp)) {
			$totalLines++;
			$record_type = $this->getLineType($line);
			switch ($record_type) {
				case static::DATA_LINE:
					$this->setStructure($this->dataStructure);
					if ($parsedLine = $this->parseLine($line)) {
						$this->dataRows[] = $parsedLine;
					}
					break;
				case static::HEADER_LINE:
					if ($this->headerStructure) {
						$this->setStructure($this->headerStructure);
						if ($parsedLine = $this->parseLine($line)) {
							$this->headerRows[] = $parsedLine;
						}
					}
					break;
				case static::TRAILER_LINE:
					if ($this->trailerStructure) {
						$this->setStructure($this->trailerStructure);
						if ($parsedLine = $this->parseLine($line)) {
							$this->trailerRows[] = $parsedLine;
						}
					}
					break;
				default:
					$skippedLines++;
					break;
			}
		}
		if ($this->hasFooter) {
			$this->removeLastLine($record_type);
		}
		if ($totalLines < $skippedLines * 2) {
			Billrun_Factory::log('Billrun_Parser_Csv: cannot identify record type of ' . $skippedLines . ' lines.', Zend_Log::ALERT);
		}
	}

	abstract protected function parseLine($line);

	protected function getLineType($line) {
		if (preg_match($this->lineTypes['D'], $line)) {
			return static::DATA_LINE;
		} else if (preg_match($this->lineTypes['H'], $line)) {
			return static::HEADER_LINE;
		} else if (preg_match($this->lineTypes['T'], $line)) {
			return static::TRAILER_LINE;
		}
			
		return FALSE;
	}

	public function getLine($fp) {
		return fgets($fp);
	}
	
	public function removeLastLine($record_type) {
		switch ($record_type) {
			case static::DATA_LINE:
				array_pop($this->dataRows);
				break;
			case static::HEADER_LINE:
				array_pop($this->headerRows);
				break;
			case static::TRAILER_LINE:
				array_pop($this->trailerRows);
				break;
			default:
				break;
		}
	}
	
	/**
	 * method to set data structure of the parsed file
	 * @param array $structure the structure of the parsed file
	 *
	 * @return Billrun_Parser_Fixed self instance
	 */
	public function setDataStructure($structure) {
		$this->dataStructure = $structure;
		return $this;
	}
	
		/**
	 * method to set header structure of the parsed file
	 * @param array $structure the structure of the parsed file
	 *
	 * @return Billrun_Parser_Fixed self instance
	 */
	public function setHeaderStructure($structure) {
		$this->headerStructure = $structure;
		return $this;
	}
	
	public function getStructure() {
		return $this->structure;
	}
}
