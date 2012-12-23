<?php

/**
 * @package			Billing Mongo DB
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */
require_once 'Collection.php';

class Mongodloid_DB
{

	private $_db;
	private $_connection;
	private $_collections = array();

	public function getCollection($name)
	{
		if (!isset($this->_collections[$name]) || !$this->_collections[$name])
			$this->_collections[$name] = new Mongodloid_Collection($this->_db->selectCollection($name), $this);

		return $this->_collections[$name];
	}

	public function getName()
	{
		return (string) $this->_db;
	}

	public function __construct(MongoDb $db, Mongodloid_Connection $connection)
	{
		$this->_db = $db;
		$this->_connection = $connection;
	}

}