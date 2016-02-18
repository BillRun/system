<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Importer Cards class
 *
 * @package  Billrun
 * @since    4.0
 */
class Billrun_Importer_Cards extends Billrun_Importer_Csv {
	
	// TODO: Move the field columns to the 
	// importer_csv and add a 'get fields' abstract function.
	protected $fields = null;
	
	public function __construct($options) {
		parent::__construct($options);
		$this->fields = Billrun_Factory::config()->getConfigValue('importer.Cards.fields', array());
	}
	
	protected function getCollectionName() {
		return 'cards';
	}

	protected function getSecret($rowData) {
		$formatted = number_format($rowData[$this->fields['secret']], 0, '', '');
		$codeLength = Billrun_Factory::config()->getConfigValue('importer.Cards.code_length');
		$padded = str_pad($formatted, $codeLength, "0", STR_PAD_LEFT);
		$secret = hash('sha512',$formatted);
		return $secret;
	}
	
	protected function getFrom($rowData) {
		$from = new MongoDate();
		return $from;
	}
	
	protected function getSerial($rowData) {
		$serial = (int)$rowData[$this->fields['serial_number']];
		return $serial;
	}
	
	protected function getBatch($rowData) {
		$batch = (int)$rowData[$this->fields['batch_number']];
		return $batch;
	}
	
	protected function getTo($rowData) {
		$to = new MongoDate(strtotime($rowData[$this->fields['to']]));
		return $to;
	}
	
	protected function getCreationTime($rowData) {
		$currentTime = date('m/d/Y h:i:s a', time());
		$creationTime = new MongoDate(strtotime($currentTime));
		return $creationTime;
	}
	
}