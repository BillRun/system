<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Date {

	private $_mongoDate;
	private $_stringDate;

	public function __toString() {
		return $this->_stringDate;
	}

	public function toDateTime() {
		return $this->_mongoDate->toDateTime();
	}

	public function setMongoDate(MongoDB\BSON\UTCDatetime $date) {
		$this->_mongoDate = $date;
		$this->_stringDate = $date->__toString();
	}

	public function __construct($date = null) {
		if ($date instanceOf MongoDB\BSON\UTCDatetime) {
			$this->setMongoDate($date);
		} else {
			$this->setMongoDate(new MongoDB\BSON\UTCDatetime($date));
		}
	}

}
