<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Regex {

	private $_mongoRegex;
	private $_stringRegex;

	public function __toString() {
		return $this->_stringRegex;
	}

	public function setMongoRegex(MongoDB\BSON\Regex $regex) {
		$this->_mongoRegex = $regex;
		$this->_stringRegex = $regex->__toString();
	}

	public function __construct($regex) {
		if ($regex instanceOf MongoDB\BSON\Regex) {
			$this->setMongoRegex($regex);
		} else {
			$this->setMongoRegex(new MongoDB\BSON\Regex($regex));
		}
	}

}
