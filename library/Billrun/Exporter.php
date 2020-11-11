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
abstract class Billrun_Exporter extends Billrun_Base {

	/**
	 * Type of exporter
	 *
	 * @var string
	 */
	static protected $type = 'exporter';
	
	const SEQUENCE_NUM_INIT = 1;
	
	/**
	 * configuration for internal use of the exporter
	 * 
	 * @var array
	 */
	protected $config = array();
	
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
	
	/**
	 * data to export (after translation)
	 * @var array
	 */
	protected $rowsToExport = array();
	
	/**
	 * header to export (after translation)
	 * @var type 
	 */
	protected $headerToExport = null;
	
	/**
	 * footer to export (after translation)
	 * @var type 
	 */
	protected $footerToExport = null;
	
	/**
	 * raw lines from DB that should be exported (before translation)
	 * @var type 
	 */
	protected $rawRows = array();

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->config = $options;
		$this->exportTime = time();
		$this->exportStamp = $this->getExportStamp();
		$this->query = $this->getQuery();
		$this->logCollection = Billrun_Factory::db()->logCollection();
	}
	////////////////////// need to be in separate class /////////////////////////////////////
	protected function buildGeneratorOptions() {
		$this->fileNameParams = isset($this->config['filename_params']) ? $this->config['filename_params'] : '';
		$this->fileNameStructure = isset($this->config['filename']) ? $this->config['filename'] : '';
        $options['data'] = $this->loadRows();
		$headers[0] = $this->getHeaderLine();
        $options['headers'] = $headers;
		$trailers[0] = $this->getTrailerLine();
        $options['trailers'] = $trailers;
        $options['type'] = $this->config['generator']['type'];
        $options['configByType'] = $this->config;
        if (isset($this->config['generator']['separator'])) {
            $options['delimiter'] = $this->config['generator']['separator'];
        }
        $options['file_type'] = $this->config['file_type'];
		$localDir = $this->getFilePath();
        $options['file_path'] = $localDir;
		$options['local_dir'] = $localDir;
		$fileName = $this->getFilename();
		$options['file_name'] = $fileName;
        return $options;
    }
	
	protected function getGeneratorClassName() {
        if (!isset($this->config['generator']['type'])) {
            $message = 'Missing generator type for ' . $this->config['file_type'];
            $this->logFile->updateLogFileField('errors', $message);
            throw new Exception($message);
        }
        switch ($this->config['generator']['type']) {
            case 'fixed':
            case 'separator':
                $generatorType = 'Csv';
                break;
            case 'xml':
                $generatorType = 'Xml';
                break;
            default:
                $message = 'Unknown generator type for ' . $this->config['file_type'];
                throw new Exception($message);
        }

        $className = "Billrun_Generator_PaymentGateway_" . $generatorType;
        return $className;
    }
	
	protected function getHeaderLine() {
        $headerStructure = $this->config['generator']['header_structure'];
        return $this->buildLineFromStructure($headerStructure);
    }

    protected function getTrailerLine() {
        $trailerStructure = $this->config['generator']['trailer_structure'];
        return $this->buildLineFromStructure($trailerStructure);
    }
	
	    protected function getDataLine($params) {
        $dataStructure = $this->config['generator']['data_structure'];
        return $this->buildLineFromStructure($dataStructure, $params);
    }
	
	protected function buildLineFromStructure($structure, $params = null) {
        $line = array();
        foreach ($structure as $field) {
            if (!isset($field['path'])) {
                $message = "Exporter " . $this->config['file_type'] . " header/trailer structure is missing a path";
                Billrun_Factory::log($message, Zend_Log::ERR);
                continue;
            }
            if (isset($field['predefined_values']) && $field['predefined_values'] == 'now') {
                $dateFormat = isset($field['format']) ? $field['format'] : Billrun_Base::base_datetimeformat;
                $line[$field['path']] = date($dateFormat, time());
            }
            if (isset($field['hard_coded_value'])) {
                $line[$field['path']] = $field['hard_coded_value'];
            }
			if (isset($field['linked_entity'])) {
				$line[$field['path']] = $this->getLinkedEntityData($field['linked_entity']['entity'], $params, $field['linked_entity']['field_name']);
			}
            if ((isset($field['type']) && $field['type'] == 'date') && (!isset($field['predefined_values']) && $field['predefined_values'] !== 'now')) {
                $dateFormat = isset($field['format']) ? $field['format'] : Billrun_Base::base_datetimeformat;
                $date = strtotime($line[$field['path']]);
                if ($date) {
                    $line[$field['path']] = date($dateFormat, $date);
                } else {
                    $message = "Couldn't convert date string when generating file type " . $this->config['file_type'];
                    Billrun_Factory::log($message, Zend_Log::ERR);
                }
            }
            if (!isset($line[$field['path']])) {
                $configObj = $field['name'];
                $message = "Field name " . $configObj . " config was defined incorrectly when generating file type " . $this->config['file_type'];
                throw new Exception($message);
            }
            
            $attributes = $this->getLineAttributes($field);
            
            if (isset($field['number_format'])) {
                $line[$field['path']] = $this->setNumberFormat($field, $line);
            }
            $line[$field['path']] = $this->prepareLineForGenerate($line[$field['path']], $field, $attributes);
        }
        if ($this->config['generator']['type'] == 'fixed' || $this->config['generator']['type'] == 'separator') {
            ksort($line);
        }
        return $line;
    }
	
	protected function setNumberFormat($field, $line) {
        if((!isset($field['number_format']['dec_point']) && (isset($field['number_format']['thousands_sep']))) || (isset($field['number_format']['dec_point']) && (!isset($field['number_format']['thousands_sep'])))){
            $message = "'dec_point' or 'thousands_sep' is missing in one of the entities, so only 'decimals' was used, when generating file type " . $this->configByType['file_type'];
            Billrun_Factory::log($message, Zend_Log::WARN);
            $this->logFile->updateLogFileField('warning', $message);
        }
        if (isset($field['number_format']['dec_point']) && isset($field['number_format']['thousands_sep']) && isset($field['number_format']['decimals'])){
            return number_format((float)$line[$field['path']], $field['number_format']['decimals'], $field['number_format']['dec_point'], $field['number_format']['thousands_sep']);
        } else {
            if (isset($field['number_format']['decimals'])){
                return number_format((float)$line[$field['path']], $field['number_format']['decimals']); 
            }
        }
    }
	
	    /**
     * Function returns line's attributes, if exists
     * @param type $field
     * @return array $attributes.
     */
    protected function getLineAttributes($field){
        if(isset($field['attributes'])){
            return $field['attributes'];
        } else {
            return [];
        }
    }
	
	protected function prepareLineForGenerate($lineValue, $addedData, $attributes) {
        $newLine = array();
		$newLine['value'] = $lineValue;
        $newLine['name'] = $addedData['name'];
        if (count($attributes) > 0) {
            for ($i = 0; $i < count($attributes); $i++) {
                $newLine['attributes'][] = $attributes[$i];
            }
        }
        if (isset($addedData['padding'])) {
            $newLine['padding'] = $addedData['padding'];
        }
        return $newLine;
    }
	
	/**
	 * gets file path for export
	 * 
	 * @return string
	 */
	protected function getFilename() {
        if (!empty($this->fileName)) {
            return $this->fileName;
        }
        $translations = array();
        if(is_array($this->fileNameParams)){
            foreach ($this->fileNameParams as $paramObj) {
                $translations[$paramObj['param']] = $this->getTranslationValue($paramObj);
            }
        }

        $this->fileName = Billrun_Util::translateTemplateValue($this->fileNameStructure, $translations, null, true);
        return $this->fileName;
    }
	
	
	//abstract getLinkedEntityData()- who generate from the new class need ro implement this function  
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	protected function getFilePath() {
		$sharedPath = Billrun_Util::getBillRunSharedFolderPath(Billrun_Util::getIn($this->config, 'workspace', 'workspace'));
		return rtrim($sharedPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'export' . DIRECTORY_SEPARATOR . date("Ym") . DIRECTORY_SEPARATOR . substr(md5(serialize($this->config)), 0, 7) . DIRECTORY_SEPARATOR;
	}
	
	protected function getLinkedEntityData($entity, $params, $field) {
        switch ($entity) {
            case 'line':
                if (!isset($params[$field])) {
                    $message = 'Unknown field in line';
                    throw new Exception($message);
                }

                return $params[$field];
            default:
                $message = "Unknown entity: " . $entity . ", as 'linked entity' in the config.";
                $this->logFile->updateLogFileField('errors', $message);
                Billrun_Factory::log($message, Zend_Log::ERR);
        }
    }
	
	public static function getInstance() {
		$args = func_get_args();
		$stamp = md5(static::class . serialize($args));
		if (isset(self::$instance[$stamp])) {
			return self::$instance[$stamp];
		}

		$args = $args[0];
		$exportGeneratorSettings = Billrun_Factory::config()->getExportGeneratorSettings($args['type']);
		if (!$exportGeneratorSettings) {
			Billrun_Factory::log("Can't get configurarion: " . print_R($args, 1), Zend_Log::EMERG);
			return false;
		}
		$params = array_merge($exportGeneratorSettings, $args);
		$exporterType = Billrun_Util::getIn($exportGeneratorSettings, 'exporter.type', '');
		$class = 'Billrun_Exporter_' . ucfirst($exporterType);
		if (!@class_exists($class, true)) {
			Billrun_Factory::log("Can't find class: " . $class, Zend_Log::EMERG);
			return false;
		}
		self::$instance[$stamp] = new $class($params);
		return self::$instance[$stamp];
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
			$querySettings = $this->config['queries'][0]; // TODO: currenly, supporting 1 query might support more in the future
			$collectionName = $querySettings['collection'];
			$this->collection = Billrun_Factory::db()->{"{$collectionName}Collection"}();
		}
		return $this->collection;
	}
	
	/**
	 * get query to load data from the DB
	 */
	protected function getQuery() {
		$querySettings = $this->config['queries'][0]; // TODO: currenly, supporting 1 query might support more in the future
		$query = json_decode($querySettings['query'], JSON_OBJECT_AS_ARRAY);
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
	abstract function handleExport();
	
	/**
	 * general function to handle the export
	 *
	 * @return array list of lines exported
	 */
	function export() {
		Billrun_Factory::dispatcher()->trigger('beforeExport', array($this));
		$this->beforeExport();
		//$className = $this->getGeneratorClassName();
		//$generatorOptions = $this->buildGeneratorOptions();
		//$this->fileGenerator = new $className($generatorOptions);
		$this->prepareDataToExport();//need to remove this
		$exportedData = $this->handleExport();//need to remove this and use the line bellow
		//$this->fileGenerator->generate();
		$this->afterExport();
		Billrun_Factory::dispatcher()->trigger('afterExport', array(&$exportedData, $this));
		return $exportedData;
	}
	
	/**
	 * gets record type according to configuration mapping
	 * 
	 * @return string
	 */
	protected function getRecordType($row) {
		foreach (Billrun_Util::getIn($this->config, 'record_type_mapping', array()) as $recordTypeMapping) {
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
		$fieldsMapping = Billrun_Util::getIn($this->config, array('fields_mapping', $recordType));
		$ret = $this->mapFields($fieldsMapping, $row);
		Billrun_Factory::dispatcher()->trigger('ExportAfterGetRecordData', array(&$row, &$ret, $this));
		return $ret;
	}
	
	/**
	 * checks if there is a header
	 * 
	 * @return boolean
	 */
	protected function hasHeader() {
		$headerMapping = Billrun_Util::getIn($this->config, 'header_mapping');
		return !empty($headerMapping);
	}

	/**
	 * checks if there is a footer
	 * 
	 * @return boolean
	 */
	protected function hasFooter() {
		$footerMapping = Billrun_Util::getIn($this->config, 'footer_mapping');
		return !empty($footerMapping);
	}
	
	/**
	 * loads the header data (first line in file)
	 * 
	 * @return array
	 */
	protected function loadHeader() {
		if (!$this->hasHeader()) {
			return false;
		}
		$headerMapping = Billrun_Util::getIn($this->config, 'header_mapping');
		$this->headerToExport = $this->mapFields($headerMapping);
		Billrun_Factory::dispatcher()->trigger('ExportAfterGetHeader', array(&$this->headerToExport, $this));
	}
	
	/**
	 * loads the footer data (last line in file)
	 * 
	 * @return array
	 */
	protected function loadFooter() {
		if (!$this->hasFooter()) {
			return false;
		}
		$footerMapping = Billrun_Util::getIn($this->config, 'footer_mapping');
		$this->footerToExport = $this->mapFields($footerMapping);
		Billrun_Factory::dispatcher()->trigger('ExportAfterGetFooter', array(&$this->headerToExport, $this));
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
			$this->rowsToExport[] = $this->getRecordData($rawRow);
			$data[] = $rawRow; //maybe - $this->getRecordData($rawRow);
		}
		Billrun_Factory::dispatcher()->trigger('ExportAfterLoadRows', array(&$this->rawRows, &$this->rowsToExport, $this));
		return $data;
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
	 * prepare the data to be exported
	 * 
	 * @return array
	 */
	protected function prepareDataToExport() {
		$this->loadRows();
		$this->loadHeader();//remove this
		$this->loadFooter();//remove this
	}
	
	public function mapFields($fieldsMapping, $row = array()) {
		$data = array();
		foreach ($fieldsMapping as $fieldMapping) {
			Billrun_Factory::dispatcher()->trigger('ExportBeforeMapField', array(&$row, &$fieldMapping, $this));
			$val = '';
			$fieldName = $fieldMapping['field_name'];
			$mapping = $fieldMapping['mapping'];
			if (!is_array($mapping)) {
				$val = Billrun_Util::getIn($row, $mapping, $mapping);
			} else if (isset($mapping['field'])) {
				$val = Billrun_Util::getIn($row, $mapping['field'], '');
			} else if(isset ($mapping['hard_coded'])) {
				$val = $mapping['hard_coded'];
			} else if (isset ($mapping['conditions'])) {
				$val = isset($mapping['default']) ? $mapping['default'] : '';
				foreach ($mapping['conditions'] as $condition) {
					if (Billrun_Util::isConditionMet($row, $condition)) {
						$val = $condition['result'];
						break;
					}
				}
			} else if (isset($mapping['func'])) {
				$funcName = $mapping['func']['func_name'];
				if (!method_exists($this, $funcName)) {
					Billrun_Log::getInstance()->log('Bulk exporter: mapping pre-defined function "' . $funcName . '" does not exist in class "' . $className . '"', Zend_log::WARN);
				} else {
					$val = $this->{$funcName}($row, $mapping);
				}
			} else {
				Billrun_Log::getInstance()->log('Bulk exporter: invalid mapping: ' . print_R($fieldMapping, 1), Zend_log::WARN);
			}
			Billrun_Factory::dispatcher()->trigger('ExportAfterMapField', array(&$row, &$fieldMapping, &$val, $this));
			
			if (!is_null($val)) {
				$val = self::formatMappingValue($val, $mapping);
				Billrun_Util::setIn($data, explode('>', $fieldName), $val);
			}
		}
		
		return $data;
	}
	
	protected function formatMappingValue($value, $mapping) {
		Billrun_Factory::dispatcher()->trigger('ExportBeforeFormatValue', array(&$value, $mapping, $this));
		if (isset($mapping['format']['regex'])) {
			$value = preg_replace($mapping['format']['regex'], '', $value);
		}
		if (isset($mapping['format']['date'])) {
			$value = $this->formatDate($value, $mapping);
		}
		if (isset($mapping['format']['number'])) {
			$value = $this->formatNumber($value, $mapping);
		}
		if (isset($mapping['padding'])) {
			$padding = Billrun_Util::getIn($mapping, 'padding.character', ' ');
			$length = Billrun_Util::getIn($mapping, 'padding.length', strlen($value));
			$padDirection = strtolower(Billrun_Util::getIn($mapping, 'padding.direction', 'left')) == 'right' ? STR_PAD_RIGHT : STR_PAD_LEFT;
			$value = str_pad($value, $length, $padding, $padDirection);
		}
		return $value;
	}
	
	protected function formatDate($date, $mapping) {
		if ($date instanceof MongoDate) {
			$date = $date->sec;
		} else if (is_string($date)) {
			$date = strtotime($date);
		}
		$dateFormat = Billrun_Util::getIn($mapping, 'format.date', 'YmdHis');
		return date($dateFormat, $date);
	}
	
	protected function formatNumber($number, $mapping) {
		$multiply = Billrun_Util::getIn($mapping, 'format.number.multiply', 1);
		$decimals = Billrun_Util::getIn($mapping, 'format.number.decimals', 0);
		$dec_point = Billrun_Util::getIn($mapping, 'format.number.dec_point', '.');
		$thousands_sep = Billrun_Util::getIn($mapping, 'format.number.thousands_sep', ',');
		return number_format(($number * $multiply), $decimals, $dec_point, $thousands_sep);
	}
	
	
	/** pre-defined functions start **/
	
	protected function callCustomFunction($row = array(), $mapping = array()) {
		$customFuncName = $mapping['func']['custom_func_name'];
		$ret = '';
		Billrun_Factory::dispatcher()->trigger($customFuncName, array($row, $mapping, &$ret));
		return $ret;
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
	
	protected function getTimeStamp($row = array(), $mapping = array()) {
		$format = Billrun_Util::getIn($mapping, 'func.date_format', Billrun_Util::getIn($mapping, 'format', 'YmdHis'));
		return date($format, $this->exportTime);
	}
	
	protected function getNumberOfRecords($row = array(), $mapping = array()) {
		$numberOfRecords = count($this->rowsToExport);
		$includeHeader = Billrun_Util::getIn($mapping, 'func.include_header', true);
		$includeFooter = Billrun_Util::getIn($mapping, 'func.include_footer', true);
		if ($includeHeader && $this->hasHeader()) {
			$numberOfRecords++;
		}
		if ($includeFooter && $this->hasFooter()) {
			$numberOfRecords++;
		}
		return $this->formatMappingValue($numberOfRecords, $mapping);
	}
	
	protected function sumField($row = array(), $mapping = array()) {
		$sum = 0;
		$fieldName = Billrun_Util::getIn($mapping, 'func.field', '');
		foreach ($this->rowsToExport as $i => $rowToExport) {
			$value = Billrun_Util::getIn($rowToExport, $fieldName, Billrun_Util::getIn($this->rawRows[$i], $fieldName, 0));
			$sum += $value;
		}
		
		return $this->formatMappingValue($sum, $mapping);
	}
	
	/** pre-defined functions end **/
}
