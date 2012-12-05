<?php
/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract processor class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class processor
{

	/**
	 *
	 * @var file handler to proecess
	 */
	protected $fileHandler;

	/**
	 *
	 * @var parser to processor the file
	 */
	protected $parser = null;

	/**
	 *
	 * @var data container
	 */
	protected $data = null;
	
	/**
	 * constants of tables
	 */
	const log_table = 'log';
	const lines_table = 'lines';
	
	/**
	 * constructor - load basic options
	 * 
	 * @param array $options for the file processor
	 */
	public function __construct($options)
	{
		if (isset($options['file_path']))
		{
			$this->loadFile($options['file_path']);
		}

		if (isset($options['parser']))
		{
			$this->setParser($options['parser']);
		}
		
		if (isset($options['db'])) {
			$this->setDB($options['db']);
		}
	}
	
	public function setDB($db) {
		$this->db = $db;
	}

	public function getData() {
		return $this->data;
	}
	/**
	 * method to get the data from the file
	 */
	abstract public function process();

	/**
	 * method to load the data to the db
	 * @todo refactoring this method
	 */
	abstract protected function logDB();
	
	/**
	 * method to parse the data
	 */
	abstract protected function parse();

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

		$file_path = __DIR__ . DIRECTORY_SEPARATOR . 'processor' . DIRECTORY_SEPARATOR . $type . '.php';

		if (!file_exists($file_path))
		{
			// @todo raise an error
			return false;
		}

		require_once $file_path;
		$class = 'processor_' . $type;

		if (!class_exists($class))
		{
			// @todo raise an error
			return false;
		}

		return new $class($args);
	}

	public function loadFile($file_path)
	{
		if (file_exists($file_path))
		{
			$this->fileHandler = fopen($file_path, 'r');
		}
		else
		{
			// log file not exists
		}
	}

	public function setParser($parser)
	{
		$this->parser = $parser;
	}

}