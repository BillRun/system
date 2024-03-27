<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing basic abstract class
 *
 * @package  base
 * @since    1.0
 */
abstract class Billrun_Base {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'base';

	/**
	 * Stamp of the object
	 * Used to make the object unique
	 *
	 * @var string
	 */
	protected $stamp = null;

	/**
	 * constant for base date format
	 */
	const base_dateformat = 'Y-m-d H:i:s';

	/**
	 * Limit iterator
	 * used to limit the count of row to calc on.
	 * 0 or less means no limit
	 *
	 * @var int
	 */
	protected $limit = 10000;
	
	
	static protected $instance = array();
	/**
	 * constructor
	 * 
	 * @param array $options
	 */
	public function __construct($options = array()) {
		
		if (isset($options['type'])) {
			static::$type = $options['type'];
		}

		if (isset($options['stamp']) && $options['stamp']) {
			$this->setStamp($options['stamp']);
		} else {
			$this->setStamp(uniqid(get_class($this)));
		}

		if(isset($options['limit']) && $options['limit']) {
			$this->setLimit($options['limit']);
		} 
		
	}

	/**
	 * set stamp of the basic object
	 * used for unique object actions
	 *
	 * @param string $stamp the stamp to set
	 *
	 * @return mixed self instance
	 */
	public function setStamp($stamp) {
		$this->stamp = $stamp;
		return $this;
	}

	/**
	 * get stamp of the basic object
	 * used for unique object actions
	 *
	 * @return string the stamp of the object
	 */
	public function getStamp() {
		return $this->stamp;
	}
	
	
	/**
	 * Set running limit for the current instance
	 * used differently by each inheriteing class
	 * 
	 * @param string $limit the limit to set
	 *
	 * @return mixed self instance
	 */
	public function setLimit($limit) {
		$this->limit = $limit;
		return $this;
	}

	/**
	 * Get the current instance limit
	 *
	 * @return string the limit of the object
	 */
	public function getLimit() {
		return $this->limit;
	}
	
	/**
	 * Get the type name of the current object.
	 * @return string conatining the current.
	 */
	public function getType() {
		return static::$type;
	}

	/**
	 * Loose coupling of objects in the system
	 *
	 * @return mixed the bridge class
	 */
	static public function getInstance() {
		$args = func_get_args();

		$stamp = md5(serialize($args));
		if (isset(self::$instance[$stamp])) {
			return self::$instance[$stamp];
		}

		if (isset($args['type'])) {
			$type = $args['type'];
			$args = array();
			Billrun_Factory::log()->log('Depratected approach of Billrun_Base::getInstance: ' . $type, Zend_Log::INFO);
		} else {
			$type = $args[0]['type'];
			unset($args[0]['type']);
			$args = $args[0];
		}

		$config_type = Yaf_Application::app()->getConfig()->{$type};
		$called_class = get_called_class();

		if ($called_class && Billrun_Factory::config()->getConfigValue($called_class)) {
			$args = array_merge(Billrun_Factory::config()->getConfigValue($called_class)->toArray(), $args);
		}

		$class_type = $type;
		if ($config_type) {
			$args = array_merge($config_type->toArray(), $args);
			if (isset($config_type->{$called_class::$type}) &&
				isset($config_type->{$called_class::$type}->type)) {
				$class_type = $config_type[$called_class::$type]['type'];
				$args['type'] = !empty($config_type[$called_class::$type]['force_type'])  ?
				(!empty($config_type[$called_class::$type]['force_type_to']) ? $config_type[$called_class::$type]['force_type_to'] : $class_type) :
				$type;
			}
		}
		$class = $called_class . '_' . ucfirst($class_type);
		if (!@class_exists($class, true)) {
			// try to search in external sources (application/helpers)
			$external_class = str_replace('Billrun_', '', $class);
			if (($pos = strpos($external_class, "_")) !== FALSE) {
				$namespace = substr($external_class, 0, $pos);
				Yaf_Loader::getInstance(APPLICATION_PATH . '/application/helpers')->registerLocalNamespace($namespace);
			}
			if (!@class_exists($external_class, true)) {
				Billrun_Factory::log("Can't find class: " . $class, Zend_Log::EMERG);
				return false;
			}
			$class = $external_class;
		}

		self::$instance[$stamp] = new $class($args);
		return self::$instance[$stamp];
	}
	
	/**
	 * method to get config value
	 * 
	 * @param mixed $keys array of keys or string divided by period
	 * @param mixed $defVal the value return if the keys not found in the config
	 * @return mixed the config value
	 * @deprecated since version 1.0
	 */
	public function getConfigValue($keys, $defVal) {
		Billrun_Factory::log()->log("Billrun_Base::getConfigValue is deprecated; please use Billrun_Config::getConfigValue through factory::config()", Zend_Log::DEBUG);
		return Billrun_Factory::config()->getConfigValue($keys, $defVal);
	}

}
