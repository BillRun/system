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

class Generator_PrepaidRecharge extends Billrun_Generator_ConfigurableCDRAggregationCsv {

	
	static $type = 'prepaidrecharge';
	
	public function generate() {
		$fileData = $this->getNextFileData();
		$this->writeRows();
		$this->logDB($fileData);
	}
	
	
	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);
		
		return array('seq'=> $seq , 'filename' => 'PREPAID_RECHARGE_'.date('YmdHi').".csv", 'source' => static::$type);
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
			$this->markLines($line['stamps']);
		}
	}
	
	// ------------------------------------ Helpers -----------------------------------------
	// 
	
	
	protected function isLineEligible($line) {
		return true;
	}

	
	protected function getFromDBRef($dbRef, $parameters, &$line) {
		$entity =$this->collection->getRef($dbRef);
		if($entity && !$entity->isEmpty()) {
			return $entity[$parameters['field_name']];
		}
		return FALSE;
	}
	
	protected function getFromDBRefUrt($dbRef, $parameters, &$line) {
		$value = $this->getFromDBRef($dbRef, $parameters, $line);
		if(!empty($value)) {
			return $this->translateUrt($value, $parameters);
		}
	}
	
	protected function flattenArray($array, $parameters, &$line) {
		foreach($array as $idx => $val) {
			if($val instanceof MongoDBRef ) {
				$val = Billrun_DBRef::getEntity($val);
			}
			foreach($parameters['mapping'] as $dataKey => $lineKey) {
				$fieldValue = Billrun_Util::getNestedArrayVal($val,$dataKey);
				if(!empty($fieldValue)) {
					$line[sprintf($lineKey, $idx+1)] = $fieldValue;
				}
			}
		}
		return $array;
	}
}
