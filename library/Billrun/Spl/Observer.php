<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Spl Observer
 *
 * @package SPL
 * @since    1.0
 */
abstract class Billrun_Spl_Observer extends Billrun_Base implements SplObserver {

	/**
	 * method to trigger the observer
	 * 
	 * @param SplSubject $subject the subject which trigger this observer
	 */
	public function update(SplSubject $subject) {
		$subject->notify();
	}

}
