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

class Generator_Prepaidvoice extends Billrun_Generator_ConfigurableCDRAggregationCsv {

	
	static $type = 'prepaidvoice';
	

	public function __construct($options) {
		parent::__construct($options);		
	}
	
	public function generate() {
		$fileData = $this->getNextFileData();
		$this->writeRows();
		$this->logDB($fileData);
	}
	
	public function getNextFileData() {
		$lastFile = Billrun_Factory::db()->logCollection()->query(array('source'=>static::$type))->cursor()->sort(array('seq'=>-1))->limit(1)->current();
		$seq = empty($lastFile['seq']) ? 0 : $lastFile['seq'];
		$seq++;
		
		return array('seq'=> $seq , 'filename' => 'Brun_PN_'.sprintf('%05d',$seq).'_'.date('YmdHis'), 'source' => static::$type);
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
	
	protected function transalteDuration($value, $parameters, $line) {
		return date($parameters['date_format'],$line[$parameters['end_field']]->sec - $line[$parameters['start_field']]->sec);
	}

}