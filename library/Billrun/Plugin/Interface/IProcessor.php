<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This interface defines the interface needed to add processor behavior to a plugin.
 */
interface Billrun_Plugin_Interface_IProcessor {

	/**
	 * Process data from a file.
	 * @param type $type the type of file to process
	 * @param type $fileHandle the file handle
	 * @param Billrun_Processor $processor the processor object that managing the file processing
	 */
	public function processData($type, $fileHandle, Billrun_Processor &$processor);

	/**
	 * Check if thers more  processing to be done.
	 * @param type $type  type of file being processed
	 * @param type $fileHandle the processed file handle
	 * @param Billrun_Processor $processor the processor object that managing the file processing
	 */
	public function isProcessingFinished($type, $fileHandle, Billrun_Processor &$processor);

	/**
	 * Retrive the sequence data for a filename
	 * @param type $type the type of the file being processed
	 * @param type $filename the file name of the file being processed
	 * @param type $processor the processor instace that triggered the fuction
	 * @return array containing the file sequence data or false if there was an error.
	 */
	public function getFilenameData($type, $filename, &$processor);
}
