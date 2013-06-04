<?php

/**
 * @category   Application
 * @package    Helpers
 * @subpackage Processor
 * @copyright  Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Billing processor for import data into rates collection
 *
 * @package    Processor
 * @subpackage ImportZones
 * @since      1.0
 */
class Processor_Importintnetworkmappings extends Billrun_Processor_Base_Separator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'importintnetworkmappings';

	public function __construct($options) {
		parent::__construct($options);
		$header = $this->getLine();
		$this->parser->setStructure($header);
	}

	/**
	 * we do not need to log
	 * 
	 * @return boolean true
	 */
	public function logDB() {
		return TRUE;
	}

	/**
	 * method to parse the data
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log()->log("Resource is not configured well", Zend_Log::ERR);
			return false;
		}

		while ($line = $this->getLine()) {
			$this->parseData($line);
		}

		return true;
	}

	/**
	 * method to parse data
	 * 
	 * @param array $line data line
	 * 
	 * @return array the data array
	 */
	protected function parseData($line) {

		$this->parser->setLine($line);
		Billrun_Factory::dispatcher()->trigger('beforeDataParsing', array(&$line, $this));
		$parsed_row = $this->parser->parse();
		$row = array();
		$row['PLMN'] = $parsed_row['PLMN'];
		$row['name'] = $parsed_row['mnName'];
		$row['type']['call'] = $parsed_row['mnAccessType'];
		$row['type']['callback'] = $parsed_row['mnAccessTypeForCallback'];
		$row['type']['incoming_call'] = $parsed_row['mnAccessTypeForInCall'];
		$row['type']['data'] = $parsed_row['accessTypeForData'];
		$row['type']['sms'] = $parsed_row['mnAccessTypeForSms'];		
		$row['comment'] = $parsed_row['mnComment'];

		Billrun_Factory::dispatcher()->trigger('afterDataParsing', array(&$row, $this));
		$this->data['data'][] = $row;
		return $row;
	}

	protected function store() {
		if (!isset($this->data['data'])) {
			// raise error
			return false;
		}

		$int_network_mappings = Billrun_Factory::db()->intnetworkmappingsCollection();

		$this->data['stored_data'] = array();

		foreach ($this->data['data'] as $key => $row) {
			$entity = new Mongodloid_Entity($row);

			if ($int_network_mappings->query('PLMN', $row['PLMN'])->count() > 0) {
				continue;
			}

			$entity->save($int_network_mappings, true);
			$this->data['stored_data'][] = $row;
		}
		return true;
	}

}
