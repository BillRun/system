<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract processor class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Processor extends Billrun_Base {

	use Billrun_Traits_FileActions;

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
	 *
	 * @var string the stamp of the processed entry in log collection
	 */
	protected $file_stamp = null;

	/**
	 *
	 * @var string SHould the bluk inserted lines be ordered before  the actuall  insert is done.
	 */
	protected $orderLinesBeforeInsert = false;

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


		if (isset($options['orphan_files_time'])) {
			$this->orphandFilesAdoptionTime = $options['orphan_files_time'];
		} else if (isset($options['processor']['orphan_files_time'])) {
			$this->orphandFilesAdoptionTime = $options['processor']['orphan_files_time'];
		}

		if (isset($options['processor']['limit']) && $options['processor']['limit']) {
			$this->setLimit($options['processor']['limit']);
		}
		if (isset($options['bulkInsert'])) {
			$this->bulkInsert = $options['bulkInsert'];
		}

		if (isset($options['processor']['order_lines_before_insert'])) {
			$this->orderLinesBeforeInsert = $options['processor']['order_lines_before_insert'];
		}
		if (isset($options['backup_path'])) {
			$this->backupPaths = $options['backup_path'];
		} else {
			$this->backupPaths = Billrun_Factory::config()->getConfigValue($this->getType() . '.backup_path', array('./backup/' . $this->getType()));
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
	 * method to run over all the files received which did not have been processed
	 */
	public function process_files() {

		$log = Billrun_Factory::db()->logCollection();

		$linesCount = 0;

		for ($i = $this->getLimit(); $i > 0; $i--) {
			if ($this->isQueueFull()) {
				Billrun_Factory::log()->log("Billrun_Processor: queue size is too big", Zend_Log::ALERT);
				return $linesCount;
			} else {
				$this->init();
				$file = $this->getFileForProcessing();
				if ($file->isEmpty()) {
					break;
				}
				$this->setStamp($file->getID());
				$this->setFileStamp($file);
				$this->loadFile($file->get('path'), $file->get('retrieved_from'));
				$processedLinesCount = $this->process();
				if (FALSE !== $processedLinesCount) {
					$linesCount += $processedLinesCount;
				}
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
		$this->queue_data = array();
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
			Billrun_Factory::log()->log("Billrun_Processor: queue size is too big", Zend_Log::ALERT);
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

			$this->removefromWorkspace($this->getFileStamp());
			Billrun_Factory::dispatcher()->trigger('afterProcessorRemove', array($this));
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

		$header['linesStats']['queue'] = count($this->queue_data);
		$header['linesStats']['good'] = count($this->data['data']) - $header['linesStats']['queue'];

		$current_stamp = $this->getStamp(); // mongo id in new version; else string
		if ($current_stamp instanceof Mongodloid_Entity || $current_stamp instanceof Mongodloid_Id) {
			$resource = $log->findOne($current_stamp);
			if (!empty($header)) {
				$resource->set('header', $header, true);
			}
			if (!empty($trailer)) {
				$resource->set('trailer', $trailer, true);
			}
			$resource->set('process_hostname', Billrun_Util::getHostName(), true);
			$resource->set('process_time', date(self::base_dateformat), true);
			return $resource->save($log);
		} else {
			// backward compatibility
			// old method of processing => receiver did not logged, so it's the first time the file logged into DB
			$entity = new Mongodloid_Entity($trailer);
			if ($log->query('stamp', $entity->get('stamp'))->count() > 0) {
				Billrun_Factory::log()->log("Billrun_Processor::logDB - DUPLICATE! trying to insert duplicate log file with stamp of : {$entity->get('stamp')}", Zend_Log::NOTICE);
				return FALSE;
			}
			return $entity->save($log);
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
			Billrun_Factory::log()->log('Got empty data from file  : ' . basename($this->filePath) , Zend_Log::ERR);
			return false;
		}

		$lines = Billrun_Factory::db()->linesCollection();
		Billrun_Factory::log()->log("Store data of file " . basename($this->filePath) . " with " . count($this->data['data']) . " lines", Zend_Log::INFO);
		$queue_data = $this->getQueueData();
		if ($this->bulkInsert) {
			settype($this->bulkInsert, 'int');
			if (!$this->bulkAddToCollection($lines)) {
				return false;
			}
			Billrun_Factory::log()->log("Storing " . count($this->queue_data) . " queue lines of file " . basename($this->filePath), Zend_Log::INFO);
			if (!$this->bulkAddToQueue()) {
				return false;
			}
		} else {
			$this->addToCollection($lines);
			Billrun_Factory::log()->log("Storing " . count($queue_data) . " queue lines of file " . basename($this->filePath), Zend_Log::INFO);
			$this->addToQueue($queue_data);
		}
		
 		$queue_stamps = array_keys($queue_data); 
 		$lines->update(array('stamp' => array('$in' => $queue_stamps)), array('$set' => array('in_queue' => true)), array("multiple" => true)); 
		Billrun_Factory::log()->log("Finished storing data of file " . basename($this->filePath), Zend_Log::INFO);
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

		// verify minimum orphan time to avoid parallel processing
		if (Billrun_Factory::config()->isProd() && (time() - $adoptThreshold) < 3600) {
			Billrun_Factory::log()->log("Processor orphan time less than one hour: " . $this->orphandFilesAdoptionTime . ". Please set value greater than or equal to one hour. We will take one hour for now", Zend_Log::NOTICE);
			$adoptThreshold = time() - 3600;
		}
		$query = array(
			'source' => static::$type,
			'process_time' => array(
				'$exists' => false,
			),
			'$or' => array(
				array('start_process_time' => array('$exists' => false)),
				array('start_process_time' => array('$lt' => new MongoDate($adoptThreshold))),
			),
			'received_time' => array(
				'$exists' => true,
			),
		);
		$update = array(
			'$set' => array(
				'start_process_time' => new MongoDate(time()),
				'start_process_host' => Billrun_Util::getHostName(),
				'start_process_ospid' => Billrun_Util::getPid(),
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
		$lines_data = $this->data['data'];

		if ($this->orderLinesBeforeInsert) {
			Billrun_Factory::log()->log("Reordering lines  by stamp...", Zend_Log::DEBUG);
			uasort($lines_data, function($a, $b) {
				return strcmp($a['stamp'], $b['stamp']);
			});
			Billrun_Factory::log()->log("Done reordering lines  by stamp.", Zend_Log::DEBUG);
		}

		try {
			if (Billrun_Factory::db()->compareServerVersion('2.6', '>=') === true && Billrun_Factory::db()->compareClientVersion('1.5', '>=') === true) {
				// we are on 2.6
				$bulkOptions = array(
					'continueOnError' => true,
					'socketTimeoutMS' => 300000,
					'wTimeoutMS' => 300000,
					'w' => 1,
				);
			} else {
				// we are on 2.4 and lower
				$bulkOptions = array(
					'continueOnError' => true,
					'wtimeout' => 300000,
					'timeout' => 300000,
					'w' => 1,
				);
			}
			$offset = 0;
			while ($insert_count = count($insert = array_slice($lines_data, $offset, $this->bulkInsert, true))) {
				Billrun_Factory::log()->log("Processor bulk insert to lines " . basename($this->filePath) . " from: " . $offset . ' count: ' . $insert_count, Zend_Log::DEBUG);
				$collection->batchInsert($insert, $bulkOptions);
				$offset += $this->bulkInsert;
			}
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Processor store " . basename($this->filePath) . " failed on bulk insert with the next message: " . $e->getCode() . ": " . $e->getMessage(), Zend_Log::ALERT);

			if ($e->getCode() == "11000" ||
				 (	$e instanceof MongoCursorException &&
					$e->getCode() == "911" &&
					method_exists($e,'getDocument') && ($expDoc = $e->getDocument() ) && is_array($expDoc['writeErrors']) &&
					count(array_filter(function($werr) {
					 return $werr['code'] != '11000';
				},$expDoc['writeErrors'])) == 0) ) {
				Billrun_Factory::log()->log("Processor store " . basename($this->filePath) . " to queue failed on bulk insert on duplicate stamp.", Zend_Log::ALERT);
				return $this->addToCollection($collection);
			}
			return false;
		}
		return true;
	}

	protected function bulkAddToQueue() {
		$queue = Billrun_Factory::db()->queueCollection();
		$queue_data = array_values($this->queue_data);
		if ($this->orderLinesBeforeInsert) {
			Billrun_Factory::log()->log("Reordering Q lines  by stamp...", Zend_Log::DEBUG);
			uasort($queue_data, function($a, $b) {
				return strcmp($a['stamp'], $b['stamp']);
			});
			Billrun_Factory::log()->log("Done reordering Q lines  by stamp.", Zend_Log::DEBUG);
		}
		try {
			if (Billrun_Factory::db()->compareServerVersion('2.6', '>=') === true && Billrun_Factory::db()->compareClientVersion('1.5', '>=') === true) {
				// we are on 2.6
				$bulkOptions = array(
					'continueOnError' => true,
					'socketTimeoutMS' => 300000,
					'wTimeoutMS' => 300000,
					'w' => 1,
				);
			} else {
				// we are on 2.4 and lower
				$bulkOptions = array(
					'continueOnError' => true,
					'wtimeout' => 300000,
					'timeout' => 300000,
					'w' => 1,
				);
			}
			$offset = 0;
			while ($insert_count = count($insert = array_slice($queue_data, $offset, $this->bulkInsert, true))) {
				Billrun_Factory::log()->log("Processor bulk insert to queue " . basename($this->filePath) . " from: " . $offset . ' count: ' . $insert_count, Zend_Log::DEBUG);
				$queue->batchInsert($insert, $bulkOptions);
				$offset += $this->bulkInsert;
			}
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Processor store " . basename($this->filePath) . " to queue failed on bulk insert with the next message: " . $e->getCode() . ": " . $e->getMessage(), Zend_Log::ALERT);

			if ($e->getCode() == "11000" ||
				 (	$e instanceof MongoCursorException &&
					$e->getCode() == "911" &&
					method_exists($e,'getDocument') && ($expDoc = $e->getDocument() ) && is_array($expDoc['writeErrors']) &&
					count(array_filter(function($werr) {
					 return $werr['code'] != '11000';
				},$expDoc['writeErrors'])) == 0)) {
				Billrun_Factory::log()->log("Processor store " . basename($this->filePath) . " to queue failed on bulk insert on duplicate stamp.", Zend_Log::ALERT);
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
				$entity->save($collection);
				$this->data['stored_data'][] = $row;
			} catch (Exception $e) {
				Billrun_Factory::log()->log("Processor store " . basename($this->filePath) . " failed on stamp : " . $row['stamp'] . " with the next message: " . $e->getCode() . ": " . $e->getMessage(), Zend_Log::NOTICE);
				continue;
			}
		}
		return true;
	}

	protected function addToQueue($queue_data) {
		$queue = Billrun_Factory::db()->queueCollection();
		foreach ($queue_data as $row) {
			try {
				$entity = new Mongodloid_Entity($row);
				$entity->save($queue);
			} catch (Exception $e) {
				Billrun_Factory::log()->log("Processor store " . basename($this->filePath) . " to queue failed on stamp : " . $row['stamp'] . " with the next message: " . $e->getCode() . ": " . $e->getMessage(), Zend_Log::NOTICE);
				continue;
			}
		}
		return true;
	}

	/**
	 * prepare the queue before insert
	 */
	protected function prepareQueue() {
		foreach ($this->data['data'] as $dataRow) {
			$queueRow = $dataRow;
 			$queueRow['calc_name'] = false;
 			$queueRow['calc_time'] = false;
			$this->setQueueRow($queueRow);
		}
	}

	/**
	 * method to add advanced properties to row
	 * 
	 * @param Array $row data row from lines
	 * 
	 * @return true if succeed to add advanced properties, else false
	 */
	public function addAdvancedPropertiesToQueueRow($row) { // TODO: call this function once from CalcCpu
		$queue_row = $this->getQueueRow($row);
		if ($queue_row === FALSE) {
			return false;
		}
		if (!is_array($row)){
			$row = $row->getRawData();
		}
		$updated_queue_row = array_merge($queue_row, $row);
		if (!isset($updated_queue_row['stamp'])) {
			$updated_queue_row['stamp'] = $row['stamp'];
		}
		$this->setQueueRow($updated_queue_row);
		return true;
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
	 * get queue row
	 * @param mixed $row if array will take the stamp from it, else bring the row by string stamp
	 * 
	 * return mixed the queue row if exists, else false
	 */
	public function getQueueRow($row) {
		if (is_string($row) && isset($this->queue_data[$row])) {
			return $this->queue_data[$row];
		}
		if (isset($this->queue_data[$row['stamp']])) {
			return $this->queue_data[$row['stamp']];
		}
		return false;
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
	 * get queue row step
	 * 
	 * @param string $stamp the row stamp
	 * 
	 * @return string the queue step
	 */
	public function getQueueRowStep($stamp) {
		return $this->queue_data[$stamp]['calc_name'];
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

	protected function setFileStamp($file) {
		$this->file_stamp = $file['stamp'];
	}

	protected function getFileStamp() {
		return $this->file_stamp;
	}
	
}
