<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing generator Golan016 class
 *
 * @package  Billing
 * @since    1.0
 */
class Generator_Golan016 extends Billrun_Generator_Csv_Fixed {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = '016';

	/**
	 *  File name of the generated file
	 * 
	 * @var string
	 */
	static protected $fileName = '';

	/**
	 * @object logEntity
	 */
	protected $logEntity;

	/**
	 *  Disable stamp export directory
	 * 
	 * @var boolean
	 */
	protected $disable_stamp_export_directory = true;

	/* data structure
	 * @var array
	 */
	protected $data = array();

	public function __construct($options = array()) {

		$options['export_directory'] = Billrun_Factory::config()->getConfigValue('016.export');

		if ($this->disable_stamp_export_directory) {
			$options['disable_stamp_export_directory'] = $this->disable_stamp_export_directory;
		}

		parent::__construct($options);
	}

	/*
	 * return the data structure
	 */

	public function dataStructure() {
		return array(
			'records_type' => 3,
			'calling_number' => 15,
			'call_start_time' => 13,
			'call_end_time' => 13,
			'called_number' => 18,
			'is_in_glti' => 1,
			'prepaid' => 1,
			'duration' => 10,
			'sampleDurationInSec' => 8,
			'charge' => 10,
			'origin_carrier' => 10,
			'origin_file_name' => 100,
		);
	}

	/**
	 * @see Billrun_Calculator::load
	 */
	public function load() {
		$this->data = array();

		$log_coll = Billrun_Factory::db()->logCollection();
		$lines = Billrun_Factory::db()->linesCollection();

		$log = $log_coll->query(array(
					'source' => '016',
					'generated' => array('$exists' => false),
				))->cursor()->current();

		if ($log->isEmpty()) {
			Billrun_Factory::log()->log("No file to generate", Zend_Log::INFO);
			return FALSE;
		}

		$this->logEntity = $log;

		$lines_arr = $lines->query()
				->equals('source', 'ilds')
				->equals('type', '016')
				->exists('price_customer')
				->equals('file', $log['file_name']);

		foreach ($lines_arr as $entity) {

			$this->data[] = $entity;

			if (empty(self::$fileName) && !empty($entity['file'])) {
				self::$fileName = $entity['file'];
			}
		}
	}

	/*
	 * set stamp on the generated file on log collecton
	 */

	public function setGeneratedStamp() {

		$log = Billrun_Factory::db()->logCollection();
		$logEntity = $this->logEntity;
		$current_row = $logEntity->getRawData();

		$added_values = array(
			'generated' => $current_row['file_name'],
		);

		$newData = array_merge($current_row, $added_values);
		$logEntity->setRawData($newData);
		$logEntity->save($log);
	}

	/**
	 * @see Billrun_Generator_Csv::createTreatedFile
	 */
	public function createTreatedFile($xmlContent) {
		if (empty(self::$fileName)) {
			Billrun_Factory::log()->log("file name is empty, cannot generate the file:" . self::$fileName, Zend_Log::ERR);
			return FALSE;
		}

		$path = $this->export_directory . DIRECTORY_SEPARATOR . '/' . substr(self::$fileName, 0, strpos(self::$fileName, '.new')) . '.out';

		if (file_put_contents($path, $xmlContent)) {
			return self::$fileName;
		}

		Billrun_Factory::log()->log("cannot put content of file: " . self::$fileName . "path: " . $path, Zend_Log::ERR);
		return FALSE;
	}

}
