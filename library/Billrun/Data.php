<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Data
 *
 * @author eran
 */
class Billrun_Data {
	protected $data = array();
	
	public function __construct($inData=array()) {
		$this->data = $inData instanceof Mongodloid_Entity ? $inData->getRawData() : $inData;
	}
	
}

