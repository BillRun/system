<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract processor class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Processor extends Billrun_Base {

	const BACKUP_FILE_SEQUENCE_GRANULARITY = 2;

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
	protected $data = array('data' => array());

	/**
	 * the container work on
	 * @var array
	 */
	protected $queue_data = array();
	
	/**
	 * flag indicate to make bulk insert into database
	 * 
	 * @var boolean
	 */
	protected $bulkInsert = 0;

	/**
	 * the file path to process on
	 * @var file path
	 */
	protected $backupPaths = array();

	/**
	 * The time to wait  until adopting file  that were  started processing but weren't finished.
	 */
	protected $orphandFilesAdoptionTime = '1 day';

	/**
	 * whether to log records' line number in the source file
	 * @var boolean 
	 */
	protected $line_numbers = false;

	/**
	 * current processed line number
	 * @var boolean 
	 */
	protected $current_line = 0;

	/**
	 * the backup sequence file number digits granularity 
	 * (1=batches of 10 files  in each directory, 2= batches of 100, 3= batches of 1000,etc...)
	 * @param integer
	 */
	protected $backup_seq_granularity = self::BACKUP_FILE_SEQUENCE_GRANULARITY;

	/**
	 *
	 * @var boolean whether to preserve the modification timestamps of the files being backed up
	 */
	protected $preserve_timestamps = true;

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

		if (isset($options['parser']) && $options['parser'] != 'none') {
			$this->setParser($options['parser']);
		}
		if (isset($options['processor']['line_numbers'])) {

			$this->line_numbers = $options['processor']['line_numbers'];
		}

		if (isset($options['backup_path'])) {
			$this->setBackupPath($options['backup_path']);
		} else {
			$this->setBackupPath(Billrun_Factory::config()->getConfigValue($this->getType() . '.backup_path', array('./backups/' . $this->getType())));
		}

		if (isset($options['orphan_files_time'])) {
			$this->orphandFilesAdoptionTime = $options['orphan_files_time'];
		} else if (isset($options['processor']['orphan_files_time'])) {
			$this->orphandFilesAdoptionTime = $options['processor']['orphan_files_time'];
		}

		if (isset($options['processor']['limit']) && $options['processor']['limit']) {
			$this->setLimit($options['processor']['limit']);
		}
		if (isset($options['processor']['backup_granularity']) && $options['processor']['backup_granularity']) {
			$this->backup_seq_granularity = $options['processor']['backup_granularity'];
		}
		if (isset($options['bulkInsert'])) {
			$this->bulkInsert = $options['bulkInsert'];
		}
		if (isset($options['processor']['preserve_timestamps'])) {
			$this->preserve_timestamps = $options['processor']['preserve_timestamps'];
		}
	}

	/**
	 * method to receive the items that the processor parsed on each iteration of parser
	 * 
	 * @return array items 
	 */
	public function &getData() {
		return $this->data;
	}

	public function addDataRow($row) {
		if (!isset($this->data['data'])) {
			$this->data['data'] = array();
		}
		$this->data['data'][] = $row;
		return true;
	}

	public function getParser() {
		return $this->parser;
	}

	/**
	 * method to setup the backup path that the processor will stored the parsing file
	 * 
	 * @param string $path the backup path
	 */
	public function setBackupPath($paths) {
		$paths = is_array($paths) ? $paths : explode(',', $paths);
		$this->backupPaths = array();
// in case the path is not exists but we can't create it

		foreach ($paths as $path) {
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

		$log = Billrun_Factory::db()->logCollection();

		$linesCount = 0;

		for ($i = $this->getLimit(); $i > 0; $i--) {
			if ($this->isQueueFull()) {
				Billrun_Factory::log()->log("Billrun_Processor: queue size is too big", Zend_Log::INFO);
				return $linesCount;
			} else {
				$file = $this->getFileForProcessing();
				if ($file->isEmpty()) {
					break;
				}
				$this->setStamp($file->getID());
				$this->loadFile($file->get('path'), $file->get('retrieved_from'));
				$processedLinesCount = $this->process();
				if (FALSE !== $processedLinesCount) {
					$linesCount += $processedLinesCount;
					$file->collection($log);
					$file->set('process_time', date(self::base_dateformat));
				}
				$this->init();
			}
		}

		return $linesCount;
	}

	/**
	 * method to initialize the data and the file handler of the processor
	 * useful when processing files in iterations one after another
	 */
	protected function init() {
		$this->data = array('data' => array());
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
		if ($this->isQueueFull()) {
			Billrun_Factory::log()->log("Billrun_Processor: queue size is too big", Zend_Log::INFO);
			return FALSE;
		} else {
			Billrun_Factory::dispatcher()->trigger('beforeProcessorParsing', array($this));

			if ($this->parse() === FALSE) {
				Billrun_Factory::log()->log("Billrun_Processor: cannot parse " . $this->filePath, Zend_Log::ERR);
				return FALSE;
			}

			Billrun_Factory::dispatcher()->trigger('afterProcessorParsing', array($this));
			$this->prepareQueue();
			Billrun_Factory::dispatcher()->trigger('beforeProcessorStore', array($this));

			if ($this->store() === FALSE) {
				Billrun_Factory::log()->log("Billrun_Processor: cannot store the parser lines " . $this->filePath, Zend_Log::ERR);
				return FALSE;
			}

			if ($this->logDB() === FALSE) {
				Billrun_Factory::log()->log("Billrun_Processor: cannot log parsing action" . $this->filePath, Zend_Log::WARN);
				return FALSE;
			}
			Billrun_Factory::dispatcher()->trigger('afterProcessorStore', array($this));
			$this->backup();

			Billrun_Factory::dispatcher()->trigger('afterProcessorBackup', array($this, &$this->filePath));
			return count($this->data['data']);
		}
	}

	/**
	 * Parse the current CDR line. 
	 * @return array conatining the parsed data.  
	 */
	abstract protected function parse();

	/**
	 * method to log the processing
	 * 
	 * @todo refactoring this method
	 */
	protected function logDB() {


		if (!isset($this->data['trailer']) && !isset($this->data['header'])) {
			Billrun_Factory::log()->log("Billrun_Processor:logDB " . $this->filePath . " no header nor trailer to log", Zend_Log::ERR);
			return false;
		}

		$log = Billrun_Factory::db()->logCollection();

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
		if ($current_stamp instanceof Mongodloid_Entity || $current_stamp instanceof Mongodloid_Id) {
			$resource = $log->findOne($current_stamp);
			if (!empty($header)) {
				$resource->set('header', $header);
			}
			if (!empty($trailer)) {
				$resource->set('trailer', $trailer);
			}
			return $resource->save($log, true);
		} else {
// backward compatibility
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
		if (!isset($this->data['data'])) {
// raise error
			return false;
		}

		$lines = Billrun_Factory::db()->linesCollection();
		Billrun_Factory::log()->log("Store data of file " . basename($this->filePath) . " with " . count($this->data['data']) . " lines", Zend_Log::DEBUG);
		$queue_data = $this->getQueueData();
		if ($this->bulkInsert) {
			settype($this->bulkInsert, 'int');
			if (!$this->bulkAddToCollection($lines)) {
				return false;
			}
			if (!$this->bulkAddToQueue()) {
				return false;
			}
		} else {
			$this->addToCollection($lines);
			$this->addToQueue($queue_data);
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
	public function loadFile($file_path, $retrivedHost = '') {
		Billrun_Factory::dispatcher()->trigger('processorBeforeFileLoad', array(&$file_path, $this));
		if (file_exists($file_path)) {
			$this->filePath = $file_path;
			$this->filename = substr($file_path, strrpos($file_path, '/'));
			$this->retrievedHostname = $retrivedHost;
			$this->fileHandler = fopen($file_path, 'r');
			Billrun_Factory::log()->log("Billrun Processor load the file: " . $file_path, Zend_Log::INFO);
		} else {
			Billrun_Factory::log()->log("Billrun_Processor->loadFile: cannot load the file: " . $file_path, Zend_Log::ERR);
		}
		Billrun_Factory::dispatcher()->trigger('processorAfterFileLoad', array(&$file_path));
	}

	/**
	 * method to set the parser of the processor
	 * 
	 * @param Billrun_Parser|string|array $parser the parser to use by the processor or its name.
	 *
	 * @return mixed the processor itself (for concatening methods)
	 */
	public function setParser($parser) {
		if (is_object($parser)) {
			$this->parser = $parser;
		} else {
			$parser = is_array($parser) ? $parser : array('type' => $parser);
			$this->parser = Billrun_Parser::getInstance($parser);
		}
		return $this;
	}

	/**
	 * Backup the current processed file to the proper backup paths
	 * @param type $move should the file be moved when the backup ends?
	 */
	protected function backup($move = true) {
		$seqData = $this->getFilenameData($this->filename);
		for ($i = 0; $i < count($this->backupPaths); $i++) {
			$backupPath = $this->backupPaths[$i];
			$backupPath .= ($this->retrievedHostname ? DIRECTORY_SEPARATOR . $this->retrievedHostname : ""); //If theres more then one host or the files were retrived from a named host backup under that host name
			$backupPath .= DIRECTORY_SEPARATOR . ($seqData['date'] ? $seqData['date'] : date("Ym")); // if the file name has a date  save under that date else save under tthe current month
			$backupPath .= ($seqData['seq'] ? DIRECTORY_SEPARATOR . substr($seqData['seq'], 0, -$this->backup_seq_granularity) : ""); // brak the date to sequence number with varing granularity

			if ($this->backupToPath($backupPath, !($move && $i + 1 == count($this->backupPaths))) === TRUE) {
				Billrun_Factory::log()->log("Success backup file " . $this->filePath . " to " . $backupPath, Zend_Log::INFO);
			} else {
				Billrun_Factory::log()->log("Failed backup file " . $this->filePath . " to " . $backupPath, Zend_Log::INFO);
			}
		}
	}

	/**
	 * method to backup the processed file
	 * @param string $path  the path to backup the file to.
	 * @param boolean $copy copy or rename (move) the file to backup
	 * 
	 * @return boolean return true if success to backup
	 */
	public function backupToPath($path, $copy = false) {
		if ($copy) {
			$callback = "copy";
		} else {
			$callback = "rename";
		}
		if (!file_exists($path)) {
			@mkdir($path, 0777, true);
		}
		$target_path = $path . DIRECTORY_SEPARATOR . $this->filename;
		$ret = @call_user_func_array($callback, array(
				$this->filePath,
				$target_path,
			));
		if ($callback == 'copy' && $this->preserve_timestamps) {
			$timestamp = filemtime($this->filePath);
			Billrun_Util::setFileModificationTime($target_path, $timestamp);
		}
		return $ret;
	}

	/**
	 * Get the data the is stored in the file name.
	 * @return an array containing the sequence data. ie:
	 * 			array(seq => 00001, date => 20130101 )
	 */
	public function getFilenameData($filename) {
		return array(
			'seq' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($this->getType() . ".sequence_regex.seq", "/(\d+)/"), $filename),
			'date' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($this->getType() . ".sequence_regex.date", "/(20\d{4})/"), $filename),
			'time' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($this->getType() . ".sequence_regex.time", "/\D(\d{4,6})\D/"), $filename),
		);
	}

	/**
	 * mark a file in the log collection as being processed and return it
	 * @return Mongodloid_Entity the file to process on sucessful update false otherwise
	 */
	protected function getFileForProcessing() {
		$log = Billrun_Factory::db()->logCollection();
		$adoptThreshold = strtotime('-' . $this->orphandFilesAdoptionTime);
		$query = array(
			'$or' => array(
				array('start_process_time' => array('$exists' => false)),
				array('start_process_time' => array('$lt' => new MongoDate($adoptThreshold))),
			),
			'source' => static::$type,
			'process_time' => array(
				'$exists' => false,
			),
		);
		$update = array(
			'$set' => array(
				'start_process_time' => new MongoDate(time()),
			),
		);
		$options = array(
			'sort' => array(
				'received_time' => 1,
			),
			'new' => true,
		);
		$file = $log->findAndModify($query, $update, array(), $options);
		$file->collection($log);
		return $file;
	}

	public function fgetsIncrementLine($file_handler) {
		$ret = fgets($file_handler);
		if ($ret) {
			$this->current_line++;
		}
		return $ret;
	}

	protected function bulkAddToCollection($collection) {
		settype($this->bulkInsert, 'int');
		try {
			$bulkOptions = array(
				'continueOnError' => true,
				'wtimeout' => 300000,
				'timeout' => 300000,
			);
			$offset = 0;
			while ($insert_count = count($insert = array_slice($this->data['data'], $offset, $this->bulkInsert, true))) {
				Billrun_Factory::log()->log("Processor bulk insert " . basename($this->filePath) . " from: " . $offset . ' count: ' . $insert_count, Zend_Log::DEBUG);
				$collection->batchInsert($insert, $bulkOptions);
				$offset += $this->bulkInsert;
			}
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Processor store " . basename($this->filePath) . " failed on bulk insert with the next message: " . $e->getCode() . ": " . $e->getMessage(), Zend_Log::NOTICE);

			if ($e->getCode() == "11000") {
				Billrun_Factory::log()->log("Processor store " . basename($this->filePath) . " to queue failed on bulk insert on duplicate stamp.", Zend_Log::NOTICE);
				return $this->addToCollection($collection);
			}
			return false;
		}
		return true;
	}

	protected function bulkAddToQueue() {
		$queue = Billrun_Factory::db()->queueCollection();
		$queue_data = array_values($this->queue_data);
		try {
			$bulkOptions = array(
				'continueOnError' => true,
				'wtimeout' => 300000,
				'timeout' => 300000,
			);
			$offset = 0;
			while (count($insert = array_slice($queue_data, $offset, $this->bulkInsert, true))) {
				$queue->batchInsert($insert, $bulkOptions);
				$offset += $this->bulkInsert;
			}
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Processor store " . basename($this->filePath) . " to queue failed on bulk insert with the next message: " . $e->getCode() . ": " . $e->getMessage(), Zend_Log::NOTICE);

			if ($e->getCode() == "11000") {
				Billrun_Factory::log()->log("Processor store " . basename($this->filePath) . " to queue failed on bulk insert on duplicate stamp.", Zend_Log::NOTICE);
				return $this->addToQueue($queue_data);
			}

			return false;
		}
		return true;
	}

	protected function addToCollection($collection) {
		$this->data['stored_data'] = array();

		foreach ($this->data['data'] as $row) {
			try {
				$entity = new Mongodloid_Entity($row);
				$entity->save($collection, true);
				$this->data['stored_data'][] = $row;
			} catch (Exception $e) {
				Billrun_Factory::log()->log("Processor store " . basename($this->filePath) . " failed on stamp : " . $row['stamp'] . " with the next message: " . $e->getCode() . ": " . $e->getMessage(), Zend_Log::NOTICE);
				continue;
			}
		}
	}

	protected function addToQueue($queue_data) {
		$queue = Billrun_Factory::db()->queueCollection();
		foreach ($queue_data as $row) {
			try {
				$entity = new Mongodloid_Entity($row);
				$entity->save($queue, true);
			} catch (Exception $e) {
				Billrun_Factory::log()->log("Processor store " . basename($this->filePath) . " to queue failed on stamp : " . $row['stamp'] . " with the next message: " . $e->getCode() . ": " . $e->getMessage(), Zend_Log::NOTICE);
				continue;
			}
		}
	}

	/**
	 * prepare the queue before insert
	 */
	protected function prepareQueue() {
		foreach ($this->data['data'] as $row) {
			$row = array(
				'stamp' => $row['stamp'], 
				'type' => $row['type'], 
				'urt' => $row['urt'], 
				'calc_name' => false, 
				'calc_time' => false
			);
			$this->setQueueRow($row);
		}
	}
	
	/**
	 * get the queue data
	 * 
	 * @return array
	 */
	public function getQueueData() {
		return $this->queue_data;
	}
	
	/**
	 * set queue row
	 * @param array $row
	 */
	public function setQueueRow($row) {
		$this->queue_data[$row['stamp']] = $row;
	}

	/**
	 * set queue row step
	 * this method enable to step forward or backword queue step
	 * 
	 * @param string $stamp
	 * @param string $step
	 */
	public function setQueueRowStep($stamp, $step) {
		$this->queue_data[$stamp]['calc_name'] = $step;
	}

	/**
	 * unset row from the queue to disable it from inserted to DB queue collection
	 * 
	 * @param string $stamp the row (to unset) stamp
	 */
	public function unsetQueueRow($stamp) {
		unset($this->queue_data[$stamp]);
	}

	/**
	 * method to check if the queue is full (depend on configuration queue.max_size)
	 * 
	 * @return boolean true if full else false
	 */
	protected function isQueueFull() {
		$queue_max_size = Billrun_Factory::config()->getConfigValue("queue.max_size", 999999999);
		return (Billrun_Factory::db()->queueCollection()->count() >= $queue_max_size);
	}

}