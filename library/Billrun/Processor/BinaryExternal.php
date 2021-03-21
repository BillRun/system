<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * External binary processor
 *
 */
class Billrun_Processor_BinaryExternal extends Billrun_Processor_Base_Binary {

	static protected $type = 'binaryExternal';

	public function __construct($options = array()) {
		parent::__construct($options);
		if ($this->getType() == 'binaryExternal') {
			throw new Exception('Billrun_Processor_BinaryExternal::__construct : cannot run without specifing a specific type.');
		}
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
	
		/**
	 * method to run over all the files received which did not have been processed
	 */
	public function processLines() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log('Resource is not configured well', Zend_Log::ERR);
			return FALSE;
		}
		try {
			return Billrun_Factory::chain()->trigger('processData', array($this->getType(), $this->fileHandler, &$this));
		} catch (Exception $e) {
			Billrun_Factory::log("Got exception :" . $e->getMessage() . " while processing file {$this->filePath}", Zend_Log::ERR);
			return FALSE;
		}
	}

}
