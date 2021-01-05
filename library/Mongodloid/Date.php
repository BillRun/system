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

	public function setMongoDate(MongoDB\BSON\UTCDatetime $date) {
		$this->_mongoDate = $date;
		$this->_stringDate = $date->__toString();
		$msecString = $this->_stringDate;
		$sec = substr($msecString, 0, -3);
		$usec = ((int) substr($msecString, -3)) * 1000;
		$this->sec = (int) $sec;
        $this->usec = (int) $this->truncateMicroSeconds($usec);
	}

	public function __construct($date = null) {
		if ($date instanceOf MongoDB\BSON\UTCDatetime) {
			$this->setMongoDate($date);
		} else {
			$this->setMongoDate(new MongoDB\BSON\UTCDatetime($date));
		}
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
