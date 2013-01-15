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
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'processor';

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

		$this->dispatcher->trigger('beforeProcessorParsing', array($this));

		if ($this->parse() === FALSE) {
			$this->log->log("Billrun_Processor: cannot parse", Zend_Log::ERR);
			return false;
		}

		$this->dispatcher->trigger('afterProcessorParsing', array($this));

		if ($this->logDB() === FALSE) {
			$this->log->log("Billrun_Processor: cannot log parsing action", Zend_Log::WARN);
		}

		$this->dispatcher->trigger('beforeProcessorStore', array($this));

		if ($this->store() === FALSE) {
			$this->log->log("Billrun_Processor: cannot store the parser lines", Zend_Log::ERR);
			return false;
		}

		$this->dispatcher->trigger('afterProcessorStore', array($this));

		return $this->data['data'];
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

		if (isset($this->data['trailer'])) {
			$entity = new Mongodloid_Entity($this->data['trailer']);
		} else if (isset($this->data['header'])) {
			$entity = new Mongodloid_Entity($this->data['header']);
		} else {
			$this->log->log("Billrun_Processor::logDB - cannot locate trailer ot header to log", Zend_Log::ERR);
			return FALSE;
		}

		if ($log->query('stamp', $entity->get('stamp'))->count() > 0) {
			$this->log->log("Billrun_Processor::logDB - DUPLICATE! trying to insert duplicate log line with stamp of : {$entity->get('stamp')}", Zend_Log::NOTICE);
			return FALSE;
		}
		return $entity->save($log, true);
	}

	/**
	 * method to store the processing data
	 * 
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
				$this->log->log("processor::store - DUPLICATE! trying to insert duplicate line with stamp of : {$entity->get('stamp')}", Zend_Log::NOTICE);
				$this->log->log("processor::store - {$entity->get('caller_phone_no')} , {$entity->get('call_start_dt')}", Zend_Log::NOTICE);
				continue;
			}
			$entity->save($lines, true);
		}

		return true;
	}

	/**
	 * Get the type of the currently parsed line.
	 * 
	 * @param $line  string containing the parsed line.
	 * 
	 * @return Character representing the line type
	 * 	'H' => Header
	 * 	'D' => Data
	 * 	'T' => Trailer
	 */
	protected function getLineType($line, $length = 1) {
		return substr($line, 0, $length);
	}

	/**
	 * load file to be handle by the processor
	 * 
	 * @param string $file_path
	 * 
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
	 * 
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
			$args['type'] = "Type_" . $args['type'];
		} else {
			$args[0]['type'] = "Type_" . $args[0]['type'];
		}
		return forward_static_call_array(array('parent', 'getInstance'), $args);
	}

}