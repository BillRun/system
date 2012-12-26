<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Spl Object
 *
 * @package SPL
 * @since    1.0
 */
class spl_object implements SplSubject {

	protected $observers = array();

	public function attach(SplObserver $observer) {
		$this->observers[] = $observer;
	}

	public function detach(SplObserver $observer) {
		$key = array_search($observer, $this->observers);
		if ($key) {
			unset($this->observers[$key]);
		}
	}

	public function notify() {
		foreach ($this->observers as $observer) {
			$observer->update($this);
		}
	}

}

