<?php

class ASN_OBJECT extends ASN_BASE {

	public $dataLength = false;

	function __construct($data = false, $type = false)
	{
		parent::__construct($data,$type);
		if($this->isConstructed()) {
			//the object is constructed from smaller objects
			$this->parsedData = array();
			while (strlen($data) > 0){
				$this->parsedData[] = $this->newClassFromData($data);
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
		if(!$this->dataLength) {$this->dataLength = strlen($this->asnData);}
		return  $this->dataLength;
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
		return $this->typeId &  ASN_MARKERS::ASN_CONSTRUCTOR;
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


