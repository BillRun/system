<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing basic abstract class
 *
 * @package  base
 * @since    1.0
 */
abstract class base {

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
	 * constant of log collection name
	 */
	const log_table = 'log';

	/**
	 * constant of lines collection name
	 */
	const lines_table = 'lines';

	/**
	 * constant of billrun collection name
	 */
	const billrun_table = 'billrun';

	/**
	 * constructor
	 * @param array $options
	 */
	public function __construct($options)
	{
		if (isset($options['db']))
		{
			$this->setDB($options['db']);
		}

		if (isset($options['stamp']) && $options['stamp']) {
			$this->setStamp($options['stamp']);
		} else {
			$this->setStamp(uniqid(get_class($this)));
		}

	}

	/**
	 * set database of the basic object
	 * @param resource $db
	 */
	public function setDB($db)
	{
		$this->db = $db;
	}

	/**
	 * set stamp of the basic object
	 * used for unique object actions
	 *
	 * @param string $stamp
	 */
	public function setStamp($stamp)
	{
		$this->stamp = $stamp;
	}


	/**
	 * get stamp of the basic object
	 * used for unique object actions
	 *
	 * @return string the stamp of the object
	 */
	public function getStamp()
	{
		return $this->stamp;
	}


}