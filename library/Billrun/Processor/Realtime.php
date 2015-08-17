<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing realtime processor class
 *
 * @package  Billing
 * @since    4.0
 */
class Billrun_Processor_Realtime extends Billrun_Processor {

	static protected $type = 'realtime';

	/**
	 * override abstract method
	 * @return true
	 */
	public function parse() {
		return true;
	}

	/**
	 * process the data
	 * @return boolean
	 */
	public function processData() {
		parent::processData();
		foreach ($this->data['data'] as &$row) {
			$row['urt'] = new MongoDate($row['urt']['sec']);
		}
		return true;
	}

	public function process() {
		Billrun_Factory::dispatcher()->trigger('beforeProcessorParsing', array($this));

		if ($this->parse() === FALSE) {
			Billrun_Factory::log()->log("Billrun_Processor: cannot parse " . $this->filePath, Zend_Log::ERR);
			return FALSE;
		}

		Billrun_Factory::dispatcher()->trigger('afterProcessorParsing', array($this));
//		no need to queue because it's real-time; 
//		@TODO: check this with future spec
//		$this->prepareQueue();
		Billrun_Factory::dispatcher()->trigger('beforeProcessorStore', array($this));

		if ($this->store() === FALSE) {
			Billrun_Factory::log()->log("Billrun_Processor: cannot store the parser lines " . $this->filePath, Zend_Log::ERR);
			return FALSE;
		}

		Billrun_Factory::dispatcher()->trigger('afterProcessorStore', array($this));

		//$this->removefromWorkspace($this->getFileStamp());
		Billrun_Factory::dispatcher()->trigger('afterProcessorRemove', array($this));
		return count($this->data['data']);
	}

}
