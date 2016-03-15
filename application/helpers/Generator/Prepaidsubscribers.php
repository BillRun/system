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
		
		return array('seq'=> $seq , 'filename' => 'PREPAID_SUBSCRIBERS_'.date('YmdHi').".csv", 'source' => static::$type);
	}
	
	//--------------------------------------------  Protected ------------------------------------------------
	
	protected function writeRows() {
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

}
