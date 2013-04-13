<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Description of BlockedSeperatedFile
 *
 * @author eran
 */
abstract  class Billrun_Processor_Base_BlockedSeperatedBinary extends Billrun_Processor_Base_Binary {
	
	public function process() {
		
		// run all over the file with the parser helper
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log()->log("Resource is not configured well", Zend_Log::ERR);
			return false;
		}
		$this->data['trailer'] = array();
		$this->data['header'] = $this->buildHeader(false);
		
		Billrun_Factory::dispatcher()->trigger('beforeProcessorParsing', array($this));
			
		while(!$this->processFinished()) {
			if ($this->parse() === FALSE) {
				Billrun_Factory::log()->log("Billrun_Processor: cannot parse", Zend_Log::ERR);
				return false;
			}
		}
		
		$this->data['trailer'] = $this->buildTrailer($this->data['trailer']);

		Billrun_Factory::dispatcher()->trigger('afterProcessorParsing', array($this));

		if ($this->logDB() === FALSE) {
			Billrun_Factory::log()->log("Billrun_Processor: cannot log parsing action", Zend_Log::WARN);
		}

		Billrun_Factory::dispatcher()->trigger('beforeProcessorStore', array($this));

		if ($this->store() === FALSE) {
			Billrun_Factory::log()->log("Billrun_Processor: cannot store the parser lines", Zend_Log::ERR);
			return false;
		}
		Billrun_Factory::dispatcher()->trigger('afterProcessorStore', array($this));

		$this->backup();
		
		Billrun_Factory::dispatcher()->trigger('afterProcessorBackup', array($this, &$this->filePath));
		
		return $this->data['data'];
	}
	
	abstract protected function processFinished();
	
}

?>
