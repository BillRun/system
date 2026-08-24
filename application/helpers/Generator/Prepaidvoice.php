<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Udata Generator class
 *
 * @package  Models
 * @since    4.0
 */
class Generator_Prepaidvoice extends Billrun_Generator_ConfigurableCDRAggregationCsv {

	static $type = 'prepaidvoice';
	protected $startEndWindow = 12800;

	public function __construct($options) {
		parent::__construct($options);
		$this->startEndWindow = Billrun_Factory::config()->getConfigValue(static::$type . '.generator.start_end_window', $this->startEndWindow);
	}

	public function generate() {
		$fileData = $this->getNextFileData();
		$this->writeRows();
		$this->logDB($fileData);
	}

	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);

		return array('seq' => $seq, 'filename' => 'Brun_PN_' . sprintf('%05.5d', $seq) . '_' . date('YmdHi',$this->startTime), 'source' => static::$type);
	}

	//--------------------------------------------  Protected ------------------------------------------------

	protected function getReportCandiateMatchQuery() {
		return array('$and' => array(
				array('$or' => array(
						array('urt' => array('$gt' => new Mongodloid_Date($this->getLastRunDate(static::$type)->sec - $this->startEndWindow)), 'record_type' => array('$ne' => 'release_call')),
						array('urt' => array('$gt' => $this->getLastRunDate(static::$type)))
					))
			)
		);
	}

	protected function getReportFilterMatchQuery() {
		return array('disconnect_time' => array('$lt' => new Mongodloid_Date($this->startTime), '$gte' => $this->getLastRunDate(static::$type)));
	}

	// ------------------------------------ Helpers -----------------------------------------
	// 


	protected function isLineEligible($line) {
		return true;
	}

	protected function transalteDuration($value, $parameters, $line) {
		return date($parameters['date_format'], $line[$parameters['end_field']]->sec - $line[$parameters['start_field']]->sec);
	}

}
