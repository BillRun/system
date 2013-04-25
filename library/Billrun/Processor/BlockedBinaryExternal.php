<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */


/**
 * This defines an empty processor that pass the processing action to extarnal plugin.
 */
class Billrun_Processor_BlockedBinaryExternal extends Billrun_Processor_Base_BlockedSeperatedBinary
{	
	static protected $type = 'blockedBinaryExternal';

	public function __construct($options = array()) {
		parent::__construct($options);
		if($this->getType() == 'blockedBinaryExternal') {
			throw new Exception('Billrun_Processor_BlockedBinaryExternal::__construct : cannot run without specifing a specific type.');
		}
	}
	
	protected function parse() {
			if (!is_resource($this->fileHandler)) {
				Billrun_Factory::log()->log('Resource is not configured well', Zend_Log::ERR);
				return false;
			}
			$this->markStartProcessing();
			return Billrun_Factory::chain()->trigger('processData',array($this->getType(), $this->fileHandler, &$this));
	}

	protected function processFinished() {
			return Billrun_Factory::chain()->trigger('isProcessingFinished',array($this->getType(), $this->fileHandler, &$this));		
	}
	
	/**
	 * @see Billrun_Processor::getSequenceData
	 */
	public function getFilenameData($filename) {
		return Billrun_Factory::chain()->trigger('getFilenameData',array($this->getType(), $filename, &$this));		
	}
	
	/**
	 * mark the current log line as being processed
	 */
	protected function markStartProcessing() {
		$current_stamp = $this->getStamp(); // mongo id in new version; else string
		if ($current_stamp instanceof Mongodloid_Entity || $current_stamp instanceof Mongodloid_Id) {
			$resource = Billrun_Factory::db()->logCollection()->findOne($current_stamp);		
			$resource->set('start_process_time', new MongoDate(time()));
			return $resource->save(Billrun_Factory::db()->logCollection(), true);
		}
	}
	
}

