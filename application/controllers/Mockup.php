<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Mockup controller class that illustrate EAI
 *
 * @package  Controller
 * @since    4.5
 */
class MockupController extends Yaf_Controller_Abstract {

	public function indexAction() {
		header("Content-Type:text/xml");
		echo '<RESPONSE>
    <HEADER>
        <COMMAND>PCRF_SubscriberSlowdown</COMMAND>
        <STATUS_CODE>OK</STATUS_CODE>
    </HEADER>
    <PARAMS>
        <RET_CODE>0</RET_CODE>
        <RET_DESC>Slowdown added successfully</RET_DESC>
    </PARAMS>
</RESPONSE>';
		
		die;
	}

}
