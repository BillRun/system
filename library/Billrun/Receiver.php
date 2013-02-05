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
abstract class Billrun_Receiver extends Billrun_Base {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'receiver';

	/**
	 * the receiver workspace path of files
	 * this is the place where the files will be received
	 * 
	 * @var string
	 */
	protected $workspace;

	/**
	 * general function to receive
	 *
	 * @return array list of files received
	 */
	abstract public function receive();

	/**
	 * method to log the processing
	 * 
	 * @todo refactoring this method
	 */
	protected function logDB($path) {
		if (!isset($this->db)) {
			$this->log->log("Billrun_Processor:logDB database instance not found", Zend_Log::ERR);
			return false;
		}

		$log = $this->db->getCollection(self::log_table);

		$log_data = array(
			'source' => static::$type,
			'path' => $path,
			'file_name' => basename($path),
		);

		$log_data['stamp'] = md5(serialize($log_data));
		$log_data['received_time'] = date(self::base_dateformat);

		$this->dispatcher->trigger('beforeLogReceiveFile', array(&$log_data, $this));
		$entity = new Mongodloid_Entity($log_data);
		if ($log->query('stamp', $entity->get('stamp'))->count() > 0) {
			$this->log->log("Billrun_Receiver::logDB - DUPLICATE! trying to insert duplicate log file with stamp of : {$entity->get('stamp')}", Zend_Log::NOTICE);
			return FALSE;
		}

		return $entity->save($log, true);
	}

}
