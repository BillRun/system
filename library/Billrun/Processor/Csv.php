<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Processor_Csv extends Billrun_Processor {

	static protected $type = 'csv';
	protected $separator = null;
	protected $fields_to_process = null;
	protected $usage_type = null;
	protected $include_header = null;
	protected $skip_first_row = null;
	protected $config = null;
	protected $receiver_path = null;
	protected $receiver_type = null;
	protected $fields = null;
	protected $date_offset = null;
	protected $date_field = null;
	protected $date_format = null;
	protected $header_structure = null;
	protected $data_structure = null;
	protected $trailer_structure = null;

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->usage_type = $this->getType();
		$this->config = $this->getConfig();
		$this->receiver_path = $this->getReceiverPath();
		$this->separator = $this->getSeparator();
		$this->receiver_type = $this->getReceiverType();
		$this->fields = $this->getProcessorFields();
		$this->date_field = $this->getDate();
		$this->date_format = $this->getDateFormat();
		$this->date_offset = $this->getDateOffset();

		if (!is_null($this->config["processor.$this->usage_type.include_header"])) {
			$this->include_header = $this->config["processor.$this->usage_type.include_header"];
		}
		if (!is_null($this->config["processor.$this->usage_type.skip_first_row"])) {
			$this->skip_first_row = $this->config["processor.$this->usage_type.skip_first_row"];
		}
	}

	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log()->log("Resource is not configured well", Zend_Log::ERR);
			return false;
		}
		$fields = $this->fields;
		if (($this->include_header) && ($this->skip_first_row)) {
			$this->getLine(); // header non-relevant
		}
		if (($this->include_header) && (!$this->skip_first_row)) {
			$fields = $this->getLine();
			$this->fields = $fields;
		}
		$this->header_structure = $this->config["processor.$this->usage_type.header"];
		$this->data_structure = $this->fields;
		$this->trailer_structure = $this->config["processor.$this->usage_type.trailer"];

		$this->setInitialData();
		while ($line = $this->getLine()) {
			$line_to_string = implode($line);
			$record_type = $this->getLineType($line_to_string);

			switch ($record_type) {
				case 'H': // header
					if (isset($this->data['header'])) {
						Billrun_Factory::log()->log("double header", Zend_Log::ERR);
						return false;
					}
					$this->data['header'] = $this->buildHeader($line);

					break;
				case 'T': //trailer
					if (isset($this->data['trailer'])) {
						Billrun_Factory::log()->log("double trailer", Zend_Log::ERR);
						return false;
					}

					$this->data['trailer'] = $this->buildTrailer($line);

					break;
				case 'D': //data

					$parsed_row = $this->parseData($line);
					foreach ($fields as $index => $field) {
						$row[$field] = $line[$index];
					}
					if (isset($row[$this->date_field])) {
						$offset = (!is_null($this->date_offset) && isset($row[$this->date_offset]) ?
								($row[$this->date_offset] > 0 ? "+" : "" ) . $row[$this->date_offset] : "00" ) . ':00';
						$datetime = DateTime::createFromFormat($this->date_format, $row[$this->date_field] . $offset);
					}
					$row['urt'] = new Mongodloid_Date($datetime->format('U'));
					$row = array_merge($row, $parsed_row);
					$this->data['data'][] = $row;

					break;
				default:
					//raise warning
					break;
			}
		}
		return true;
	}

	protected function parseData($line, $line_number = null) {
		$this->parser->setStructure($this->data_structure);
		$this->parser->setLine($line);
		Billrun_Factory::dispatcher()->trigger('beforeDataParsing', array(&$line, $this));
		$row = $this->parser->parse();
		$row['source'] = static::$type;
		$row['type'] = self::$type;
		$row['log_stamp'] = $this->getFileStamp();
		$row['file'] = basename($this->filePath);
		$row['process_time'] = new Mongodloid_Date();
		Billrun_Factory::dispatcher()->trigger('afterDataParsing', array(&$row, $this));

		return $row;
	}

	protected function getSeparator() {
		return $this->config["processor.$this->usage_type.separator"];
	}

	/**
	 * method to receive the next line to parse
	 * 
	 * @return array the lines parsed
	 */
	protected function getLine() {
		return fgetcsv($this->fileHandler, 8092, $this->getSeparator());
	}

	protected function getProcessorFields() {
		if (empty($this->fields_to_process)) {
			$this->fields_to_process = $this->config["processor.$this->usage_type.fields"];
		}
		return $this->fields_to_process;
	}

	protected function getReceiverPath() {
		return $this->config["processor.$this->usage_type.receiver.path"];
	}

	protected function getReceiverType() {
		return $this->config["processor.$this->usage_type.receiver.type"];
	}

	protected function getDate() {
		return $this->config["processor.$this->usage_type.date_field"];
	}

	protected function getDateFormat() {
		return $this->config["processor.$this->usage_type.date_format"];
	}

	protected function getDateOffset() {
		return $this->config["processor.$this->usage_type.date_offset"];
	}

	/**
	 * preform intial operations on the data.
	 */
	protected function setInitialData() {
		if (isset($this->config) && $this->config["processor.$this->usage_type.add_filename_data_to_header"]) {
			$this->data['header'] = array_merge($this->buildHeader(''), array_merge((isset($this->data['header']) ? $this->data['header'] : array()), $this->getFilenameData(basename($this->filePath))));
		}
	}

	/**
	 * Find the line type  by checking  if the line match  a configuraed regex.
	 * @param type $line the line to check.
	 * @param type $length the lengthh of the line,
	 * @return string H/T/D  depending on the type of the line.
	 */
	protected function getLineType($line, $length = 1) {
		$a = $this->config["processor.$this->usage_type.line_types"];
		foreach ($a as $key => $val) {
			if (preg_match($val, $line)) {
				//	Billrun_Factory::log()->log("line type key : $key",Zend_Log::DEBUG);
				return $key;
			}
		}
		return parent::getLineType($line, $length);
	}

	protected function getLineUsageType($row) {
		
	}

	protected function getLineVolume($row) {
		
	}

	protected function processLines() {
		
	}

}
