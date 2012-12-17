<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract processor class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class processor extends base {

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

		if (isset($options['db'])) {
			$this->setDB($options['db']);
		}
	}

	public function setDB($db) {
		$this->db = $db;
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
			//raise error
			return false;
		}

		if (!$this->store()) {
			//raise error
			return false;
		}

		// @todo: trigger after storage line (including $ret)

		return true;
	}
	
	abstract protected function parse();

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

		return $entity->save($log);
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
			$entity->save($lines);
		}

		return true;
	}
	/**
	 * Get the type of the currently parsed line.
	 * @param $line  string containing the parsed line.
	 * @return Character representing the line type
	 *	'H' => Header
	 *	'D' => Data
	 *	'T' => Tail
	 */
	protected function getLineType($line)
	{
		return substr($line, 0, 1);
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