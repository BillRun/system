<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Spl Observer
 *
 * @package SPL
 * @since    1.0
 */
class spl_observer implements SplObserver {

	public function update(SplSubject $subject) {
		echo "I was updated by " . get_class($subject);
	}

}
