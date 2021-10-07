<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Date implements Mongodloid_TypeInterface{

	private $_mongoDate;
	private $_stringDate;
	public $sec;
	public $usec;

	public function __toString() {
		return $this->_stringDate;
	}

	public function toDateTime() {
		return $this->_mongoDate->toDateTime();
	}

	public function __construct($sec = 0, $usec = 0) {
		if (func_num_args() == 0) {
            $time = microtime(true);
            $sec = floor($time);
            $usec = ($time - $sec) * 1000000.0;
        } elseif ($sec instanceof MongoDB\BSON\UTCDatetime) {
            $msecString = (string) $sec;

            $sec = substr($msecString, 0, -3);
            $usec = ((int) substr($msecString, -3)) * 1000;
        }
		
        $this->sec = (int) $sec;
        $this->usec = (int) $this->truncateMicroSeconds($usec);
		$milliSeconds = ($this->sec * 1000) + ($this->truncateMicroSeconds($this->usec) / 1000);
		$this->_mongoDate = new MongoDB\BSON\UTCDatetime($milliSeconds);
		$this->_stringDate = $this->_mongoDate->__toString();
	}
	
	/**
     * @param int $usec
     * @return int
     */
    private function truncateMicroSeconds($usec)
    {
        return (int) floor($usec / 1000) * 1000;
    }
	
	/**
     * Converts this Mongodloid_Date to the new BSON date type
     *
     * @return MongoDB\BSON\UTCDatetime
     */
    public function toBSONType()
    {
        return $this->_mongoDate;
    }

}