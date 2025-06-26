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
	protected $legalLineTypeFields = ['parser', 'record_type', 'line_type', 'regex'];

	/**
	 *
	 * @var array the structure of the parser line
	 */
	protected $structure;
	protected $lineTypes;
	protected $hasHeader;
	protected $hasFooter;
	protected $recordType;

	
	public function __construct($options) {
		parent::__construct($options);
		$this->setConstructParserCsvFields($options);
		if (isset($options['line_types'])) {
			$this->setLineTypes($options['line_types']);
		}
		$this->hasHeader =  (isset($options['csv_has_header']) ? $options['csv_has_header'] : false);
		$this->hasFooter =  (isset($options['csv_has_footer']) ? $options['csv_has_footer'] : false);
	}

	protected function setConstructParserCsvFields($options){
		$this->dataStructure = isset($options['data_structure']) ? $options['data_structure'] : ($options['structure'] ?? null);
		$this->headerStructure = $options['header_structure'] ??  null;
		$this->trailerStructure= $options['trailer_structure'] ?? null;
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
		if(Billrun_Config::haveMultipleLineTypes($lineTypes)){
    		foreach ($lineTypes as $lineType) {
				$this->lineTypes[] = array_intersect_key($lineType, array_flip($this->legalLineTypeFields));
			}
		} else {
			$this->lineTypes = $lineTypes;
		}
	}

	/**
	 * general method to parse
	 * @param resource $fp
	 */
	public function parse($fp) {
		$totalLines = 0;
		$indexDataRows = 0;
		$skippedLines = 0;
		$this->dataRows = array();
		$this->headerRows = array();
		$this->trailerRows = array();
		
		if ($this->hasHeader) {
			$this->getLine($fp);
		}
		while ($line = $this->getLine($fp)) {
			$totalLines++;
			$this->setParserDataByLine($line);
			$record_type = $this->getRecordType($line);
			switch ($record_type) {
				case static::DATA_LINE:
					$this->setStructure($this->dataStructure);
					if ($parsedLine = $this->parseLine($line)) {
						$this->dataRows[] = $parsedLine;
						$this->saveParserExtraData($indexDataRows);
						$indexDataRows++;
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

	protected function getRecordType($line) {
		if (isset($this->recordType)){
			return $this->recordType;
		}
		if (isset($this->lineTypes['D']) && preg_match($this->lineTypes['D'], $line)) {
			return static::DATA_LINE;
		} else if (isset($this->lineTypes['H']) && preg_match($this->lineTypes['H'], $line)) {
			return static::HEADER_LINE;
		} else if (isset($this->lineTypes['T']) && preg_match($this->lineTypes['T'], $line)) {
			return static::TRAILER_LINE;
		}
			
		return FALSE;
	}

	public function getLine($fp) {
        $line = fgets($fp);
        
        if (isset($this->encodingSource)) {
            $encodingTarget = $this->encodingTarget ?? self::DEFAULT_TARGET_ENCODING;
            
            $supportedEncodings = mb_list_encodings();
            $targetEncodingSupported = in_array($encodingTarget, $supportedEncodings);
            $sourceEncodingSupported = in_array($this->encodingSource, $supportedEncodings);

            if (!$targetEncodingSupported) {
                Billrun_Factory::log(
                    sprintf(
                        "Parser %s encoding convert cancelled. Unsupported target encoding: %s",
                        __CLASS__,
                        $encodingTarget
                    ),
                    Zend_Log::ALERT
                );
                return $line;
            }
            
            if (!$sourceEncodingSupported) {
                Billrun_Factory::log(
                    sprintf(
                        "Parser %s encoding convert cancelled. Unsupported source encoding: %s",
                        __CLASS__,
                        $this->encodingSource
                    ),
                    Zend_Log::ALERT
                );
                return $line;
            }
            
            $lineConverted = mb_convert_encoding($line, $this->encodingTarget, $this->encodingSource);
            
            if ($lineConverted === false) {
                Billrun_Factory::log(
                    sprintf(
                        "Parser %s encoding convert failed. Source encoding: %s, Target encoding: %s, Line: %s",
                        __CLASS__,
                        $this->encodingSource,
                        $this->encodingTarget,
                        $line
                    ),
                    Zend_Log::ALERT
                );
            }
            
            $line = $lineConverted;
        }

        return $line;
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

	protected function setParserDataByLine($line){
		if(Billrun_Config::haveMultipleLineTypes($this->lineTypes)){
			foreach ($this->lineTypes as $lineType){
				$regex = $lineType['regex'];
				if (preg_match($regex, $line)) {
					if ($lineType['record_type'] == 'H') {
						$this->recordType = static::HEADER_LINE;
					} else if ($lineType['record_type'] == 'T') {
						$this->recordType = static::TRAILER_LINE;
					} else {
						$this->recordType = static::DATA_LINE;//DEFAULT
					}
					$this->lineType =  $lineType['line_type'];
					$this->setConstructParserCsvFields($lineType['parser']);
					return;
				}
			}
			throw new Exception('Input Processor have multiple line types and Line '. $lineNumber . ' does not match any of the regex patterns.');
		}
	}

	protected function saveParserExtraData($indexDataRows){
		if(isset($this->lineType)){
			$this->linesTypesMapping[$indexDataRows] = $this->lineType; 
		}
	}

	public function getExtraData(){
		if(!empty($this->linesTypesMapping)){
			return ['linesTypesMapping' => $this->linesTypesMapping];
		}
		return [];
	}

}
