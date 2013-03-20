<?php

/**
 * This interface defines the interface needed to add processor behavior to a plugin.
 * @author eran
 */
interface  Billrun_Plugin_Interface_IProcessor {
	/**
	 * 
	 * @param type $type
	 * @param type $fileHandle
	 * @param Billrun_Processor $processor
	 */
	public function processData($type, $fileHandle, Billrun_Processor &$processor);
	/**
	 * 
	 * @param type $type
	 * @param type $fileHandle
	 * @param Billrun_Processor $processor
	 */
	public function isProcessingFinished($type, $fileHandle, Billrun_Processor &$processor);
	/**
	 * Retrive the sequence data for a filename
	 * @param type $type the type of the file being processed
	 * @param type $filename the file name of the file being processed
	 * @param type $processor the processor instace that triggered the fuction
	 * @return array containing the file sequence data or false if there was an error.
	 */
	public function getSequenceData($type, $filename, &$processor);
}

?>
