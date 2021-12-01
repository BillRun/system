<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Utils_DataManipulation {
	static public function getModulo10CheckDigit($value) {
		$carryValues = [0,9,4,6,8,2,7,1,3,5];
		$carry = 0;
		$valueArr= str_split($value,1);
		foreach($valueArr as $digit) {
			$carry =  $carryValues[($digit+$carry)%10];
		}
		$checkDigit = (10 - $carry) % 10;

		return $checkDigit;
	}

	static public function addModulo10ToNumber($value) {
		return $value . static::getModulo10CheckDigit($value);
	}
}
