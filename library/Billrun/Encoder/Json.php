<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Encoder_Json extends Billrun_Encoder_Base {

	public function encode($elem) {
		header('Content-Type: application/json');
		return json_encode((array) $elem);
	}

}
