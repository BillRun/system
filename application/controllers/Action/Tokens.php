<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Balance action class
 *
 * @package  Action
 * @since    0.5
 */
class TokensAction extends Action_Base {

    public function execute() {
	$request = $this->getRequest();
	$GUT = $request->get("gut");

	if (is_null($GUT)) {
	    die();
	}

	$OUT = Billrun_Util::hash($GUT);

	// Insert to DB
	$model = new TokensModel();
	$model->storeData($GUT, $OUT);

	// Send request to google
	$url = Billrun_Factory::config()->getConfigValue('tokens.host') .
		Billrun_Factory::config()->getConfigValue('tokens.post');
	$data = array(
	    "kind"		=> "carrierbilling#userToken",
	    "googleToken"	=> $GUT,
	    "operatorToken"	=> $OUT
	);
	
	$response = Billrun_Util::sendRequest($url, $data, array("onlyBody" => false));
	$status = $response->getStatus();
	
	// Verifies request success
	if ($status != 200) {
	    Billrun_Factory::log()->log("No response from Google API.\nData sent: " . $data, Zend_Log::ALERT);    
	}
    }
}
