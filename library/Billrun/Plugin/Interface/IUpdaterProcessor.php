<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This interface defines the interface needed to add update processor behavior to a plugin.
 */
interface Billrun_Plugin_Interface_IUpdaterProcessor extends Billrun_Plugin_Interface_IProcessor {

	/**
	 * Update data using the processed data
	 * @param string $type the type of the file being processed
	 * @param string $filename the filename of the file being processed
	 * @param Billrun_Processor_Updater $processor the processor instace that triggered the function
	 */
	public function updateData($type, $filename, Billrun_Processor_Updater &$processor);
}
