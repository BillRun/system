<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Decoder_Xml extends Billrun_Decoder_Base {

	public function decode($str) {
		$xmlArr = (array) simplexml_load_string($str);
		return json_decode( json_encode($xmlArr) , 1);
	}

}
