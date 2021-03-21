<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Temporary plugin  to handle smsc/smpp/mmsc retrival should be changed to specific CDR  handling baviour
 *
 * @package  Application
 * @subpackage Plugins
 * @since    2.0
 */
class smsPlugin extends Billrun_Plugin_BillrunPluginBase {

	use Billrun_Traits_FileSequenceChecking;

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'sms';

	/**
	 * Setup the sequence checker.
	 * @param type $receiver
	 * @param type $hostname
	 * @return type
	 */
	public function beforeFTPReceive($receiver, $hostname) {
		if ($receiver->getType() != 'smsc' && $receiver->getType() != "smpp" && $receiver->getType() != "mmsc") {
			return;
		}

		$this->setFilesSequenceCheckForHost($hostname);
	}

	/**
	 * Check the  received files sequence.
	 * @param type $receiver
	 * @param type $filepaths
	 * @param type $hostname
	 * @return type
	 * @throws Exception
	 */
	public function afterFTPReceived($receiver, $filepaths, $hostname) {
		if ($receiver->getType() != 'smsc' && $receiver->getType() != "smpp" && $receiver->getType() != "mmsc") {
			return;
		}

		$this->checkFilesSeq($filepaths, $hostname);

		$path = Billrun_Factory::config()->getConfigValue($receiver->getType() . '.thirdparty.backup_path', false, 'string');
		if (!$path)
			return;
		if ($hostname) {
			$path = $path . DIRECTORY_SEPARATOR . $hostname;
		}

		foreach ($filepaths as $filePath) {
			if (!$receiver->backupToPath($filePath, $path, true, true)) {
				Billrun_Factory::log("Couldn't save file $filePath to third patry path at : $path", Zend_Log::ERR);
			}
		}
	}

}
