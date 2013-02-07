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
	
	public function process() {
		
		// run all over the file with the parser helper
		if (!is_resource($this->fileHandler)) {
			$this->log->log("Resource is not configured well", Zend_Log::ERR);
			return false;
		}
		
		$this->data['header'] = $this->buildHeader(false);
			
		$this->dispatcher->trigger('beforeProcessorParsing', array($this));
			
		while(!$this->processFinished()) {
			if ($this->parse() === FALSE) {
				$this->log->log("Billrun_Processor: cannot parse", Zend_Log::ERR);
				return false;
			}
		}			
		$this->data['trailer'] = array_merge(	$this->buildTrailer(false),
												array(
													'lines_stamp' => md5(serialize($this->data['data'])),
													'lines_count' => count($this->data['data'])
													));
		$this->dispatcher->trigger('afterProcessorParsing', array($this));

		if ($this->logDB() === FALSE) {
			$this->log->log("Billrun_Processor: cannot log parsing action", Zend_Log::WARN);
		}

		$this->dispatcher->trigger('beforeProcessorStore', array($this));

		if ($this->store() === FALSE) {
			$this->log->log("Billrun_Processor: cannot store the parser lines", Zend_Log::ERR);
			return false;
		}
		$this->dispatcher->trigger('afterProcessorStore', array($this));

		return $this->data['data'];
	}
	
	abstract protected function processFinished();
	
}

?>
