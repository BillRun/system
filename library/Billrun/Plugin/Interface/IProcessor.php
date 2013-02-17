<?php

/**
 * This interface defines the interface needed to add processor behavior to a plugin.
 * @author eran
 */
interface  Billrun_Plugin_Interface_IProcessor {
	public function processData($type, $fileHandle, Billrun_Processor &$processor);
	public function isProcessingFinished($type, $fileHandle, Billrun_Processor &$processor);
}

?>
