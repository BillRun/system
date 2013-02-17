<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing api controller class
 *
 * @package  Action
 * @since    1.0
 */
class RefundAction extends Action_Base {
	
	public function execute() {
		$this->getController()->setOutput('Refund', 'executed');
	}

}