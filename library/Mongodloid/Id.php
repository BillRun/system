<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Id implements Mongodloid_TypeInterface, JsonSerializable, Serializable{

	private $_objectId;
	private $_stringID;

	public function __toString() {
		return $this->_stringID;
	}

	public function getMongoID() {
		return $this;
	}

	public function setMongoID(MongoDB\BSON\ObjectId $id) {
		$this->_objectId = $id;
		$this->_stringID = $this->_objectId->__toString();
	}

	public function __construct($base = null) {
		if ($base instanceOf MongoDB\BSON\ObjectId) {
			$this->setMongoID($base);
		} else {
			$this->setMongoID(new MongoDB\BSON\ObjectId($base));
		}
	}
	
	/**
     * Converts this Mongodloid_Id to the new BSON Id type
     *
     * @return MongoDB\BSON\ObjectId
     */
    public function toBSONType()
    {
        return $this->_objectId;
    }


    /**
     * @return stdClass
     */
    public function jsonSerialize()
    {
        $object = new stdClass();
        $object->{'$id'} =  $this->_stringID;
        return $object;
    }
	
	 /**
     * @param string $name
     *
     * @return null|string
     */
    public function __get($name)
    {
        if ($name === '$id') {
            return $this->_stringID;
        }

        return null;
    }

	/**
     * Check if a value is a valid ObjectId
     *
     * @link http://php.net/manual/en/mongoid.isvalid.php
     * @param mixed $value The value to check for validity.
     * @return bool
     */
    public static function isValid($value)
    {
        if ($value instanceof MongoDB\BSON\ObjectId || $value instanceof Mongodloid_Id) {
            return true;
        } elseif (! is_string($value)) {
            return false;
        }

        return (bool) preg_match('#^[a-f0-9]{24}$#i', $value);
    }
	
	/**
     * @return string
     */
    public function serialize()
    {
        return $this->_stringID;
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $this->setMongoID(new MongoDB\BSON\ObjectId($serialized));
    }
}