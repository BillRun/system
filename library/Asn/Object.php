<?php

/**
 * @package			ASN
 * @copyright		Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license			GNU Affero General Public License Version 3; see LICENSE.txt
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
	protected $rawDataLength = false;
	protected $typeId = null;
	protected $asnData = null;
	protected $flags = null;
	protected $offset = 0;

	function __construct($data = "", $type = false, $flags = false, $offset = 0) {	
		$data = $this->getObjectData($data, $offset);		
		
		$this->typeId = $type;		
		$this->flags = $flags;
		
		if ($this->isConstructed()) {
			//the object is constructed from smaller objects
			$this->parsedData = array();
			while (isset($data[0])) {
				$ret = $this->newClassFromData($data);				
				$this->asnData .= static::shift($data,$ret->getRawDataLength());
				if( $ret instanceof Asn_Type_Eoc || ( hexdec($ret->getType()) == 0 && $ret->getDataLength() == 0 )) {
					$this->offset += $ret->getRawDataLength();					
					break;
				}
				$this->parsedData[] = $ret;				
			}			
		} else {
			//this object only conatins data so parse it.
			$this->parse($data);
			$this->asnData = $data;
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
	 * get the length of the actual data this object contains
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
	 * get the length of the  raw data this object was created from.
	 * @return integer the length of the data that was used to create the object.
	 */
	public function getRawDataLength() {		
		if(!$this->rawDataLength) {
			$this->rawDataLength = $this->offset;
			if(is_array($this->parsedData)) {
				foreach($this->parsedData as $child) {
					$this->rawDataLength += $child->getRawDataLength();
				}
			} else {
				$this->rawDataLength += $this->parsedData instanceof Asn_Object  ?  $this->parsedData->getRawDataLength() : strlen($this->parsedData);
			}		
		}
		return $this->rawDataLength;
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
	
	/**
	 * Get the Object actual data length.
	 * @param $rawData 	(passed by ref) the raw byte data.
	 * @param $offset  the offset that that the data header start at. (Note: this  varialbe will be alter by this function)
	 * @return 		the length of the data also alter the $offset parameter by the length of the data header.
	 */
	protected function parseObjectDataLength($rawData, &$offest = 0) {
		$length = isset($rawData[$offest]) ? ord($rawData[$offest++]) : 0 ;
		if (($length & Asn_Markers::ASN_LONG_LEN) == Asn_Markers::ASN_LONG_LEN) {			
			$tempLength = 0;
			if($length == Asn_Markers::ASN_INDEFINITE_LEN ) {			
				return FALSE;
			} else for ($x = ($length - Asn_Markers::ASN_LONG_LEN); $x > 0; $x--) {
				$tempLength = ord($rawData[$offest++]) + ($tempLength << 8);
			}
			$length = $tempLength;
		}
		return $length;
	}
	
	/**
	 * Get the object data string.
	 * @param type $data the raw data for the object.
	 * @param type $offset the offset to the data header in the raw data.
	 * @return mixed the data that shold be held in the object.
	 */
	protected function getObjectData($data,$offset) {
		$dataLength = $this->parseObjectDataLength($data,$offset); 		
		$this->offset += $offset;
		if(FALSE === $dataLength) {
			return substr($data,$offset);
		} 
		return substr($data,$offset,$dataLength);
	}

}
