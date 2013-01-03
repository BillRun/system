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

		parent::__construct($options);

		if (isset($options['path'])) {
			$this->loadFile($options['path']);
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

	/**
	 * method to set the parser of the processor
	 * @param Billrun_Parser $parser the parser to use by the processor
	 *
	 * @return mixed the processor itself (for concatening methods)
	 */
	public function setParser($parser) {
		$this->parser = $parser;
		return $this;
	}

	/**
	 * Loose coupling of objects in the system
	 *
	 * @return mixed the bridge class
	 */
	static public function getInstance() {
		$args = func_get_args();
		if (!is_array($args)) {
			$args['type'] = "Type_". $args['type'];
		} else {
			$args[0]['type'] =  "Type_" . $args[0]['type'];
		}
		return forward_static_call_array(array('parent','getInstance'),$args);
	}

}