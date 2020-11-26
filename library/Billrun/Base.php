<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing basic abstract class
 *
 * @package  base
 * @since    0.5
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
	 * Base array instances container
	 *
	 * @var array
	 */
	static protected $instance = array();

	/**
	 * constant for base datetime format
	 */
	const base_datetimeformat = 'Y-m-d H:i:s';

	/**
	 * constant for base date format
	 */
	const base_dateformat = 'Y-m-d';

	/**
	 * Limit iterator
	 * used to limit the count of row to calc on.
	 * 0 or less means no limit
	 *
	 * @var int
	 */
	protected $limit = 10000;

	/**
	 * Page
	 * number of page to retrieve (starting from 0)
	 *
	 * @var int
	 */
	protected $page = 0;

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

		if (isset($options['limit']) && $options['limit']) {
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
	public static function getInstance() {
		$args = func_get_args();

		$stamp = md5(static::class . serialize($args));
		if (isset(self::$instance[$stamp])) {
			return self::$instance[$stamp];
		}

		$type = $args[0]['type'];
		unset($args[0]['type']);
		$args = $args[0];

		if (!$config_type = Billrun_Factory::config()->{$type}) {
			$config_type = array_filter(Billrun_Factory::config()->file_types->toArray(), function($fileSettings) use ($type) {
				return $fileSettings['file_type'] === $type && Billrun_Config::isFileTypeConfigEnabled($fileSettings);
			});
			if ($config_type) {
				$config_type = current($config_type);
			}
		}
		$called_class = get_called_class();

		if ($called_class && Billrun_Factory::config()->getConfigValue($called_class)) {
			$args = array_merge(Billrun_Factory::config()->getConfigValue($called_class)->toArray(), $args);
		}

		if (!isset($args['payment_gateway'])) {
			$class_type = $type;
		} else {
			$class_type = 'PaymentGateway_' . $args['payment_gateway'] . '_' . ucfirst($type);
			$gatewayName = $args['payment_gateway'];
			$typeInConfig = array_filter(Billrun_Factory::config()->payment_gateways->toArray(), function($pgSettings) use ($gatewayName) {
				return $pgSettings['name'] === $gatewayName;
			});
			if ($typeInConfig) {
				$typeInConfig = current($typeInConfig);
			}
			if (!empty($typeInConfig['custom'])) {
				$args = array_merge($typeInConfig, $args);
				$args['type'] = $type;
				$class_type = 'PaymentGateway_Custom_' . str_replace('_', '', ucwords($type, '_'));
			}
		}
		if ($config_type) {
			if (is_object($config_type)) {
				$config_type = $config_type->toArray();
			}
			$args = array_merge($config_type, $args);
			if (isset($config_type[$called_class::$type]) &&
				isset($config_type[$called_class::$type]['type'])) {
					$class_type = $config_type[$called_class::$type]['type'];
					$args['type'] = $type;
			} else if(!empty($config_type[$called_class::$type]['type_mapping'])) {
				foreach (@$config_type[$called_class::$type]['type_mapping'] as $typeConfig) {
					$match = true;
					foreach (@$typeConfig['config'] as $field => $value) {
						$match &= Billrun_Factory::config()->getConfigValue($field,null) == $value;
					}
					if($match) {
						$class_type = $typeConfig['type'];
						$args['type'] = $type;
						break;
					}
				}
			}
		}
		$class = $called_class . '_' . ucfirst($class_type);
		if (!@class_exists($class, true)) {
			// try to search in external sources (application/helpers)
			$external_class = str_replace('Billrun_', '', $class);
			if (($pos = strpos($external_class, "_")) !== FALSE) {
				$namespace = substr($external_class, 0, $pos);
				br_yaf_register_autoload($namespace, APPLICATION_PATH . '/application/helpers');
			}
			// TODO: We need a special indication for this case.
			// There are places in the code that try to create clases in a loop,
			// if the class doesn't exist it is a critical error that should stop the operation
			// of most executed logic.
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
		Billrun_Factory::log("Billrun_Base::getConfigValue is deprecated; please use Billrun_Config::getConfigValue through factory::config()", Zend_Log::DEBUG);
		return Billrun_Factory::config()->getConfigValue($keys, $defVal);
	}

}
