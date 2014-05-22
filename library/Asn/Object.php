<?php

/**
 * @package			ASN
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This define a general ASN object.
 *
 * @package  ASN
 * @since    0.5
 */
class Asn_Object extends Asn_Base {

	protected $parsedData = null;
	protected $dataLength = false;
	protected $typeId = null;
	protected $asnData = null;
	protected $flags = null;
	protected $offset = 0;

	function __construct($data = false, $type = false, $flags = false, $offset = 0) {
		if (false !== $data) {
			$this->asnData = $data;
		}
		if (false !== $type) {
			$this->typeId = $type;
		}

		if (false !== $flags) {
			$this->flags = $flags;
		}
		
		$this->offset = $offset;
		
		if ($this->isConstructed()) {
			//the object is constructed from smaller objects
			$this->parsedData = array();
			while (isset($data[0])) {
				$ret = $this->newClassFromData($data);				
				if( $ret instanceof Asn_Type_Eoc || ($ret->getDataLength() == 0 && hexdec($ret->getType()) == 0 )) {
					$this->offset += $ret->getRawDataLength();
					break;
				}
				$this->parsedData[] = $ret;
			}
		} else {
			//this object only conatins data so parse it.
			$this->parse($this->asnData);
		}
	}

	/**
	 * get the parsed data that was encoded in the ASN.
	 * @retrun mixed the actual that that was encoded in this field.
	 */
	public function getData() {
		return $this->parsedData;
	}

	/**
	 * get the length of the data this object contains
	 * @return integer the length of the data conatined in the object.
	 */
	public function getDataLength() {
		if (!$this->dataLength) {
			$this->dataLength = 0;
			if(is_array($this->parsedData)) {
				foreach($this->parsedData as $child) {
					$this->dataLength += $child->getDataLength();
				}
			} else {
				$this->dataLength = $this->parsedData instanceof Asn_Object  ?  $this->parsedData->getDataLength() : strlen($this->parsedData);
			}
		}
		return $this->dataLength;
	}

	/**
	 * get the length of the data this object was created from.
	 * @return integer the length of the data that was used to create the object.
	 */
	public function getRawDataLength() {		
		$rawDataLength = $this->offset;
		if(is_array($this->parsedData)) {
			foreach($this->parsedData as $child) {
				$rawDataLength += $child->getRawDataLength();
			}
		} else {
			$rawDataLength += $this->parsedData instanceof Asn_Object  ?  $this->parsedData->getRawDataLength() : strlen($this->parsedData);
		}		
		return $rawDataLength;
	}	
	
	
	/**
	 * Get the type that the object was constructed on.
	 * @return string hex representation of the type.
	 */
	public function getType() {
		return dechex($this->typeId);
	}

	/**
	 * Check if the corrent object is a constructed one
	 * (built from smaller objects)
	 * @return boolean which will be true if the corrent object is constructed false otherwise.
	 */
	public function isConstructed() {
		return $this->flags & Asn_Markers::ASN_CONSTRUCTOR;
	}

	/**
	 * Parse the ASN data of a primitive type.
	 * (Override this to parse specific types)
	 * @param $data The ASN encoded data
	 */
	protected function parse($data) {
		$this->parsedData = $data;
	}

}
