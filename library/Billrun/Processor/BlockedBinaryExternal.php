<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of BlockedBinaryExternal
 *
 * @author eran
 */
class Billrun_Processor_BlockedBinaryExternal extends Billrun_Processor_Base_BlockedSeperatedBinary
{	
	static protected $type = 'blockedBinaryExternal';

	protected function parse() {
			return $this->chain->trigger('processData',array($this->getType(), $this->fileHandler, &$this));
	}

	protected function processFinished() {
			return $this->chain->trigger('isProcessingFinished',array($this->getType(), $this->fileHandler, &$this));		
	}
}

?>
