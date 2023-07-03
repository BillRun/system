<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2023 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing ExternalPricing Csv generator class
 * require to generate csvs for comparison with older billing systems / charge using credit guard
 *
 * @todo this class should inherit from abstract class Generator_Golan
 * @package  Billing
 * @since    0.5
 */
class Generator_ExternalPricing  extends Billrun_Generator {


	protected $validFuncs = ['intval','floatval','date','sprintf'];

	 /**
     * Data structure for mapping CDR fields to CSV file columns.
     *
     * @var array
     */
	protected $dataStructure = [];

	public function __construct(array $options) {
	    parent::__construct($options);

		// load file Structure
		if( ($configFile = Billrun_Factory::config()->getConfigValue(static::type.'.generator.structure_config','')) &&
			file_exists($configFile)) {
			$this->dataStructure = Yaf_Config_Ini();
		}
	}


	/**
	 * Loads the relevant CDRs from the external pricing queue.
	 */
	public function load() {
	    //query queue for CDRs  in external pricing stage  that  were  not  sent to pricing yet
		$queueLines = Billrun_Factory::db()->queueCollection()->query(['external_pricing'=>'waiting','generated'=>['$exists'=>false,'$ne'=>true]])
												->cursor()->sort(['urt'=>-1])->limit($this->limit ? $this->limit : 10000);
		$this->stamps = array_map(function ($q){
			return $q['stamp'];
		},iterator_to_array($queueLines));
		//load  the  relevant CDRs from lines
		$this->data = Billrun_Factory::db()->linesCollection()->query(['stamp'=> ['$in'=>$this->stamps]]);
	}

	/**
	 * Generates the CSV file.
	 *
	 * @return bool True if generation was successful, false otherwise.
	 */
	public function generate() {
		$generatedData = [];
		$generatedData[] = $this->getHeader($this->dataStructure['header']);
		//for each  line
		foreach($this->data as $row) {
			$generatedData[] = $this->getDataLine($row, $this->dataStructure['data']);
		}

		$generatedData[] =  $this->getFooter($this->dataStructure['footer']);

		if(!$this->write()) {
			Billrun_Factory::log('Failed to write the external pricing file.', Zend_Log::ERR);
			return false;
		}

		if( $this->markQueueLines($this->stamps) ) {
				Billrun_Factory::log('Failed to mark all queue lines as generated', Zend_Log::ERR);
				return false;
		}

		return true;
	}


	/**
	 * Generates the header line for the CSV file.
	 *
	 * @param array $headerStruct Structure configuration for the header line.
	 * @return string The generated header line.
	 */
	protected function getHeader($headerStruct = []) {
		return date('YmdHis');
	}
    /**
     * Generates a data line for the CSV file based on a CDR row.
     *
     * @param array $row The CDR row.
     * @param array $dataStruct Structure configuration for the data line.
     * @return string The generated data line.
     */
	protected function getDataLine($row , $dataStruct = []) {
		return $this->exractFields($row, $dataStruct);
	}

    /**
     * Generates the footer line for the CSV file.
     *
     * @param array $footerStruct Structure configuration for the footer line.
     * @return string The generated footer line.
     */
	protected function getFooter($footerStruct = []) {
		return date('YmdHis');
	}

    /**
     * Extracts fields from a CDR row based on the data structure configuration.
     *
     * @param array $row The CDR row.
     * @param array $dataStruct Structure configuration for the data line.
     * @return array The extracted fields.
     */
	protected function exractFields($row, $dataStruct = []) {
		$retRow = [];
		foreach($dataStruct as $srcField => $mapping) {
			if(is_array($mapping) && !empty($mapping['func']) ) {
				if(in_array($mapping['func'],$this->validFuncs)) {
					$args = empty($mapping['arguments']) ? [] : $mapping['arguments'];
					$args[] = ($row[$srcField] instanceOf MongoDate ? $row[$srcField]->sec : $row[$srcField]);
					$retRow[$mapping['field_name']]=call_user_func_array($mapping['func'],$args);
				} else {
					Billrun_Factory::log("Function ${mapping['func']} is not valid for csv generation",Zend_Log::WARN);
				}
			} else {
				$retRow[$mapping] = $row[$srcField];
			}
		}

		return $retRow;
	}

    /**
     * Writes the generated data to the CSV file.
     *
     * @param array $data The generated data to be written.
     * @return bool True if writing was successful, false otherwise.
     */
	protected function write($data) {
		$fullPath =  $this->getFullFilePath();

		if(!( $fd =  fopen($fullPath,'w'))) {
			Billrun_Factory::log("Failed to write to file : ${fullPath}", Zend_Log::ERR);
			return false;
		}

		$ret =  fputcsv($fd, $data) !== false;
		fclose($fd);

		return  $ret;
	}

	/**
	 * Marks the queue lines as generated to indicate that they have been processed.
	 *
	 * @param array $stamps The stamps of the queue lines to be marked.
	 * @return bool True if marking was successful, false otherwise.
	 */
	protected function markQueueLines($stamps) {
		// Update the queue collection to mark the queue lines as generated
		$result = Billrun_Factory::db()->queueCollection()->update(
			['stamp' => ['$in' => $stamps]],
			['$set' => ['generated' => true]]
		);

		return  $result['ok'] == 1 && ($result['n'] === count($stamps)) ;
	}


	protected function getFullFilePath($fileData = false) {
		return $this->export_directory . PHP_DIRECTORY_SEPERATOR . $this->getFilename($fileData);
	}

	protected function  getFilename($fileData = false) {
		return 'temp.csv';
	}
}


