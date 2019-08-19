<?php
/**
 * @package	Billing
 * @copyright	Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license	GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * 
 * IPChangeRecord  IP NAT mapping  processor
 * (NOTICE : this processor DOES NOT save any of the lines/CDRs  to the queue)
 * (NOTICE : this processor save the CDRs/lines to the ipmapping collection)

 * @package  Application
 * @subpackage Plugins
 * @since    5.8
 */
class Processor_IPChangeRecord extends Billrun_Processor
{

	static protected $type = 'ip_change_records';

	protected $data_structure = [
								'match'=> '/^\w+  \d+ \d+:\d+:\d+ [\d\w\.]+.*NAT/',
								'fields' => [
												'recording_entity' => '/^\w+  \d+ \d+:\d+:\d+ ([\d\w\.]+)/',
												'datetime' => "/^\w+  \d+ \d+:\d+:\d+ [\d\w\.]+  \w (\d{4} \w{3,5} \d{1,2} \d{1,2}:\d{1,2}:\d{1,2})/",
												'nat_type' => "/^\w+  \d+ \d+:\d+:\d+ [\d\w\.]+  \w [ \d\w:]+ - - ([\d\w]+) /",
												'changes' => "/(\[\w+ [^]]+\])/"
								],
						];


	public function __construct(array $options)
	{
	    parent::__construct($options);
	    if(!empty(Billrun_Factory::config()->getConfigValue($this->getType() . '.config_path',''))) {
			$this->loadConfig(Billrun_Factory::config()->getConfigValue($this->getType() . '.config_path'));
		}
	    if(!empty($options['data_structure']) || !empty($options['parser']['data_structure'])) {
			$this->data_structure = Billrun_Util::getFieldVal($options['data_structure'],Billrun_Util::getFieldVal($options['parser']['data_structure'],$this->data_structure));
	    }
	}


	public function process()
	{
		$this->data['header'] = $this->getFileLogData($this->filename,static::$type);
	    $ret = parent::process();
	    return $ret;
	}


	/**
	 * method to parse the data
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log()->log("Resource is not configured well", Zend_Log::ERR);
			return false;
		}

		while ($line = $this->fgetsIncrementLine($this->fileHandler)) {
			try {
				$mergedRows = $this->buildData($line);

				$explodedRows = $this->explodeIPChanges($mergedRows,$this->data_structure['sub_records']);
				foreach($explodedRows as $row) {
					if ($this->isValidDataRecord($row)) {
						if(!$this->shouldFilterOutRecrod($row)) {
							$this->data['data'][] = $this->filterFieldsByValue($this->filterFields($row));
						}
					} else {
						Billrun_Factory::log("invalid record :".json_encode($row),Zend_Log::WARN);
					}
				}
			} catch(Throwable $tr) {
				Billrun_Factory::log("Crashed on invalid record : {$line}",Zend_Log::WARN);
				throw new Exception("Crashed on invalid record : {$line}");
			}
		}
		return true;
	}

	protected function buildData($line, $line_number = null) {
		$this->parser->setStructure($this->data_structure); // for the next iteration
		$this->parser->setLine($line);
		// @todo: trigger after row load (including $header, $row)
		$row = $this->filterFields($this->parser->parse());
		if($row == FALSE) {
			return FALSE;
		}
		// @todo: trigger after row parse (including $header, $row)
		$row['source'] = self::$type;
		$row['type'] = static::$type;
		$row['log_stamp'] = $this->getFileStamp();
		$row['file'] = basename($this->filePath);
		$row['process_time'] = date(self::base_dateformat);
		$date = DateTime::createFromFormat(	Billrun_Util::getFieldVal($this->configStruct['config']['date_format'],"Y M j H:i:s"),
											$row['datetime'],
											new DateTimeZone(Billrun_Util::getFieldVal($this->configStruct['config']['timezone'],date_default_timezone_get())) );
		$row['urt']= new MongoDate( $date->getTimestamp()  );
		if ($this->line_numbers) {
			$row['line_number'] = $this->current_line;
		}
		return $row;
	}

	protected function isValidDataRecord($row) {
		$valid = true;
		foreach(Billrun_Util::getFieldVal($this->configStruct['config']['valid_data_line'],[]) as $fieldKey => $regCheck) {
			$valid &= preg_match($regCheck,$row[$fieldKey]);
		}
		return $valid;
	}

	protected function shouldFilterOutRecrod($row) {
		$filterOut = !empty(Billrun_Util::getFieldVal($this->configStruct['config']['change_type_to_filter_out'],[]));
		foreach(Billrun_Util::getFieldVal($this->configStruct['config']['change_type_to_filter_out'],[]) as $fieldKey => $regCheck) {
			$filterOut &= preg_match($regCheck,$row[$fieldKey]);
		}
		// not filter-in so filter the row out if all the condition matched
		return $filterOut;
	}


	protected function explodeIPChanges($mergedRow,$fieldToExplode = FALSE) {
		if(!$fieldToExplode) {
			return $mergedRow;
		}

		$rowBase = $mergedRow;
		foreach(array_keys($fieldToExplode) as $exField) {
			unset($rowBase[$exField]);
		}
		$rows =[];
		foreach($fieldToExplode as $exField => $translationRules) {
			if(!empty($mergedRow[$exField])) {
				$fieldArr = is_array($mergedRow[$exField]) ? $mergedRow[$exField] : [$mergedRow[$exField]];
				foreach($fieldArr as $subRow) {
					foreach($translationRules['transfoms'] as  $transRegex => $transValue) {
						$subRow = preg_replace($transRegex, $transValue, $subRow);
					}
					$change = array_combine($translationRules['fields'] ,explode( $translationRules['separator'],	rtrim($subRow, "{$translationRules['separator']}\t\n\r\0\x0B"))) ;
					$row = array_merge( $rowBase, $change	);
					$row['stamp'] = Billrun_Util::generateArrayStamp([$change,$row['stamp']]);
					$rows[] = $row;
				}
			}
		}

		return $rows;
	}

	/**
	 * method to store the processing data
	 * (NOTICE : this processor DOES NOT save any of the lines/CDRs  to the queue)
	 * (NOTICE : this processor save the CDRs/lines to the ipmapping collection)
	 * @todo refactoring this method
	 */
	protected function store() {
		if (!isset($this->data['data'])) {
			// raise error
			Billrun_Factory::log()->log('Got empty data from file  : ' . basename($this->filePath) , Zend_Log::ERR);
			return false;
		}

		$lines = Billrun_Factory::db()->ipmappingCollection();
		Billrun_Factory::log()->log("Store data of file " . basename($this->filePath) . " with " . count($this->data['data']) . " lines", Zend_Log::INFO);
		if ($this->bulkInsert) {
			settype($this->bulkInsert, 'int');
			if (!$this->bulkAddToCollection($lines)) {
				return false;
			}
		} else {
			$this->addToCollection($lines);
		}

		Billrun_Factory::log()->log("Finished storing data of file " . basename($this->filePath), Zend_Log::INFO);
		return true;
	}

	/**
	 * Filter out row fields  by thier data/value based on the the configuration.
	 */
	protected function filterFieldsByValue($rawRow) {
		$row = $rawRow;

		$valuesToFilter = Billrun_Util::getFieldVal($this->configStruct['config']['values_to_filter_out'],[]);
		if (!empty($valuesToFilter)) {
			foreach ($valuesToFilter as $valueToFilter) {
				foreach($rawRow as $key => $rowValue) {
					 if( $rowValue === $valueToFilter && isset($row[$key])) {
						unset($row[$key]);
					 }
				}
			}
		}

		return $row;
	}

	protected function loadConfig($path) {
	    parent::loadConfig($path);
	    if(!empty($this->configStruct['data'])) {
			$this->data_structure= $this->configStruct['data'];
		}
	}


}
