<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Udata Generator class
 *
 * @package  Models
 * @since    4.0
 */
class Generator_Payments extends Billrun_Generator_ConfigurableCDRAggregationCsv {

	static $type = 'payments';

	public function generate() {
		$fileData = $this->getNextFileData();
		$this->writeRows();
		$this->logDB($fileData);
	}

	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);

		return array('seq' => $seq, 'filename' => 'Brun_PS_' . sprintf('%05.5d', $seq) . '_' . date('YmdHi',$this->startTime), 'source' => static::$type);
	}

	// ------------------------------------ Protected -----------------------------------------

	protected function getReportCandiateMatchQuery() {
		return array('urt' => array('$gt' => $this->getLastRunDate(static::$type)));
	}

	protected function getReportFilterMatchQuery() {
		return array();
	}

	// ------------------------------------ Helpers -----------------------------------------
	// 



	protected function isLineEligible($line) {
		return true;
	}

	/**
	 * Get subscriber ID for refund transactions
	 * @param type $value
	 * @param type $parameters
	 * @param type $line
	 * @return type
	 */
	function getSubscriberForRefund($value, $parameters, $line) {
		if (/* empty($value) && */!empty($line['refund_trans_id_1'])) {
			$orgTrans = $this->collection->query(array('transaction_id' => $line['refund_trans_id_1']))->cursor()->limit(1)->current();
			if (!empty($orgTrans) && !$orgTrans->isEmpty()) {
				$value = $orgTrans['sid'];
			}
		}
		return $value;
	}

}
