<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
	protected $fieldsColumns = null;

	public function __construct($options) {
		parent::__construct($options);
		$this->fieldsColumns = Billrun_Factory::config()->getConfigValue('importer.Cards.columns', array());
	}

	protected function getCollectionName() {
		return 'cards';
	}

	protected function getSecret($rowData) {
		$formatted = number_format($rowData[$this->fieldsColumns['secret']], 0, '', '');
		$codeLength = Billrun_Factory::config()->getConfigValue('importer.Cards.code_length');
		$padded = str_pad($formatted, $codeLength, "0", STR_PAD_LEFT);
		$secret = hash('sha512', $padded);
		return $secret;
	}

	protected function getFrom($rowData) {
		$from = new Mongodloid_Date();
		return $from;
	}

	protected function getSerial($rowData) {
		$serial = (int) $rowData[$this->fieldsColumns['serial_number']];
		return $serial;
	}

	protected function getBatch($rowData) {
		$batch = (int) $rowData[$this->fieldsColumns['batch_number']];
		return $batch;
	}

	protected function getTo($rowData) {
		$to = new Mongodloid_Date(strtotime($rowData[$this->fieldsColumns['to']]));
		return $to;
	}

	protected function getCreationTime($rowData) {
		$currentTime = date('m/d/Y h:i:s a', time());
		$creationTime = new Mongodloid_Date(strtotime($currentTime));
		return $creationTime;
	}

}
