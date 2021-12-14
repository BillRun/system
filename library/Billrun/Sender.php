<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract sender class
 * The class should handle moving/putting/sending file/s to a new location
 *
 * @package  Billing
 * @since    5.9
 */
abstract class Billrun_Sender extends Billrun_Base {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'sender';
	protected $options = array();

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->options = $options;
	}

	/**
	 * general function to send
	 * 
	 * @param string $filePath file/s to send
	 *
	 */
	abstract protected function send($filePath);

	public static function getInstance($options = array()) {
		$stamp = md5(static::class . serialize($options));
		if (isset(self::$instance[$stamp])) {
			return self::$instance[$stamp];
		}

		$type = $options['connection_type'];
		$class = 'Billrun_Sender_' . ucfirst($type);
		if (!@class_exists($class, true)) {
			Billrun_Factory::log("Can't find class: " . $class, Zend_Log::EMERG);
			return false;
		}
		self::$instance[$stamp] = new $class($options);
		return self::$instance[$stamp];
	}

}
