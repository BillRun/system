<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract generator class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class generator
{

	/**
	 * constants of tables
	 */

	const log_table = 'log';
	const lines_table = 'lines';
	const billrun_table = 'billrun';

	/**
	 * the directory where the generator store files
	 * @var string
	 */
	protected $export_directory;
	
	public function __construct($options)
	{
		if ($options['db'])
		{
			$this->setDB($options['db']);
		}
		
		if (isset($options['export_directory'])) {
			$this->export_directory = $options['export_directory'];
		} else {
			$this->export_directory = __DIR__ . '/../files/';
		}
		
		if ($options['stamp'])
		{
			$this->setStamp($options['stamp']);
		}
		else
		{
			$this->setStamp(uniqid(get_class($this)));
		}

	}
	
	public function setDB($db)
	{
		$this->db = $db;
	}
	
	public function setStamp($stamp)
	{
		$this->stamp = $stamp;
	}

	public function getStamp()
	{
		return $this->stamp;
	}

	/**
	 * load the container the need to be generate
	 */
	abstract public function load($initData = true);

	/**
	 * execute the generate action
	 */
	abstract public function generate();

	static public function getInstance()
	{
		$args = func_get_args();
		if (!is_array($args))
		{
			$type = $args['type'];
			$args = array();
		}
		else
		{
			$type = $args[0]['type'];
			unset($args[0]['type']);
			$args = $args[0];
		}

		$file_path = __DIR__ . DIRECTORY_SEPARATOR . 'generator' . DIRECTORY_SEPARATOR . $type . '.php';

		if (!file_exists($file_path))
		{
			// @todo raise an error
			return false;
		}

		require_once $file_path;
		$class = 'generator_' . $type;

		if (!class_exists($class))
		{
			// @todo raise an error
			return false;
		}
		
		return new $class($args);
	}

}