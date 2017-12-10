<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing workspace receiver class
 *
 * @package     Billing
 * @subpackage  Receiver
 * @since	    1.0
 */
class Billrun_Receiver_Workspace extends Billrun_Receiver_Base_LocalFiles {

	public function __construct($options) {
		parent::__construct($options);

		if ($this->workspace && !$this->srcPath) {
			$this->srcPath = $this->workspace . $this->getType();
		}
	}

}
