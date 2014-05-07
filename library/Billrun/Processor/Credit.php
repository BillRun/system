<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Credit
 *
 * @author Shani
 */
class Billrun_Processor_Credit extends Billrun_Processor_Json {

	static protected $type = 'credit';

	public function processData() {
		parent::processData();
		foreach ($this->data['data'] as &$row) {
			$row['urt'] = new MongoDate($row['urt']['sec']);
		}
		return true;
	}

}
