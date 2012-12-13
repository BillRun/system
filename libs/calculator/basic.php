<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'calculator.php';

/**
 * Billing basic calculator class
 *
 * @package  calculator
 * @since    1.0
 */
abstract class calculator_basic
{

	/**
	 * the database we are working on
	 * @var db resource
	 */
	protected $db = null;

	/**
	 * the container the calculator work on
	 * @var array
	 */
	protected $data = array();

	/**
	 * the type of the calculator
	 * @var string
	 */
	protected $type = 'basic';

	/**
	 * constants of tables
	 */

	const log_table = 'log';
	const lines_table = 'lines';

	/**
	 * constructor
	 * @param array $options
	 */
	public function __construct($options)
	{
		if ($options['db'])
		{
			$this->setDB($options['db']);
		}
	}

	public function setDB($db)
	{
		$this->db = $db;
	}

	/**
	 * load the data to calculate
	 */
	public function load($initData = true)
	{
		$lines = $this->db->getCollection(self::lines_table);

		// @todo refactoring query to be able to extend
//		$customer_query = "{'price_customer':{\$exists:false}}";
//		$provider_query = "{'price_provider':{\$exists:false}}";
//		$query = "{\$or: [" . $customer_query . ", " . $provider_query . "]}";
//		$query = "price_customer NOT EXISTS or price_provider NOT EXISTS";

		if ($initData)
		{
			$this->data = array();
		}

		$resource = $lines->query()
			->notExists('price_customer');
//			->notExists('price_provider'); // @todo: check how to do or between 2 not exists

		foreach ($resource as $entity)
		{
			$this->data[] = $entity;
		}

		print "entities loaded: " . count($this->data) . PHP_EOL;
	}

	/**
	 * write the calculation into DB
	 */
	abstract protected function updateRow($row);

	/**
	 * identify if the row belong to calculator
	 * @return boolean true if the row identify as belonging to the calculator, else false
	 */
	protected function identify($row)
	{
		return true;
	}

	static public function getInstance()
	{
		$args = func_get_args();
		if (!is_array($args))
		{
			$type = $args[0];
			$args = array();
		}
		else
		{
			$type = $args[0]['type'];
			unset($args[0]['type']);
			$args = $args[0];
		}

		$file_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('_', '/', $type) . '.php';

		if (!file_exists($file_path))
		{
			print "calculator file doesn't exists: " . $file_path . PHP_EOL;
			return false;
		}

		require_once $file_path;
		$class = 'calculator_' . $type;

		if (!class_exists($class))
		{
			// @todo raise an error
			print "calculator class doesn't exists: " . $class;
			return false;
		}

		$instance = new $class($args);
		return $instance;
	}

}