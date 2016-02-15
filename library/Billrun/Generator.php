<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract generator class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class Billrun_Generator extends Billrun_Base {

	/**
	 * the directory where the generator store files
	 * @var string
	 */
	protected $export_directory;
	protected $csvContent = '';
	protected $csvPath;

	/**
	 * constructor
	 * 
	 * @param array $options parameters for the generator to dynamically behaiour
	 */
	public function __construct($options) {

		parent::__construct($options);

		if (isset($options['export_directory'])) {
			$this->export_directory = $options['export_directory'];
		} else {
			$this->export_directory = Billrun_Factory::config()->getConfigValue('ilds.export'); //__DIR__ . '/../files/';
		}

		if (isset($options['csv_filename'])) {
			$this->csvPath = $this->export_directory . '/' . $options['csv_filename'] . '.csv';
		} else {
			$today = date("Ymd");
			$this->csvPath = $this->export_directory . '/'. 'ilds' . $this->getStamp() .'_'. $today . '.csv';
		}

		$this->loadCsv();
	}

	/**
	 * load csv file to write the generating info into
	 */
	protected function loadCsv() {
		if (file_exists($this->csvPath)) {
			$this->csvContent = file_get_contents($this->csvPath);
		}
	}

	/**
	 * write row to csv file for generating info into in
	 * 
	 * @param string $row the row to write into
	 * 
	 * @return boolean true if succes to write info else false
	 */
	protected function csv($row) {
		return file_put_contents($this->csvPath, $row, FILE_APPEND);
	}

	/**
	 * load the container the need to be generate
	 */
	abstract public function load();

	/**
	 * execute the generate action
	 */
	abstract public function generate();
}