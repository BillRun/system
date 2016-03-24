<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Udata Generator class
 *
 * @package  Models
 * @since    2.1
 */

class Generator_Prepaidsubscribers extends Billrun_Generator_ConfigurableCDRAggregationCsv {

	
	static $type = 'prepaidsubscribers';
	
	public function generate() {
		$fileData = $this->getNextFileData();
		$this->writeRows();
		$this->logDB($fileData);
	}
	
	
	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);
		
		return array('seq'=> $seq , 'filename' => 'PREPAID_SUBSCRIBERS_'.date('YmdHi').".dat", 'source' => static::$type);
	}
	
	//--------------------------------------------  Protected ------------------------------------------------
	
	protected function writeRows() {
		if(!empty($this->headers)) {
			$this->writeHeaders();
		}
		foreach($this->data as $line) {
			if($this->isLineEligible($line)) {
				$this->writeRowToFile($this->translateCdrFields($line, $this->translations), $this->fieldDefinitions);
			}			
		}
		$this->markFileAsDone();
	}

	
	protected function getReportCandiateMatchQuery() {
		return array();
	}

	protected function getReportFilterMatchQuery() {
		return array();
	}
	
	protected function isLineEligible($line) {
		return true;
	}	
	
	// ------------------------------------ Helpers -----------------------------------------
	// 
	
	protected function flattenArray($array, $parameters, &$line) {
		foreach($array as $idx => $val) {
			foreach($parameters['mapping'] as $dataKey => $lineKey) {
				$fieldValue = Billrun_Util::getNestedArrayVal($val,$dataKey);
				if(!empty($fieldValue)) {
					$line[sprintf($lineKey, $idx+1)] = $fieldValue;
				}
			}
		}
		return $array;
	}
	
	
	protected function lastSidTransactionDate($value, $parameters, $line) {
		$usage = Billrun_Factory::db()->linesCollection()->query(array_merge(array('sid'=>$value),$parameters['query']))->cursor()->sort(array('urt'=>-1))->limit(1)->current();
		if(!$usage->isEmpty()) {
			return $this->translateUrt($usage['urt'], $parameters);
		}
	}
	

}
