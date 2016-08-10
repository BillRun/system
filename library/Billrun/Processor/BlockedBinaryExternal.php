<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This defines an empty processor that pass the processing action to extarnal plugin.
 */
class Billrun_Processor_BlockedBinaryExternal extends Billrun_Processor_Base_BlockedSeperatedBinary {

	static protected $type = 'blockedBinaryExternal';

	public function __construct($options = array()) {
		parent::__construct($options);
		if ($this->getType() == 'blockedBinaryExternal') {
			throw new Exception('Billrun_Processor_BlockedBinaryExternal::__construct : cannot run without specifing a specific type.');
		}
	}

	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log('Resource is not configured well', Zend_Log::ERR);
			return false;
		}
		return Billrun_Factory::chain()->trigger('processData', array($this->getType(), $this->fileHandler, &$this));
	}

	protected function processFinished() {
		return Billrun_Factory::chain()->trigger('isProcessingFinished', array($this->getType(), $this->fileHandler, &$this));
	}

	/**
	 * @see Billrun_Processor::getSequenceData
	 */
	public function getFilenameData($filename) {
		return Billrun_Factory::chain()->trigger('getFilenameData', array($this->getType(), $filename, &$this));
	}

	protected function getLineVolume($row) {
		return Billrun_Factory::chain()->trigger('getLineVolume', $row);
	}

	protected function getLineUsageType($row) {
		return Billrun_Factory::chain()->trigger('getLineVolume', $row);
	}

}
