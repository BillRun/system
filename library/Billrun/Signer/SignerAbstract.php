<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billrun digital signature abstract
 *
 * @package  Billing
 * @since    5.16
 */
abstract class Billrun_Signer_SignerAbstract {

	/**
	 * @var string Type of the signer implementation
	 */
	static public $type;

	/**
	 * @var array Configuration needed to configure signer and sign the file
	 */
	public $config;

	/**
	 * @var string Path to the file to sign
	 */
	public $path;

	abstract public function configure();

	abstract public function sign();

	abstract public function buildCommand();

	abstract public function exec();
}
