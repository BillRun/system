<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * A Class to aggregate sequence data and check it`s validity.
 *
 */
class Billrun_Common_SequenceChecker {

	protected $sequence = array();

	public function addSequence($seq, $data) {
		$this->sequence[intval($seq, 10)] = $data;
	}

	public function isSequenceValid() {
		$highSeq = $lowSeq = false;
		ksort($this->sequence);
		foreach ($this->sequence as $seq => $data) {
			if ($lowSeq === false || $lowSeq > $seq) {
				$lowSeq = $seq;
			}
			if ($highSeq === false || $highSeq < $seq) {
				$highSeq = $seq;
			}
		}

		return (count($this->sequence) <= 1 || count($this->sequence) - 1 == $highSeq - $lowSeq);
	}

	public function __get($name) {
		return $this->{$name};
	}

}

?>
