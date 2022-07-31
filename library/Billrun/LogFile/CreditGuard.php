<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Credit Guard log file
 *
 * @package  LogFile
 * @since    5.9
 */
class Billrun_LogFile_CreditGuard extends Billrun_LogFile {

	/**
	 * source field of the log file
	 * @var string
	 */
	protected $source = 'CreditGuard';

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->collection = Billrun_Factory::db()->logCollection();
		$key = $this->generateKeyFromOptions($options);
		$query = array(
			'source' => $this->source,
			'key' => $key,
			'process_time' => array('$exists' => true),
		);

		$cgLog = $this->collection->query($query)->cursor();
		if ($cgLog->count() > 1) {
			throw new Exception('Billrun_LogFile_CreditGuard: More than one log file was found');
		} elseif ($cgLog->count() == 1) {
			$this->data = $cgLog->current();
			if (isset($this->data['process_time'])) {
				throw new Exception('Billrun_LogFile_CreditGuard: file already created');
			}
			$this->setStartProcessTime();
			$this->data->collection($this->collection);
		} else {
			$this->data = new Mongodloid_Entity();
			$this->data->collection($this->collection);
			$this->data['key'] = $key;
			$this->data['source'] = $this->source;
			$this->data['rand'] = Billrun_Util::generateRandomNum();
			$this->setStartProcessTime();
			$this->setStamp();
			$this->save();
		}
	}

	public function setSequenceNumber() {
		return $this->data->createAutoInc('seq');
	}

	public function getSequenceNumber() {
		if (isset($this->data['seq'])) {
			return $this->data['seq'];
		}
		return NULL;
	}

	protected function generateKeyFromOptions($options) {
		$options['time'] = time();
		return md5(serialize($options));
	}

}
