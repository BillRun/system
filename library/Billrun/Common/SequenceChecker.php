<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * A Class to aggregate sequence data and check it`s validity.
 *
 * @author eran
 */
class Billrun_Common_SequenceChecker {

	protected $sequence = array();

	public function addSequence($seq, $data) {
		$this->sequence[intval($seq, 10)] = $data;
		ksort($this->sequence);
	}

	/**
	 * Check if the  current sequence is valid
	 * @return type
	 */
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
	
	/**
	 * Get the first item in the sequence.
	 * @return mixed  the first item in the saved sequence, null if the sequence is empty.
	 */
	public function getFirst() {		
		ksort($this->sequence);		
		return isset($this->sequence[key($this->sequence)]) ?  $this->sequence[key($this->sequence)] : null ;
	}

	/**
	 * get the ordered sequence that  was created
	 * @return array containing the  sequence  keyed  by the sequence key
	 */
	public function getSequence() {
		ksort($this->sequence);
		return $this->sequence;
	}
	
	/**
	 * Get the last item in the sequence.
	 * @return mixed  the last item in the saved sequence, null if the sequence is empty.
	 */
	public function getLast() {		
		ksort($this->sequence);
		$keys = array_keys($this->sequence);
		return isset($this->sequence[$keys[count($keys)-1]]) ?  $this->sequence[$keys[count($keys)-1]] : null;

	}
		
	public function __get($name) {
		return $this->{$name};
	}

}

?>
