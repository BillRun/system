<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Description of BlockedSeperatedFile
 *
 * @author eran
 */
abstract class Billrun_Processor_Base_BlockedSeperatedBinary extends Billrun_Processor_Base_Binary {

	public function process() {
		if ($this->isQueueFull()) {
			Billrun_Factory::log()->log("Billrun_Processor_Base_BlockedSeperatedBinary: queue size is too big", Zend_Log::ALERT);
			return FALSE;
		} else {
			// run all over the file with the parser helper
			if (!is_resource($this->fileHandler)) {
				Billrun_Factory::log()->log("Resource is not configured well", Zend_Log::WARN);
				return false;
			}
			$this->data['trailer'] = array();
			$this->data['header'] = $this->buildHeader(false);

			Billrun_Factory::dispatcher()->trigger('beforeProcessorParsing', array($this));

			while (!$this->processFinished()) {
				if ($this->parse() === FALSE) {
					Billrun_Factory::log()->log("Billrun_Processor: cannot parse", Zend_Log::ERR);
					return false;
				}
			}

			$this->data['trailer'] = $this->buildTrailer($this->data['trailer']);

			Billrun_Factory::dispatcher()->trigger('afterProcessorParsing', array($this));
			$this->prepareQueue();
			Billrun_Factory::dispatcher()->trigger('beforeProcessorStore', array($this));

			if ($this->store() === FALSE) {
				Billrun_Factory::log()->log("Billrun_Processor: cannot store the parser lines", Zend_Log::ERR);
				return false;
			}

			if ($this->logDB() === FALSE) {
				Billrun_Factory::log()->log("Billrun_Processor: cannot log parsing action", Zend_Log::WARN);
			}

			Billrun_Factory::dispatcher()->trigger('afterProcessorStore', array($this));

			$this->removefromWorkspace($this->getFileStamp());
			Billrun_Factory::dispatcher()->trigger('afterProcessorRemove', array($this));
			
			return count($this->data['data']);
		}
	}

	abstract protected function processFinished();
}

?>
