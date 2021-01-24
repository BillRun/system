<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Regex implements Mongodloid_TypeInterface{

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
			if (! preg_match('#^/(.*)/([imxslu]*)$#', $regex, $matches)) {
				throw new Mongodloid_Exception('invalid regex', 9);
			}
			$pattern = $matches[1];
			$flags = $matches[2];
			$this->setMongoRegex(new MongoDB\BSON\Regex($pattern, $flags));
		}
	}
	
	/**
     * Converts this Mongodloid_Regex to the new BSON Regex type
     *
     * @return MongoDB\BSON\Regex
     */
    public function toBSONType()
    {
        return $this->_mongoRegex;
    }

}
