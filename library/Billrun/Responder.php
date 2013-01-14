<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract receiver class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class Billrun_Responder extends Billrun_Base {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'responder';

	/**
	 * the responder files workspace.
	 * @var string directory path
	 */
	protected $workspace;

	public function __construct($options) {

		parent::__construct($options);

		if (isset($options['workspace'])) {
			$this->workspace = $options['workspace'];
		} else {
			$this->workspace = $this->config->ilds->path;
		}

	}

	/**
	 * general function to receive
	 *
	 * @return mixed
	 */
	abstract public function respond();
}


