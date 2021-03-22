<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract exporter class
 * Exporter class should handle:
 *   1. fetching data (from DB) according to configured query
 *   2. mapping the data according to configuration
 *   3. export the data (to a file or any other export option)
 *   4. send the exported data to a configured location (FTP, SFTP, etc...)
 * 
 * @package  Billing
 * @since    5.9
 */
class Billrun_Exporter extends Billrun_Generator_File {
	use Billrun_Traits_ConditionsCheck;
	/**
	 * Type of exporter
	 *
	 * @var string
	 */
	static protected $type = 'exporter';
	
	const SEQUENCE_NUM_INIT = 1;
        
        const DEFAULT_FILENAME = 'EXPORT_[[param1]]_[[param2]].CSV';
        const DEFAULT_FILENAME_PARMS = [
            [
                "param" => "param1",
                "type" => "autoinc",
                "min_value" => 1,
                "date_group" => "e",
                "padding" => [
                    "character" => "0",
                    "length" => 6,
                    "direction" => "left"
                ],
                "value" => "now"
            ],
            [
                "param" => "param2",
                "type" => "date",
                "format" => "YmdHis",
                "value" => "now"
            ]
        ];

    /**
	 * the name of the log collection in the DB
	 * @var string
	 */
	protected $logCollection = null;
	
	/**
	 * sequence number unique for the specific export
	 * @var string
	 */
	protected $sequenceNum = null;
	
	/**
	 * datetime the export has started
	 * @var unixtimestamp 
	 */
	protected $exportTime = null;
	
	/**
	 * unique stamp for the export
	 * @var string
	 */
	protected $exportStamp = null;
	
	/**
	 * unique stamp for log collection
	 * @var string
	 */
	protected $logStamp = array();
	
	/**
	 * collection name (DB) from which data should be fetched
	 * @var string
	 */
	protected $collection = null;
	
	/**
	 * query by which data should be fetched from DB
	 * @var array
	 */
	protected $query = array();

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->exportTime = time();
		$this->exportStamp = $this->getExportStamp();
		$this->query = $this->getFiltrationQuery();
		$this->logCollection = Billrun_Factory::db()->logCollection();
	}
	
	protected function getLinkedEntityData($entity, $params, $field) {
        switch ($entity) {
            case 'line':
				$value = Billrun_Util::getIn($params, $field, null);
                if (!isset($value)) {
                    $message = 'Unknown field in line';
                    throw new Exception($message);
                }
                return $value;
            default:
                $message = "Unknown entity: " . $entity . ", as 'linked entity' in the config.";
                Billrun_Factory::log($message, Zend_Log::ERR);
        }
    }

	
	/**
	 * get stamp for the current run of the exporter
	 */
	protected function getExportStamp() {
		if (is_null($this->exportStamp)) {
			$this->exportStamp = uniqid();
		}
		return $this->exportStamp;
	}
	
	/**
	 * gets collection to load data from DB
	 * 
	 * @return string
	 */
	protected function getCollection() {
		if (is_null($this->collection)) {
			$collectionName = $this->getCollectionName();
			$this->collection = Billrun_Factory::db()->{"{$collectionName}Collection"}();
		}
		return $this->collection;
	}
	
	/**
	 * get query to load data from the DB
	 */
	protected function getFiltrationQuery() {
		$querySettings = $this->config['filtration'][0]; // TODO: currenly, supporting 1 query might support more in the future
		$query = $this->getConditionsQuery($querySettings['query']);
                if ($query === true){
                    return [];
                }
		if (isset($querySettings['time_range'])) {
			$timeRange = $querySettings['time_range'];
			if (isset($querySettings['time_range_hour'])) {
				$hour = $querySettings['time_range_hour'];
				$endTime = strtotime($hour, $this->exportTime);
				$startTime = strtotime($timeRange . ' ' . $hour, $endTime);
			} else {
				$endTime = $this->exportTime;
				$startTime = strtotime($timeRange, $endTime);
			}
			$query['urt'] = array(
				'$gte' => new MongoDate($startTime),
				'$lt' => new MongoDate($endTime),
			);
		}
		return $query;
	}
	
	/**
	 * general function to handle the export
	 *
	 * @return array list of lines exported
	 */
	public function generate() {
		Billrun_Factory::dispatcher()->trigger('beforeExport', array($this));
		$this->beforeExport();
		$className = $this->getGeneratorClassName();
		$generatorOptions = $this->buildGeneratorOptions();
		$this->fileGenerator = new $className($generatorOptions);
		$this->fileGenerator->generate();
		$transactionCounter = $this->fileGenerator->getTransactionsCounter();
		$this->afterExport();
		Billrun_Factory::dispatcher()->trigger('afterExport', array(&$this->rowsToExport, $this));
		Billrun_Factory::log("Exported " . $transactionCounter . " " . $this->getCollectionName());
	}
	
	/**
	 * gets record type according to configuration mapping
	 * 
	 * @return string
	 */
	protected function getRecordType($row) {
		foreach (Billrun_Util::getIn($this->config, 'generator.record_type_mapping', array()) as $recordTypeMapping) {
			foreach ($recordTypeMapping['conditions'] as $condition) {
				if (!Billrun_Util::isConditionMet($row, $condition)) {
					continue 2;
				}
			}
			return $recordTypeMapping['record_type'];
		}
		return '';
	}
	
	/**
	 * translate row to the format it should be exported
	 * 
	 * @param array $row
	 * @return array
	 */
	protected function getRecordData($row) { 
		Billrun_Factory::dispatcher()->trigger('ExportBeforeGetRecordData', array(&$row, $this));
		$recordType = $this->getRecordType($row);
		$ret = $this->getDataLine($row, $recordType);
		Billrun_Factory::dispatcher()->trigger('ExportAfterGetRecordData', array(&$row, &$ret, $this));
		return $ret;
	}
	
		/**
	 * get rows to be exported
	 * 
	 * @return array
	 */
	protected function loadRows() {
		$collection = $this->getCollection();
		Billrun_Factory::dispatcher()->trigger('ExportBeforeLoadRows', array(&$this->query, $collection, $this));
		$rows = $collection->query($this->query)->cursor();
		$data = array();
		foreach ($rows as $row) {
			$rawRow = $row->getRawData();
			$this->rawRows[] = $rawRow;
			$data[] = $this->getRecordData($rawRow);
		}
		Billrun_Factory::dispatcher()->trigger('ExportAfterLoadRows', array(&$this->rawRows, &$this->rowsToExport, $this));
		return $data;
	}
	
	/**
	 * method to log the export process
	 */
	protected function logDB($stamp, $data) {
		if (empty($stamp)) {
			Billrun_Factory::log()->log("Billrun_Exporter::logDB - got export with empty stamp. data: " . print_R($data, 1), Zend_Log::NOTICE);
			return false;
		}
		$log = Billrun_Factory::db()->logCollection();
		Billrun_Factory::dispatcher()->trigger('beforeLogExport', array(&$data, $stamp, $this));
		
		$query = array(
			'stamp' =>  $stamp,
			'source' => 'export',
			'type' => static::$type,
		);

		$update = array(
			'$set' => $data,
		);

		$result = $this->logCollection->update($query, $update, array('w' => 1));
		$success = $result == true || ($result['n'] == 1 && $result['ok'] == 1);

		if (!$success) {
			Billrun_Factory::log()->log("Billrun_Exporter::logDB - Failed when trying to update an export log record with stamp of : {$stamp}. data: " . print_R($data, 1), Zend_Log::NOTICE);
			return false;
		}
		
		return true;
	}
	
	/**
	 * creates basic log in DB
	 * 
	 * @param string $stamp
	 * @return type
	 */
	protected function createLogDB($stamp, $data = array()) {		
		$basicLogData = array(
			'stamp' =>  $stamp,
			'source' => 'export',
			'type' => static::$type,
			'export_hostname' => Billrun_Util::getHostName(),
			'export_start_time' => new MongoDate(),
		);
		$logData = array_merge($basicLogData, $data);

		$result = $this->logCollection->insert($logData);
		$success = $result == true || ($result['n'] == 1 && $result['ok'] == 1);

		if (!$success) {
			Billrun_Factory::log()->log("Billrun_Exporter::createLogDB - Failed when trying to insert an export log record" . print_r($logData, 1) . " with stamp of : {$stamp}", Zend_Log::NOTICE);
			return false;
		}
		
		return true;
	}
	
	/**
	 * mark the lines which are about to be exported
	 */
	function beforeExport() {
		$this->query['export_start.' . static::$type] = array(
			'$exists' => false,
		);
		$this->query['export_stamp.' . static::$type] = array(
			'$exists' => false,
		);
		$update = array(
			'$set' => array(
				'export_start.' . static::$type => new MongoDate(),
				'export_stamp.' . static::$type => $this->exportStamp,
			),
		);
		$options = array(
			'multiple' => true,
		);
		
		$collection = $this->getCollection();
		$collection->update($this->query, $update, $options);
		unset($this->query['export_start.' . static::$type]);
		$this->query['export_stamp.' . static::$type] = $this->exportStamp;
		$this->createLogDB($this->getLogStamp());
	}
	
		/**
	 * gets data to log after export is done
	 * 
	 * @return array
	 */
	protected function getLogData() {
		return array(
			'sequence_num' => $this->getSequenceNumber(),
			'exported_time' => new MongoDate(),
		);
	}
	
	/**
	 * gets stamp in use for the log
	 * 
	 * @return type
	 */
	protected function getLogStamp() {
		if (empty($this->logStamp)) {
			$stampArr = array(
				'export_stamp' => $this->exportStamp,
				'sequence_num' => $this->getSequenceNumber(),
			);
			$this->logStamp = Billrun_Util::generateArrayStamp($stampArr);
		}
		return $this->logStamp;
	}
	
	/**
	 * mark the lines as exported
	 */
	protected function afterExport() {
		$stamps = array();
		foreach ($this->rawRows as $row) {
			$stamps[] = $row['stamp'];
		}
		$query = array(
			'stamp' => array(
				'$in' => $stamps,
			),
		);
		$update = array(
			'$set' => array(
				'exported.' . static::$type => new MongoDate(),
			),
		);
		$options = array(
			'multiple' => true,
		);
		
		$collection = $this->getCollection();
		$collection->update($query, $update, $options);
		$this->logDB($this->getLogStamp(), $this->getLogData());
	}
	
	/**
	 * gets Collection name
	 * 
	 * @return string
	 */
	protected function getCollectionName() {
		$querySettings = $this->config['filtration'][0]; // TODO: currenly, supporting 1 query might support more in the future
		return $querySettings['collection'];
	}
	
	/**
	 * gets current sequence number for the file
	 * 
	 * @return string - number in the range of 00001-99999
	 */
	protected function getSequenceNumber($row = array(), $mapping = array()) {
		if (is_null($this->sequenceNum)) {
			$query = array(
				'source' => 'export',
				'type' => static::$type,
				'sequence_num' => array(
					'$exists' => true,
				),
			);
			$sort = array(
				'sequence_num' => -1,
				'export_start_time' => -1,
			);
			$lastSeq = $this->logCollection->query($query)->cursor()->sort($sort)->limit(1)->current()->get('sequence_num');
			if (is_null($lastSeq)) {
				$nextSeq = self::SEQUENCE_NUM_INIT;
			} else {
				$nextSeq = $lastSeq + 1;
			}
			
			$this->sequenceNum = $nextSeq;
		}
		
		$length = intval(Billrun_Util::getIn($mapping, 'func.length', 5));
		$this->sequenceNum = sprintf('%0' . $length . 'd', $this->sequenceNum % pow(10, $length));
		return $this->sequenceNum;
	}
	
	public function move() {
		foreach (Billrun_Util::getIn($this->config, 'senders', array()) as $connections) {
                    foreach ($connections as  $connection){
			$sender = Billrun_Sender::getInstance($connection);
			if (!$sender) {
				Billrun_Factory::log()->log("Cannot get sender. details: " . print_R($connections, 1), Zend_Log::ERR);
				continue;
			}
			$sender->send($this->getExportFilePath());
                    }
		}
	}
	
	protected function getExportFilePath(){
		$filePath = $this->getFilePath();
		return rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->getFileName();
	}
	
	protected function buildGeneratorOptions() {
		$this->fileNameParams = isset($this->config['filename_params']) ? $this->config['filename_params'] : self::DEFAULT_FILENAME_PARMS;
		$this->fileNameStructure = isset($this->config['filename']) ? $this->config['filename'] : self::DEFAULT_FILENAME;
		$this->fileName = $this->getFilename();
		$options['file_name'] = $this->fileName;
		$options['file_type'] = $this->getType();
		$this->localDir = $this->getFilePath();
		$options['local_dir'] = $this->localDir;
		$options['file_path'] = $this->localDir . DIRECTORY_SEPARATOR . $this->fileName;
		$this->rowsToExport = $this->loadRows();
        $options['data'] = $this->rowsToExport;
		$this->headerToExport[0] = $this->getHeaderLine();
        $options['headers'] = $this->headerToExport;
		$this->footerToExport[0] = $this->getTrailerLine();
        $options['trailers'] = $this->footerToExport;
        $options['type'] = $this->config['generator']['type'];
		$options['force_header'] = $this->config['generator']['force_header'] ?? false;
		$options['force_footer'] = $this->config['generator']['force_footer'] ?? false;
        $options['configByType'] = $this->config;
        if (isset($this->config['generator']['separator'])) {
            $options['delimiter'] = $this->config['generator']['separator'];
        }
        return $options;
    }
	
	/**
	 * Get the type name of the current object.
	 * @return string conatining the current.
	 */
	public function getType() {
		return static::$type ;
	}

	public function getFileType() {
		return $this->config['name'] ;
	}
	
	public function getAction() {
		return "export_generators";
	}

}
