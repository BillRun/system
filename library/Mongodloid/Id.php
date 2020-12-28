<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Id {

	private $_mongoID;
	private $_stringID;

	public function __toString() {
		return $this->_stringID;
	}

	public function getMongoID() {
		return $this->_mongoID;
	}

	public function setMongoID(MongoDB\BSON\ObjectId $id) {
		$this->_mongoID = $id;
		$this->_stringID = $this->_mongoID->__toString();
	}

	public function __construct($base = null) {
		if ($base instanceOf MongoDB\BSON\ObjectId) {
			$this->setMongoID($base);
		} else {
			$this->setMongoID(new MongoDB\BSON\ObjectId($base));
		}
	}

}
