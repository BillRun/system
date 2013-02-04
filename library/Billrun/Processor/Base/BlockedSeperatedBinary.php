<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of BlockedSeperatedFile
 *
 * @author eran
 */
abstract  class Billrun_Processor_Base_BlockedSeperatedBinary extends Billrun_Processor_Base_Binary {
	
	protected $byteData = null;
	
	public function process() {
		$this->dispatcher->trigger('beforeProcessorParsing', array($this));

		while(!$this->processFinished()) {
			$this->data['data'] = array();
			$this->data['header'] = array();
			$this->data['trailer'] = array();
			
			if ($this->parse() === FALSE) {
				$this->log->log("Billrun_Processor: cannot parse", Zend_Log::ERR);
				return false;
			}

			$this->dispatcher->trigger('afterProcessorParsing', array($this));

			if ($this->logDB() === FALSE) {
				$this->log->log("Billrun_Processor: cannot log parsing action", Zend_Log::WARN);
			}

			$this->dispatcher->trigger('beforeProcessorStore', array($this));

			if ($this->store() === FALSE) {
				$this->log->log("Billrun_Processor: cannot store the parser lines", Zend_Log::ERR);
				return false;
			}
		}	
		$this->dispatcher->trigger('afterProcessorStore', array($this));

		return $this->data['data'];
	}
	
	abstract protected function processFinished();
	
}

?>
