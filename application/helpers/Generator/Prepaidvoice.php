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

	
	public function generate() {
		$fileData = $this->getNextFileData();
		$this->writeRows();
		$this->logDB($fileData);
	}
	
	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);
		
		return array('seq'=> $seq , 'filename' => 'Brun_PN_'.sprintf('%05.5d',$seq).'_'.date('YmdHi'), 'source' => static::$type);
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