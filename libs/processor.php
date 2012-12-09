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
	 * the file handler to process on
	 * @var file handler
	 */
	protected $fileHandler;

	/**
	 * parser to processor the file
	 * @var processor class
	 */
	protected $parser = null;

	/**
	 * the container work on
	 * @var array
	 */
	protected $data = null;

	/**
	 * the database we are working on
	 * @var db resource
	 */
	protected $db = null;

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

		if (isset($options['db']))
		{
			$this->setDB($options['db']);
		}
	}

	public function setDB($db)
	{
		$this->db = $db;
	}

	public function getData()
	{
		return $this->data;
	}

	/**
	 * method to get the data from the file
	 * @todo take to parent abstract
	 */
	public function process()
	{

		// @todo: trigger before parse (including $ret)
		if (!$this->parse())
		{
			return false;
		}

		// @todo: trigger after parse line (including $ret)
		// @todo: trigger before storage line (including $ret)

		if (!$this->logDB())
		{
			//raise error
			return false;
		}

		if (!$this->store())
		{
			//raise error
			return false;
		}

		// @todo: trigger after storage line (including $ret)

		return true;
	}

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