<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Importer Cards class
 *
 * @package  Billrun
 * @since    4.0
 */
class Billrun_Importer_Cards extends Billrun_Importer_Csv {
	
	protected function getCollectionName() {
		return 'cards';
	}

	protected function getSecret($rowData) {
		$secret = hash('sha512',$rowData[2]);
		return $secret;
	}
	
	protected function getTo($rowData) {
		$to = new MongoDate(strtotime($rowData[6]));
		return $to;
	}
	
	protected function getCreationTime($rowData) {
		$currentTime = date('m/d/Y h:i:s a', time());
		$creationTime = new MongoDate(strtotime($currentTime));
		return $creationTime;
	}
	
}