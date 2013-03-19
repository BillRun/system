<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of BinaryExternal
 *
 * @author eran
 */
class Billrun_Processor_BinaryExternal extends Billrun_Processor_Base_Binary {
	
	static protected $type = 'binaryExternal';
	
		public function __construct($options = array()) {
		parent::__construct($options);
		if($this->getType() == 'binaryExternal') {
			throw new Exception('Billrun_Processor_BinaryExternal::__construct : cannot run without specifing a specific type.');
		}
	}
	
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			$this->log->log('Resource is not configured well', Zend_Log::ERR);
			return false;
		}

		return $this->chain->trigger('processData',array($this->getType(), $this->fileHandler, &$this));		
	}

	/**
	 * @see Billrun_Processor::getSequenceData
	 */
	public function getSequenceData($filename) {
		return $this->chain->trigger('getSequenceData',array($this->getType(), $filename, &$this));
	}
	
	
}
