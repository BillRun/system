<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Test action class
 *
 * @package  Action
 * 
 * @since    4.0
 */
class TestAction extends ApiAction {
	
	public function execute() {
		$this->getController()->setOutput(array(array("test" => "action"), true));
	}

}