<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

class Billrun_ActionManagers_Statistics_Create extends Billrun_ActionManagers_Statistics_Action {
	public function __construct() {
		parent::__construct();
	}

	public function createProcess($input) {
		$statistics = $input->get('statistics');
		Billrun_Factory::log($statistics);
	}
	
	public function execute() {
		
	}
	
	public function parse($request) {
		if (!$this->createProcess($request))
			return false;
		return true;
	}
}
