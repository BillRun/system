<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing json processor class
 *
 * @package  Billing
 * @since    2.0
 */
class Billrun_Processor_Json extends Billrun_Processor {

	static protected $type = 'json';

	/**
	 * @see Billrun_Processor::parse()
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log('Resource is not configured well', Zend_Log::ERR);
			return FALSE;
		}
		$this->data['data'] = json_decode(stream_get_contents($this->fileHandler), true);
		if (!isset($this->data['trailer']) && !isset($this->data['header'])) {
			$this->data['trailer'] = array('no_trailer' => true);
			$this->data['header'] = array('no_header' => true);
		}

		return $this->processData();
	}

	public function processData() {
		foreach ($this->data['data'] as &$row) {
			$row['process_time'] = new MongoDate();
		}
		return true;
	}

}
