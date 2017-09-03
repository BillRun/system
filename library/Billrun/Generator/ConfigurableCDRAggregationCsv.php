<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Udata Generator class
 *
 * @package  Models
 * @since    4.0
 */
abstract class Billrun_Generator_ConfigurableCDRAggregationCsv extends Billrun_Generator_AggregatedCsv {

	use Billrun_Traits_FileActions;

	protected $data = null;
	protected $grouping = array();
	protected $match = array();
	protected $translations = array();
	protected $fieldDefinitions = array();
	protected $preProject = array();
	protected $prePipeline = array();
	protected $postPipeline = array();
	protected $tmpFileIndicator = ".tmp";
	protected $legitimateFileExtension = "";
	protected $startTime = 0;
	protected $reportLinesStamps = array();

	public function __construct($options) {

		$this->startTime = time();
		$this->db = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue(Billrun_Factory::config()->getConfigValue(static::$type . '.generator.db', 'archive.db'), array()));

		//Load added configuration for the current action type. TODO move this to Billrun_Base Class
		foreach (Billrun_Factory::config()->getConfigValue(static::$type . '.generator.configuration.include', array()) as $path) {
			Billrun_Factory::config()->addConfig($path);
		}

		$config = Billrun_Factory::config()->getConfigValue(static::$type . '.generator', array());

		foreach ($config['match'] as $idx => $query) {
			foreach ($query as $key => $val) {
				$this->match['$or'][$idx][$key] = json_decode($val, JSON_OBJECT_AS_ARRAY);
			}
		}
		$this->match = array_merge($this->match, $this->getReportCandiateMatchQuery());

		$this->grouping = array('_id' => array());
		$this->grouping['_id'] = array_merge($this->grouping['_id'], $this->translateJSONConfig($config['grouping']));
		$this->grouping = array_merge($this->grouping, $this->translateJSONConfig($config['mapping']));

		foreach (Billrun_Util::getFieldVal($config['helpers'], array()) as $key => $mapping) {
			$mapArr = json_decode($mapping, JSON_OBJECT_AS_ARRAY);
			if (!empty($mapArr)) {
				$this->grouping[$key] = $mapArr;
			}
		}

		$this->fieldDefinitions = $this->translateJSONConfig(Billrun_Util::getFieldVal($config['field_definitions'], array()));
		$this->translations = $this->translateJSONConfig(Billrun_Util::getFieldVal($config['translations'], array()));
		$this->preProject = $this->translateJSONConfig(Billrun_Util::getFieldVal($config['pre_project'], array()));
		$this->prePipeline = $this->translateJSONConfig(Billrun_Util::getFieldVal($config['pre_pipeline'], ''));
		$this->postPipeline = $this->translateJSONConfig(Billrun_Util::getFieldVal($config['post_pipeline'], ''));
		$this->separator = $this->translateJSONConfig(Billrun_Util::getFieldVal($config['separator'], ''));
		$this->tmpFileIndicator = $this->translateJSONConfig(Billrun_Util::getFieldVal($config['temporary_file_indicator'], $this->tmpFileIndicator));
		$this->legitimateFileExtension = $this->translateJSONConfig(Billrun_Util::getFieldVal($config['file_extension'], $this->legitimateFileExtension));

		if (Billrun_Util::getFieldVal($config['include_headers'], FALSE)) {
			$this->headers = array_keys($this->fieldDefinitions);
		}

		if (empty($options['limit'])) {
			$options['limit'] = (int) Billrun_Util::getFieldVal($config['limit'], $this->limit);
		}

		if (empty($options['export_directory']) && !empty($config['export'])) {
			$options['export_directory'] = $config['export'];
			$options['disable_stamp_export_directory'] = true;
		}

                $this->loadServiceProviders();
                
		parent::__construct($options);
	}

	/**
	 * 
	 * @return type
	 */
	protected function buildAggregationQuery() {
		$collName = Billrun_Factory::config()->getConfigValue(static::$type . '.generator.collection', 'archive') . 'Collection';
		$fields = array();
		//sample 100 lines  and get all the  fields  from these lines.
		$fieldExamples = $this->db->{$collName}()->query($this->match)->cursor()->limit(100);
		foreach ($fieldExamples as $doc) {
			foreach ($doc->getRawData() as $key => $val) {
				$fields[$key] = 1;
			}
		}

		if (!empty($fields)) {
			$this->aggregation_array = array(
				array('$match' => $this->match),
				array('$project' => array_merge($fields, $this->preProject)),
			);

			if (!empty($this->prePipeline)) {
				$this->aggregation_array = array_merge($this->aggregation_array, $this->prePipeline);
			}
			$this->aggregation_array[] = array('$limit' => $this->limit);
			$this->aggregation_array[] = array('$sort' => array('urt' => 1));
			$this->aggregation_array[] = array('$group' => $this->grouping);
			if (!empty($this->getReportFilterMatchQuery())) {
				$this->aggregation_array[] = array('$match' => $this->getReportFilterMatchQuery());
			}
			if (!empty($this->postPipeline)) {
				$this->aggregation_array = array_merge($this->aggregation_array, $this->postPipeline);
			}
		} else {
			$this->aggregation_array = array(array('$match' => $this->match),
				array('$sort' => array('urt' => 1)),
				array('$group' => $this->grouping)
			);
		}

		//Billrun_Factory::log(json_encode($this->aggregation_array));
	}

	/**
	 * 
	 */
	abstract public function getNextFileData();

	//--------------------------------------------  Protected ------------------------------------------------

	protected function writeRows() {
		if (!empty($this->headers)) {
			$this->writeHeaders();
		}
		foreach ($this->data as $line) {
			if ($this->isLineEligible($line)) {
				$this->writeRowToFile($this->translateCdrFields($line, $this->translations), $this->fieldDefinitions);
			}
			//$this->markLines($line['stamps']);
		}
		$this->markFileAsDone();
	}

	protected function getLastRunDate($type) {
		$lastRun = $this->db->logCollection()->query(array('source' => $type,'type' => $type))->cursor()->sort(array('generated_time' => -1))->limit(1)->current();
		return empty($lastRun['generated_time']) || !($lastRun['generated_time'] instanceof MongoDate) ? new MongoDate(0) : $lastRun['generated_time'];
	}

	abstract protected function getReportCandiateMatchQuery();

	abstract protected function getReportFilterMatchQuery();

	/**
	 * 
	 */
	protected function setCollection() {
		$collName = Billrun_Factory::config()->getConfigValue(static::$type . '.generator.collection', 'archive') . 'Collection';
		$this->collection = $this->db->{$collName}();
	}

	protected function getNextSequenceData($type) {
		$lastFile = Billrun_Factory::db()->logCollection()->query(array('source' => $type,'type' => $type))->cursor()->sort(array('seq' => -1))->limit(1)->current();
		$seq = empty($lastFile['seq']) ? 0 : $lastFile['seq'];

		return ( ++$seq) % 10000;
	}

	/**
	 * 
	 * @param type $config
	 * @return type
	 */
	protected function translateJSONConfig($config) {
		$retConfig = $config;
		if (is_array($config)) {
			foreach ($config as $key => $mapping) {
				if (is_array($mapping)) {
					$retConfig[$key] = $this->translateJSONConfig($mapping);
				} else {
					$decodedJson = json_decode($mapping, JSON_OBJECT_AS_ARRAY);
					if (!empty($decodedJson)) {
						$retConfig[$key] = $decodedJson;
					} else if ($decodedJson !== null) {
						unset($retConfig[$key]);
					}
				}
			}
		} else {
			$decodedJson = json_decode($retConfig, JSON_OBJECT_AS_ARRAY);
			if (!empty($decodedJson)) {
				$retConfig = $decodedJson;
			}
		}
		return $retConfig;
	}

	/**
	 * 
	 * @param type $line
	 * @param type $translations
	 * @return type
	 */
	protected function translateCdrFields($line, $translations) {
		foreach ($translations as $key => $trans) {
			if (!isset($line[$key])) {
				$line[$key] = '';
			}
			switch ($trans['type']) {
				case 'function' :
					if (method_exists($this, $trans['translation']['function'])) {
						$line[$key] = $this->{$trans['translation']['function']}($line[$key], $this->translateJSONConfig(Billrun_Util::getFieldVal($trans['translation']['values'], array())), $line);
					} else if (function_exists($trans['translation']['function'])) {
						$line[$key] = call_user_func($trans['translation']['function'], $line[$key]);
					} else {
						Billrun_Factory::log("Couldn't translate field $key",Zend_Log::ERR);
					}
					break;
				case 'regex' :
				default :
					if (is_array($trans['translation']) && isset($trans['translation'][0])) {
						foreach ($trans['translation'] as $value) {
							$line[$key] = preg_replace(key($value), reset($value), $line[$key]);
						}
					} else {
						$line[$key] = preg_replace(key($trans['translation']), reset($trans['translation']), $line[$key]);
					}
					break;
			}
		}
		return $line;
	}

	protected function buildHeader() {
		
	}

	protected function setFilename() {
		$data = $this->getNextFileData();
		$this->filename = $data['filename'] . $this->tmpFileIndicator;
	}

	protected function markFileAsDone() {
		rename($this->file_path, preg_replace("/{$this->tmpFileIndicator}$/", $this->legitimateFileExtension, $this->file_path));
	}

	//----------- File handling functions ------------------

	/**
	 * 
	 * @param type $row
	 * @param type $fieldDefinitions
	 * @param type $fh
	 */
	protected function writeRowToFile($row, $fieldDefinitions) {
		$str = '';
		$empty = true;
		foreach ($fieldDefinitions as $field => $definition) {
			$fieldFormat = !empty($definition) ? $definition : '%s';
			$empty &= empty($row[$field]);
			$fieldStr = sprintf($fieldFormat, (isset($row[$field]) ? $row[$field] : ''));
			$str .= $fieldStr . $this->separator;
		}
		if (!$empty ) {
			if(!isset($this->reportLinesStamps[md5($str)])) {
				$this->writeToFile($str . PHP_EOL);
				$this->reportLinesStamps[md5($str)]=true;
			} else {
				Billrun_Factory::log('BIReport got a duplicate  line : ' . $str, Zend_Log::WARN);
			}
		} else {
			Billrun_Factory::log('BIReport got an empty line : ' . print_r($row, 1), Zend_Log::WARN);
		}
	}

	/**
	 * 
	 * @param type $fh
	 * @param type $str
	 */
	protected function writeToFile($str, $overwrite = false) {
		parent::writeToFile(mb_convert_encoding($str, "UTF-8", "HTML-ENTITIES"));
	}

	/**
	 * 
	 * @param type $stamps
	 */
	protected function markLines($stamps) {
		$query = array('stamp' => array('$in' => $stamps));
		$update = array('$set' => array('mediated.' . static::$type => new MongoDate()));
		try {
			$result = $this->collection->update($query, $update, array('multiple' => true));
		} catch (Exception $e) {
			#TODO : implement error handling
		}
	}

	/**
	 * method to log the processing
	 * 
	 * @todo refactoring this method
	 */
	protected function logDB($fileData) {
		Billrun_Factory::dispatcher()->trigger('beforeLogGeneratedFile', array(&$fileData, $this));

		$data = array(
			'stamp' => Billrun_Util::generateArrayStamp($fileData),
			'file_name' => $fileData['filename'],
			'seq' => $fileData['seq'],
			'source' => $fileData['source'],
			'type' => $fileData['source'],
			'received_hostname' => Billrun_Util::getHostName(),
			'received_time' => new MongoDate(),
			'generated_time' => new MongoDate($this->startTime),
			'direction' => 'out'
		);

		if (empty($data['stamp'])) {
			Billrun_Factory::log("Billrun_Receiver::logDB - got file with empty stamp :  {$data['stamp']}", Zend_Log::NOTICE);
			return FALSE;
		}

		try {
			$log = Billrun_Factory::db()->logCollection();
			$logLine = new Mongodloid_Entity($data);
			$result = $log->insert($logLine);

			if ($result['ok'] != 1) {
				Billrun_Factory::log("Billrun_Receiver::logDB - Failed when trying to update a file log record " . $data['file_name'] . " with stamp of : {$data['stamp']}", Zend_Log::NOTICE);
			}
		} catch (Exception $e) {
			//TODO : handle exceptions
		}

		Billrun_Factory::log("Billrun_Receiver::logDB - logged the generation of : " . $data['file_name'], Zend_Log::INFO);

		return $result['ok'] == 1;
	}
        
        protected function loadServiceProviders() {
            $serviceProviders = Billrun_Factory::db()->serviceprovidersCollection()->query()->cursor();
            foreach ($serviceProviders as $provider) {
                $this->serviceProviders[$provider['name']] = $provider->getRawData();
            }
        }

	//---------------------- Manage files/cdrs function ------------------------

	/**
	 * 
	 * @param type $param
	 * @return boolean
	 */
	protected function isLineEligible($line) {
		return true;
	}

	// ------------------------------------ Helpers -----------------------------------------
	// 

	/**
	 * 
	 * @param type $queries
	 * @param type $line
	 * @return boolean
	 */
	protected function fieldQueries($queries, $line) {
		foreach ($queries as $query) {
			$match = true;
			foreach ($query as $fieldKey => $regex) {
				$match &= is_string($line[$fieldKey]) && preg_match($regex, $line[$fieldKey]);
			}
			if ($match) {
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * 
	 * @param type $value
	 * @param type $dateFormat
	 * @return type
	 */
	protected function translateUrt($value, $parameters) {
		if (empty($value)) {
			return $value;
		}
		$dateFormat = is_array($parameters) ? $parameters['date_format'] : $parameters;
		$retDate = date($dateFormat, $value->sec);

		if (!empty($parameters['regex']) && is_array($parameters['regex'])) {
			foreach ($parameters['regex'] as $regex => $substitute) {
				$retDate = preg_replace($regex, $substitute, $retDate);
			}
		}

		return $retDate;
	}

	/**
	 * 
	 * @param type $value
	 * @param type $mapping
	 * @param type $line
	 * @return type
	 */
	protected function cdrQueryTranslations($value, $mapping, $line) {
		$retVal = $value;
		if (!empty($mapping)) {
			foreach ($mapping as $possibleRet => $queries) {
				if ($this->fieldQueries($queries, $line)) {
					$retVal = $possibleRet;
					break;
				}
			}
		}
		return $retVal;
	}

	protected function getPlanId($value, $parameters, $line) {
		$plan = Billrun_Factory::db()->plansCollection()->query(array('name' => $value))->cursor()->sort(array('urt' => -1))->limit(1)->current();
		if (!$plan->isEmpty()) {
			return $plan['external_id'];
		}
	}
        
        
        protected function getServiceProviderValues($value, $parameters, $line) {
            if(!isset($this->serviceProviders[$line[$parameters['key']]][$parameters['field']]) ) {
                Billrun_Factory::log("Couldn't Identify service provider {$line[$parameters['key']]}." , Zend_Log::WARN);
                return $line[$parameters['key']];
            }
            return $this->serviceProviders[$line[$parameters['key']]][$parameters['field']];		
	}

}
