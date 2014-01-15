<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Json
 *
 * @author Shani
 */
class Billrun_Processor_Json extends Billrun_Processor {

	static protected $type = 'json';

	/**
	 * @see Billrun_Processor::parse()
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log()->log('Resource is not configured well', Zend_Log::ERR);
			return FALSE;
		}
		$this->data['data'] = json_decode(stream_get_contents($this->fileHandler), true);
		return $this->processData();
	}

	public function processData() {
		foreach ($this->data['data'] as &$row) {
			$row['process_time'] = Billrun_Util::generateCurrentTime();
		}
		return true;
	}

	protected function logDB() {
		
	}

}
