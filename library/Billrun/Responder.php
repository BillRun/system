<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract receiver class
 *
 * @package  Billing
 * @since    0.5
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
			$this->workspace = Billrun_Util::getBillRunSharedFolderPath($options['workspace']);
		} else {
			$this->workspace = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue('response.workspace'));
		}

		if (isset($options['backup'])) {
			$this->workspace = Billrun_Util::getBillRunSharedFolderPath($options['backup']);
		} else {
			$defBackup = Billrun_Factory::config()->getConfigValue('response.backup');
			$this->workspace = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue(static::type . '.backup', $defBackup));
		}
	}

	/**
	 * general function to receive
	 *
	 * @return array containing paths to the exported files.
	 */
	abstract public function respond();
}
