<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class that will unifiy several cdrs to s single cdr if possible.
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
 * 
 * @since    2.6
 *
 */
class Billrun_Calculator_Unify_Realtime extends Billrun_Calculator_Unify {

	/**
	 * 
	 * @return type
	 */
	protected function getLines() {
		$query = array('realtime' => true);
		return $this->getQueuedLines($query);
	}

	protected function setUnifiedLineDefaults(&$line) {
		
	}

	protected function getDateSeparation($line, $typeData) {
		return FALSE;
	}

}
