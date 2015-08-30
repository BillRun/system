<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Decoder_Json extends Billrun_Decoder_Base {

	public function decode($str) {
		return json_decode($str, JSON_OBJECT_AS_ARRAY);
	}

}
