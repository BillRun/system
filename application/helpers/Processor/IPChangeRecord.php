<?php
/**
 * @package	Billing
 * @copyright	Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license	GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * 
 * IPChangeRecord
 * @package  Application
 * @subpackage Plugins
 * @since    5.8
 */
class Processor_IPChangeRecord extends Billrun_Processor
{

	static protected $type = 'ip_change_records';

	protected $data_structure = [
							[
								'match'=> '^\w+  \d+ \d+:\d+:\d+ [\d\w\.]+',
								'fields' => [
												'recording_entity' => '^\w+  \d+ \d+:\d+:\d+ ([\d\w\.]+)',
												'datetime' => "^\w+  \d+ \d+:\d+:\d+ [\d\w\.]+  \w (\d{4} \w{3,5} \d{1,2} \d{1,2}:\d{1,2}:\d{1,2})",
												'nat_type' => "^\w+  \d+ \d+:\d+:\d+ [\d\w\.]+  \w [ \d\w]+ - - ([\d\w]+) ",
												'changes' => "(\[Userbased[^]]+\])"
								]
							],
						];


	public function __construct(array $options)
	{
	    parent::__construct($options);
	    if(!empty($options['regexes']) || !empty($options['parser']['regexes'])) {
			$this->data_structure = Billrun_Util::getFieldVal($options['regexes'],Billrun_Util::getFieldVal($options['parser']['regexes'],$this->data_structure));
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
			$mergedRows = $this->buildData($line);

			$explodedRows = $this->explodeIPChanges($mergedRows);
			foreach($explodedRows as $row) {
				if ($this->isValidDataRecord($row)) {
					$this->data['data'][] = $row;
				} else {
					Billrun_Factory::log("invalid record :".json_encode($row),Zend_Log::WARN);
				}
			}
		}
		return true;
	}

	protected function buildData($line, $line_number = null) {
		$this->parser->setStructure($this->data_structure); // for the next iteration
		$this->parser->setLine($line);
		// @todo: trigger after row load (including $header, $row)
		$row = $this->filterFields($this->parser->parse());
		// @todo: trigger after row parse (including $header, $row)
		$row['source'] = self::$type;
		$row['type'] = static::$type;
		$row['log_stamp'] = $this->getFileStamp();
		$row['file'] = basename($this->filePath);
		$row['process_time'] = date(self::base_dateformat);
		$date = DateTime::createFromFormat("Y M j H:i:s",$row['datetime']);
		$row['urt']= new MongoDate( $date->getTimestamp()  );
		if ($this->line_numbers) {
			$row['line_number'] = $this->current_line;
		}
		return $row;
	}

	protected function isValidDataRecord($row) {
		return true;
	}

	protected function explodeIPChanges($mergedRow,$fieldToExplode = ["changes" => ['separator' => ' ' , 'fields' => [ 'wtf1','internal_ip','network','external_ip','start_port','end_port' ], 'transfoms' => [ '\[' => '' , '\]' => '', '- ' => '']]]) {
		$rowBase = $mergedRow;
		foreach(array_keys($fieldToExplode) as $exField) {
			unset($rowBase[$exField]);
		}
		$rows =[];
		foreach($fieldToExplode as $exField => $translationRules) {
			foreach($mergedRow[$exField] as $subRow) {
				foreach($translationRules['transfoms'] as  $transRegex => $transValue) {
					$subRow = preg_replace('/'.$transRegex.'/', $transValue, $subRow);
				}
				$change = array_combine($translationRules['fields'] ,explode( $translationRules['separator'],	rtrim($subRow, "{$translationRules['separator']}\t\n\r\0\x0B"))) ;
				$row = array_merge( $rowBase, $change	);
				$row['stamp'] = Billrun_Util::generateArrayStamp([$change,$row['stamp']]);
				$rows[] = $row;
			}
		}

		return $rows;
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
	 * (@TODO  duplicate of Billrun_Processor_Base_Binary::filterFields merge both of them to the processor  when  time  are less daire!)
	 * filter the record row data fields from the records
	 * (The required field can be written in the config using <type>.fields_filter)
	 * @param Array		$rawRow the full data record row.
	 * @return Array	the record row with filtered only the requierd fields in it
	 * 					or if no filter is defined in the configuration the full data record.
	 */
	protected function filterFields($rawRow) {
		$stdFields = array('stamp');
		$row = array();

		$requiredFields = Billrun_Factory::config()->getConfigValue(static::$type . '.fields_filter', array(), 'array');
		if (!empty($requiredFields)) {
			$passThruFields = array_merge($requiredFields, $stdFields);
			foreach ($passThruFields as $field) {
				if (isset($rawRow[$field])) {
						$row[$field] = $rawRow[$field];
				}

			}
		} else {
			return $rawRow;
		}

		return $row;
	}

}
