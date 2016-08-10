<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing direct debit log file
 *
 * @package  LogFile
 * @since    1
 */
class Billrun_LogFile_DD extends Billrun_LogBillrunFile {

	/**
	 * source field of the log file
	 * @var string
	 */
	protected $source = 'dd';

	public function setSequenceNumber() {
		return $this->data->createAutoInc('seq');
	}

	public function getSequenceNumber() {
		if (isset($this->data['seq'])) {
			return $this->data['seq'];
		}
		return NULL;
	}

}
