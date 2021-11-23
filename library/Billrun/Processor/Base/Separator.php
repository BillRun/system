<?php

/**
 * @category   Billrun
 * @package    Processor
 * @subpackage Nrtrde
 * @copyright  Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Billing processor for NRTRDE
 * see also:
 * http://www.tapeditor.com/OnlineDemo/NRTRDE-ASCII-format.html
 *
 * @package    Billing
 * @subpackage Processor
 * @since      1.0
 */
abstract class Billrun_Processor_Base_Separator extends Billrun_Processor {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'separator';

	public function __construct($options) {
		parent::__construct($options);
	}

	/**
	 * Get the type of the currently parsed line.
	 * 
	 * @param $line  string containing the parsed line.
	 * 
	 * @return Character representing the line type
	 */
	protected function getLineType($line) {
		return $line[0];
	}

	/**
	 * method to parse the data
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log("Resource is not configured well", Zend_Log::ERR);
			return false;
		}

		$headerOptions = $this->getHeaderOptions();
		$dataOptions = $this->getDataOptions();
		$footerOptions = $this->getFooterOptions();

		while ($line = $this->getLine()) {
			$record_type = $this->getLineType($line);

			if (in_array($record_type, $headerOptions)) {
				$this->parseHeader($line);
			} else if (in_array($record_type, $dataOptions)) {
				$this->parseData($line);
			} else if (in_array($record_type, $footerOptions)) {
				$this->parseFooter($line);
			} else {
				Billrun_Factory::log("Billrun_Processor_Separator: cannot identify record type " . $record_type, Zend_Log::WARN);
			}
		}

		return true;
	}

	/**
	 * method to get available header record type strings
	 * 
	 * @return array all strings available as header
	 */
	protected function getHeaderOptions() {
		return array('H', 'h');
	}

	/**
	 * method to get available data record type strings
	 * 
	 * @return array all strings available as header
	 */
	protected function getDataOptions() {
		return array('D', 'd');
	}

	/**
	 * method to get available footer record type strings
	 * 
	 * @return array all strings available as header
	 */
	protected function getFooterOptions() {
		return array('F', 'f');
	}

	/**
	 * method to receive the next line to parse
	 * 
	 * @return array the lines parsed
	 */
	protected function getLine() {
		return fgetcsv($this->fileHandler, 8092, $this->parser->getSeparator());
	}

	/**
	 * method to parse header
	 * 
	 * @param array $line header line
	 * 
	 * @return array the header array
	 */
	protected function parseHeader($line) {
		if (isset($this->data['header'])) {
			Billrun_Factory::log("double header", Zend_Log::ERR);
			return false;
		}

		$this->parser->setStructure($this->header_structure);
		$this->parser->setLine($line);
		Billrun_Factory::dispatcher()->trigger('beforeHeaderParsing', array($line, $this));
		$header = $this->parser->parse();
		$header['source'] = static::$type;
		$header['type'] = self::$type;
		$header['file'] = basename($this->filePath);
		$header['process_time'] = new Mongodloid_Date();
		Billrun_Factory::dispatcher()->trigger('afterHeaderParsing', array($header, $this));
		$this->data['header'] = $header;
		return $header;
	}

	/**
	 * method to parse data
	 * 
	 * @param array $line data line
	 * 
	 * @return array the data array
	 */
	protected function parseData($line) {
		if (!isset($this->data['header'])) {
			Billrun_Factory::log("No header found", Zend_Log::ERR);
			return false;
		}

		$this->parser->setStructure($this->data_structure); // for the next iteration
		$this->parser->setLine($line);
		Billrun_Factory::dispatcher()->trigger('beforeDataParsing', array(&$line, $this));
		$row = $this->parser->parse();
		$row['source'] = static::$type;
		$row['type'] = self::$type;
		$row['log_stamp'] = $this->getFileStamp();
		$row['file'] = basename($this->filePath);
		$row['process_time'] = new Mongodloid_Date();
		Billrun_Factory::dispatcher()->trigger('afterDataParsing', array(&$row, $this));
		$this->data['data'][] = $row;
		return $row;
	}

	/**
	 * method to parse footer
	 * 
	 * @param array $line footer line
	 * 
	 * @return array the footer array
	 */
	protected function parseFooter($line) {

		if (isset($this->data['trailer'])) {
			Billrun_Factory::log("double trailer", Zend_Log::ERR);
			return false;
		}

		$this->parser->setStructure($this->trailer_structure);
		$this->parser->setLine($line);
		Billrun_Factory::dispatcher()->trigger('beforeFooterParsing', array($line, $this));
		$trailer = $this->parser->parse();
		$trailer['source'] = static::$type;
		$trailer['type'] = self::$type;
		$trailer['header_stamp'] = $this->data['header']['stamp'];
		$trailer['file'] = basename($this->filePath);
		$trailer['process_time'] = new Mongodloid_Date();
		Billrun_Factory::dispatcher()->trigger('afterFooterParsing', array($trailer, $this));
		$this->data['trailer'] = $trailer;
		return $trailer;
	}

}
