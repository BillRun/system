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
	protected $data = array();

	/**
	 * the file path to process on
	 * @var file path
	 */
	protected $backupPaths  = array();
	
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
		
		if (isset($options['backup_path'])) {
			$this->setBackupPath($options['backup_path']);
		} else {
			
				$this->setBackupPath( Billrun_Factory::config()->getConfigValue($this->getType().'.backup_path',array('./backups/'.$this->getType())));
		}

	}
	
	/**
	 * method to receive the items that the processor parsed on each iteration of parser
	 * 
	 * @return array items 
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * method to setup the backup path that the processor will stored the parsing file
	 * 
	 * @param string $path the backup path
	 */
	public function setBackupPath($paths) {
		$paths = is_array($paths) ? $paths : explode(',',$paths);
		$this->backupPaths = array();
		// in case the path is not exists but we can't create it

		foreach($paths as $path) {
			if (!file_exists($path) && !@mkdir($path, 0777, true)) {
				Billrun_Factory::log()->log("Can't create backup path or is not a directory " . $path, Zend_Log::WARN);
				return FALSE;
			}
			// in case the path exists but it's a file
			if (!is_dir($path)) {
				Billrun_Factory::log()->log("The path " . $path . " is not directory", Zend_Log::WARN);
				return FALSE;
			}
			$this->backupPaths[] = $path;
		}	
	
		return TRUE;
	}


	/**
	 * method to run over all the files received which did not have been processed
	 */
	public function process_files() {

		$log = $this->db->getCollection(self::log_table);
		$files = $log->query()
			->equals('source', static::$type)
			->notExists('process_time');

		$lines = array();
		foreach ($files as $file) {
			$this->setStamp($file->getID());
			$this->loadFile($file->get('path'), $file->get('retreived_from'));
			$processed_lines = $this->process();
			if($processed_lines) {
				$lines = array_merge($lines, $processed_lines);
				$file->collection($log);
				$file->set('process_time', date(self::base_dateformat));
			}
			$this->init();
		}

		return $lines;
	}

	/**
	 * method to initialize the data and the file handler of the processor
	 * useful when processing files in iterations one after another
	 */
	protected function init() {
		$this->data = array();
		if (is_resource($this->fileHandler)) {
			fclose($this->fileHandler);
		}
	}

	/**
	 * method to process file by the processor parser
	 * 
	 * @return mixed
	 */
	public function process() {

		$this->dispatcher->trigger('beforeProcessorParsing', array($this));

		if ($this->parse() === FALSE) {
			Billrun_Factory::log()->log("Billrun_Processor: cannot parse", Zend_Log::ERR);
			return false;
		}

		$this->dispatcher->trigger('afterProcessorParsing', array($this));

		if ($this->logDB() === FALSE) {
			Billrun_Factory::log()->log("Billrun_Processor: cannot log parsing action", Zend_Log::WARN);
		}

		$this->dispatcher->trigger('beforeProcessorStore', array($this));

		if ($this->store() === FALSE) {
			Billrun_Factory::log()->log("Billrun_Processor: cannot store the parser lines", Zend_Log::ERR);
			return false;
		}

		$this->dispatcher->trigger('afterProcessorStore', array($this));
		
		for($i=0; $i < count($this->backupPaths) ; $i++) {
			$backupPath = $this->backupPaths[$i] . DIRECTORY_SEPARATOR . $this->retreivedHostname;
			if ($this->backup( $backupPath , $i+1 < count($this->backupPaths)) === TRUE) {
				Billrun_Factory::log()->log("Success backup file " . $this->filePath . " to " . $backupPath, Zend_Log::INFO);
			} else {
				Billrun_Factory::log()->log("Failed backup file " . $this->filePath . " to " . $backupPath, Zend_Log::INFO);
			}
		}

		$this->dispatcher->trigger('afterProcessorBackup', array($this));
		
		return $this->data['data'];
	}

	abstract protected function parse();

	/**
	 * method to log the processing
	 * 
	 * @todo refactoring this method
	 */
	protected function logDB() {
		if (!isset($this->db)) {
			Billrun_Factory::log()->log("Billrun_Processor:logDB not database instance", Zend_Log::ERR);
			return false;
		}

		if (!isset($this->data['trailer']) && !isset($this->data['header'])) {
			Billrun_Factory::log()->log("Billrun_Processor:logDB no header nor trailer to log", Zend_Log::ERR);
			return false;
		}

		$log = $this->db->getCollection(self::log_table);

		$header = array();
		if (isset($this->data['header'])) {
			$header = $this->data['header'];
		}

		$trailer = array();
		if (isset($this->data['trailer'])) {
			$trailer = $this->data['trailer'];
		}

		if (empty($header) && empty($trailer)) {
			Billrun_Factory::log()->log("Billrun_Processor::logDB - trailer and header are empty", Zend_Log::ERR);
			return FALSE;
		}

		$current_stamp = $this->getStamp(); // mongo id in new version; else string
		if ($current_stamp instanceof Mongodloid_Entity || $current_stamp instanceof Mongodloid_ID) {
			$resource = $log->findOne($current_stamp);
			if (!empty($header)) {
				$resource->set('header', $header);
			}
			if (!empty($trailer)) {
				$resource->set('trailer', $trailer);
			}
			return $resource->save($log, true);
		} else {
			// backword compatability
			// old method of processing => receiver did not logged, so it's the first time the file logged into DB
			$entity = new Mongodloid_Entity($trailer);
			if ($log->query('stamp', $entity->get('stamp'))->count() > 0) {
				Billrun_Factory::log()->log("Billrun_Processor::logDB - DUPLICATE! trying to insert duplicate log file with stamp of : {$entity->get('stamp')}", Zend_Log::NOTICE);
				return FALSE;
			}
			return $entity->save($log, true);
		}
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
				Billrun_Factory::log()->log("processor::store - DUPLICATE! trying to insert duplicate line with stamp of : {$entity->get('stamp')}", Zend_Log::NOTICE);
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
	public function loadFile($file_path, $retrivedHost) {
		$this->dispatcher->trigger('processorBeforeFileLoad', array(&$file_path, $this));
		if (file_exists($file_path)) {
			$this->filePath = $file_path;
			$this->filename = substr($file_path, strrpos($file_path, '/'));
			$this->retreivedHostname = $retrivedHost;
			$this->fileHandler = fopen($file_path, 'r');
			Billrun_Factory::log()->log("Billrun Processor load the file: " . $file_path, Zend_Log::INFO);
		} else {
			Billrun_Factory::log()->log("Billrun_Processor->loadFile: cannot load the file: " . $file_path, Zend_Log::ERR);
		}
		$this->dispatcher->trigger('processorAfterFileLoad', array(&$file_path));
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
	 * method to backup the processed file
	 * @param string $path  the path to backup the file to.
	 * @param boolean $copy copy or rename (move) the file to backup
	 * 
	 * @return boolean return true if success to backup
	 */
	protected function backup($path, $copy = false) {
		if ($copy) {
			$callback = "copy";
		} else {
			$callback = "rename";
		}
		if(!file_exists($path)) {
			@mkdir($path, 0777, true);
			
		}
		return @call_user_func_array($callback, array(	$this->filePath, 
														$path. DIRECTORY_SEPARATOR . $this->filename 
													));

	}

}