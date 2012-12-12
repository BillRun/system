<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing interface aggregator class
 *
 * @package  calculator
 * @since    1.0
 */
abstract class aggregator
{

	/**
	 * the database we are working on
	 * @var db resource
	 */
	protected $db = null;

	/**
	 * the stamp of the aggregator
	 * used for mark the aggregation
	 * @var db resource
	 */
	protected $stamp = null;

	/**
	 * constants of tables
	 */

	const log_table = 'log';
	const lines_table = 'lines';
	const billrun_table = 'billrun';

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
	 * execute aggregate
	 */
	abstract public function aggregate();

	/**
	 * load the data to aggregate
	 */
	abstract public function load();

	abstract protected function updateBillingLine($subscriber_id, $item);

	abstract protected function updateBillrun($billrun, $row);

	protected function loadSubscriber($phone_number, $time)
	{
		return new stdClass();
	}

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

		$file_path = __DIR__ . DIRECTORY_SEPARATOR . 'aggregator' . DIRECTORY_SEPARATOR . $type . '.php';

		if (!file_exists($file_path))
		{
			// @todo raise an error
			return false;
		}

		require_once $file_path;
		$class = 'aggregator_' . $type;

		if (!class_exists($class))
		{
			// @todo raise an error
			return false;
		}

		return new $class($args);
	}
	
	abstract protected function save($data);

}