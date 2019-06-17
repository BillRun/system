<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a plugin to provide GGSN support to the billing system.
 */
class sgsnPlugin extends ggsnPlugin {
	
	const HEADER_LENGTH = 0;
	const MAX_CHUNKLENGTH_LENGTH = 4096;
	const FILE_READ_AHEAD_LENGTH = 16384;
	const RECORD_PADDING = 0;

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'sgsn';
	
	public function __construct(array $options = array()) {
		parent::__construct($options);
		$this->ggsnConfig = (new Yaf_Config_Ini(Billrun_Factory::config()->getConfigValue('sgsn.config_path')))->toArray();
	}
	
	/**
	 * method to unzip the processing file of SGSN (received as GZ archive)
	 * 
	 * @param string $file_path the path of the file
	 * @param Billrun_Processor $processor instance of the processor who dispatch this event
	 * 
	 * @return boolean
	 */
	public function processorBeforeFileLoad(&$file_path, $processor) {
		if ($processor->getType() != $this->getName()) {
			return;
		}
		if (file_exists($file_path)) {
			try {
				Billrun_Util::decompress($file_path, 'gz');
			} catch (Exception $ex) {
				Billrun_Factory::log('Error decompressing file "' . $file_path . '". Error code: ' . $ex->getCode() . '. Error message: ' . $ex->getMessage(), Billrun_Log::ERR);
				$file_path = false;
				return false;
			}
			$file_path = str_replace('.Z', '', $file_path);

			return true;
		}
		return false;
	}
	
}
