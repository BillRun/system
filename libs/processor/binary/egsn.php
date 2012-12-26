<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing processor for 012 class
 *
 * @package  Billing
 * @since    1.0
 */
class processor_binary_egsn extends processor_binary {

	protected $type = 'egsn';

	public function __construct($options) {
		parent::__construct($options);

	}

}