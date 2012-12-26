<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract processor class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class Billrun_Processor extends Billrun_Base {

	/**
	 * the file path to process on
	 * @var file path
	 */
	protected $filePath;

	/**
	 * the file handler to process on
	 * @var file handler
	 */
	protected $fileHandler;

	/**
	 * parser to processor the file
	 * @var processor class
	 */
	protected $parser = null;

	/**
	 * the container work on
	 * @var array
	 */
	protected $data = null;

	/**
	 * constructor - load basic options
	 *
	 * @param array $options for the file processor
	 */
	public function __construct($options) {
		if (isset($options['file_path'])) {
			$this->loadFile($options['file_path']);
		}

		if (isset($options['parser'])) {
			$this->setParser($options['parser']);
		}

	}

	public function getData() {
		return $this->data;
	}

	/**
	 * method to get the data from the file
	 * @todo take to parent abstract
	 */
	public function process() {

		// @todo: trigger before parse (including $ret)
		if (!$this->parse()) {
			return false;
		}

		// @todo: trigger after parse line (including $ret)
		// @todo: trigger before storage line (including $ret)

		if (!$this->logDB()) {
			//TODO raise error
		}

		if (!$this->store()) {
			//raise error
			return false;
		}

		// @todo: trigger after storage line (including $ret)

		return true;
	}

	/**
	 * method to log the processing
	 * @todo refactoring this method
	 */
	protected function logDB() {
		if (!isset($this->db) || !isset($this->data['trailer'])) {
			// raise error
			return false;
		}

		$log = $this->db->getCollection(self::log_table);
		$entity = new Mongodloid_Entity($this->data['trailer']);
		if ($log->query('stamp', $entity->get('stamp'))->count() > 0) {
			print("processor::logDB - DUPLICATE! trying to insert duplicate line with stamp of : {$entity->get('stamp')} \n");
			return FALSE;
		}
		return $entity->save($log, true);
	}

	/**
	 * method to store the processing data
	 * @todo refactoring this method
	 */
	protected function store() {
		if (!isset($this->db) || !isset($this->data['data'])) {
			// raise error
			return false;
		}

		$lines = $this->db->getCollection(self::lines_table);

		foreach ($this->data['data'] as $row) {
			$entity = new Mongodloid_Entity($row);
			if ($lines->query('stamp', $entity->get('stamp'))->count() > 0) {
				print("processor::store - DUPLICATE! trying to insert duplicate line with stamp of : {$entity->get('stamp')} \n");
				///print("processor::store - {$entity->get('caller_phone_no')} , {$entity->get('call_start_dt')}   \n");
				continue;
			}
			$entity->save($lines, true);
		}

		return true;
	}

	/**
	 * Get the type of the currently parsed line.
	 * @param $line  string containing the parsed line.
	 * @return Character representing the line type
	 * 	'H' => Header
	 * 	'D' => Data
	 * 	'T' => Tail
	 */
	protected function getLineType($line) {
		return substr($line, 0, 1);
	}

	/**
	 * method to parse the data
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			echo "Resource is not configured well" . PHP_EOL;
			return false;
		}

		while ($line = fgets($this->fileHandler)) {
			$record_type = $this->getLineType($line);

			// @todo: convert each case code snippet to protected method (including triggers)
			switch ($record_type) {
				case 'H': // header
					if (isset($this->data['header'])) {
						echo "double header" . PHP_EOL;
						return false;
					}

					$this->parser->setStructure($this->header_structure);
					$this->parser->setLine($line);
					// @todo: trigger after header load (including $header)
					$header = $this->parser->parse();
					// @todo: trigger after header parse (including $header)
					$header['type'] = $this->type;
					$header['file'] = basename($this->filePath);
					$header['process_time'] = date('Y-m-d h:i:s');
					$this->data['header'] = $header;

					break;
				case 'T': //trailer
					if (isset($this->data['trailer'])) {
						echo "double trailer" . PHP_EOL;
						return false;
					}

					$this->parser->setStructure($this->trailer_structure);
					$this->parser->setLine($line);
					// @todo: trigger after trailer load (including $header, $data, $trailer)
					$trailer = $this->parser->parse();
					// @todo: trigger after trailer parse (including $header, $data, $trailer)
					$trailer['type'] = $this->type;
					$trailer['header_stamp'] = $this->data['header']['stamp'];
					$trailer['file'] = basename($this->filePath);
					$trailer['process_time'] = date('Y-m-d h:i:s');
					$this->data['trailer'] = $trailer;

					break;
				case 'D': //data
					if (!isset($this->data['header'])) {
						echo "No header found" . PHP_EOL;
						return false;
					}

					$this->parser->setStructure($this->data_structure); // for the next iteration
					$this->parser->setLine($line);
					// @todo: trigger after row load (including $header, $row)
					$row = $this->parser->parse();
					// @todo: trigger after row parse (including $header, $row)
					$row['type'] = $this->type;
					$row['header_stamp'] = $this->data['header']['stamp'];
					$row['file'] = basename($this->filePath);
					$row['process_time'] = date('Y-m-d h:i:s');
					// hot fix cause this field contain iso-8859-8
					if (isset($row['country_desc'])) {
						$row['country_desc'] = mb_convert_encoding($row['country_desc'], 'UTF-8', 'ISO-8859-8');
					}
					$this->data['data'][] = $row;

					break;
				default:
					//raise warning
					break;
			}
		}
		return true;
	}

	/**
	 * load file to be handle by the processor
	 * @param string $file_path
	 * @return void
	 */
	public function loadFile($file_path) {
		if (file_exists($file_path)) {
			$this->filePath = $file_path;
			$this->fileHandler = fopen($file_path, 'r');
		} else {
			// log file not exists
		}
	}

	public function setParser($parser) {
		$this->parser = $parser;
	}

}