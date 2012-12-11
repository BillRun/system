<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract generator ilds class
 * require to generate xml for each account 
 * require to generate csv contain how much to credit each account
 *
 * @package  Billing
 * @since    1.0
 */
class generator_ilds extends generator
{

	/**
	 * load the container the need to be generate
	 */
	public function load($initData = true)
	{
		$billrun = $this->db->getCollection(self::billrun_table);
		$query = "stamp = " . $this->getStamp();
		if ($initData)
		{
			$this->data = array();
		}

		$resource = $billrun->query($query);

		foreach ($resource as $entity)
		{
			$this->data[] = $entity;
		}

		print "aggregator entities loaded: " . count($this->data) . PHP_EOL;

	}

	/**
	 * execute the generate action
	 */
	public function generate()
	{
		// load each billrun subsriber
		// generate xml
		// generate csv
	}

	protected function xml()
	{
		
	}

	protected function csv()
	{
		
	}
}