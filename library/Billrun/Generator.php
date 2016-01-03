<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract generator class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Generator extends Billrun_Base {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'generator';

	/**
	 * the directory where the generator store files
	 * @var string
	 */
	protected $export_directory;

	/**
	 * load balanced between db primary and secondary
	 * @var int
	 */
	protected $loadBalanced = 0;

	/**
	 * whether to auto create the export directory
	 * @var boolean 
	 */
	protected $auto_create_dir = true;

	/**
	 * constructor
	 * 
	 * @param array $options parameters for the generator to dynamically behaiour
	 */
	public function __construct($options) {

		parent::__construct($options);

		if (isset($options['export_directory'])) {
			if (!isset($options['disable_stamp_export_directory']) || !$options['disable_stamp_export_directory']) {
				$this->export_directory = $options['export_directory'] . DIRECTORY_SEPARATOR . $this->stamp;
			} else {
				$this->export_directory = $options['export_directory'];
			}
		} else {
			$this->export_directory = Billrun_Factory::config()->getConfigValue(static::$type . '.export') . DIRECTORY_SEPARATOR . $this->stamp; //__DIR__ . '/../files/';
		}

		if (isset($options['auto_create_dir'])) {
			$this->auto_create_dir = $options['auto_create_dir'];
		}

		$this->loadBalanced = Billrun_Factory::config()->getConfigValue('generate.loadBalanced', 0);

		if ($this->auto_create_dir && !file_exists($this->export_directory)) {
			mkdir($this->export_directory, 0777, true);
			chmod($this->export_directory, 0777);
		}
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
