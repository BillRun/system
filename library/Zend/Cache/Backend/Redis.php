<?php

/**
 * Zend Framework - Redis Cache Backend with TLS Support
 */
class Zend_Cache_Backend_Redis extends Zend_Cache_Backend implements Zend_Cache_Backend_ExtendedInterface {

	const DEFAULT_HOST = '127.0.0.1';
	const DEFAULT_PORT = 6379;

	protected $_options = array(
		'servers' => array(
			'host' => self::DEFAULT_HOST,
			'port' => self::DEFAULT_PORT,
		),
		'password' => null,
		'database' => null,
		'readTimeout' => null,
		'connectTimeout' => null,
		'ssl' => false, // Enable TLS if true
		'ssl_context' => array(), // Optional context options for SSL ['verify_peer' => true ]
	);

	/** @var Redis */
	protected $_redis = null;

	public function __construct(array $options = array()) {
		if (!extension_loaded('redis')) {
			Zend_Cache::throwException('The redis extension must be loaded for using this backend!');
		}

		parent::__construct($options);

		$servers = $this->_options['servers'];
		if (!isset($servers['host'])) {
			// RedisCluster mode
			$hosts = array();
			foreach ($servers as $server) {
				$host = $server['host'] ?? self::DEFAULT_HOST;
				$port = $server['port'] ?? self::DEFAULT_PORT;
				$hosts[] = "{$host}:{$port}";
			}

			$timeout = $this->_options['connectTimeout'] ?? null;
			$readTimeout = $this->_options['readTimeout'] ?? null;
			$persistent = false;
			$password = $this->_options['password'] ?? NULL;

			$sslContext = null;
			if ($this->_options['ssl']) {
				// RedisCluster TLS: pass 'ssl' key with context options
				$sslContext = array(
					'ssl' => $this->_options['ssl_context'] ?? array()
				);
			}

			$this->_redis = new RedisCluster(
				NULL,
				$hosts, // seed nodes
				$timeout,
				$readTimeout,
				$persistent,
				$password,
				$sslContext
			);
		} else {

			// Single Redis node mode

			$server = $servers;
			$host = $server['host'] ?? self::DEFAULT_HOST;
			$port = $server['port'] ?? self::DEFAULT_PORT;
			$timeout = $this->_options['connectTimeout'] ?? null;
			$readTimeout = $this->_options['readTimeout'] ?? null;

			$this->_redis = new Redis();

			if ($this->_options['ssl']) {
				$context = $this->_options['ssl_context'] ?? [];
				$connected = $this->_redis->connect($host, $port, $timeout, NULL, 0, 0, $context);
			} else {
				$connected = $this->_redis->connect($host, $port, $timeout);
			}

			if (!$connected) {
				Zend_Cache::throwException("Could not connect to Redis server at {$host}:{$port}");
			}

			if ($this->_options['password']) {
				$this->_redis->auth($this->_options['password']);
			}

			if ($this->_options['database'] !== null) {
				$this->_redis->select($this->_options['database']);
			}
		}
	}

	public function load($id, $doNotTestCacheValidity = false) {
		$tmp = $this->_redis->get($id);
		if ($tmp === false)
			return false;

		$tmp = @unserialize($tmp);
		return isset($tmp[0]) ? $tmp[0] : false;
	}

	public function test($id) {
		$tmp = $this->_redis->get($id);
		if ($tmp === false)
			return false;

		$tmp = @unserialize($tmp);
		return isset($tmp[1]) ? (int) $tmp[1] : false;
	}

	public function save($data, $id, $tags = array(), $specificLifetime = false) {
		$lifetime = $this->getLifetime($specificLifetime);
		$value = serialize(array($data, time(), $lifetime));
		$result = $this->_redis->set($id, $value, ['ex' => $lifetime]);

		if (count($tags) > 0) {
			$this->_log('Tags are unsupported by the Redis backend');
		}

		return $result;
	}

	public function remove($id) {
		return (bool) $this->_redis->del($id);
	}

	public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = array()) {
		switch ($mode) {
			case Zend_Cache::CLEANING_MODE_ALL:
				return $this->_redis->flushDB();
			case Zend_Cache::CLEANING_MODE_OLD:
				$this->_log("CLEANING_MODE_OLD is unsupported by the Redis backend");
				break;
			case Zend_Cache::CLEANING_MODE_MATCHING_TAG:
			case Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG:
			case Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG:
				$this->_log('Tags are unsupported by the Redis backend');
				break;
			default:
				Zend_Cache::throwException('Invalid mode for clean() method');
		}
		return false;
	}

	public function touch($id, $extraLifetime) {
		$tmp = $this->_redis->get($id);
		if ($tmp === false)
			return false;

		$tmp = @unserialize($tmp);
		if (!isset($tmp[0], $tmp[1], $tmp[2]))
			return false;

		$data = $tmp[0];
		$mtime = $tmp[1];
		$lifetime = $tmp[2];

		$newLifetime = $lifetime - (time() - $mtime) + $extraLifetime;
		if ($newLifetime <= 0)
			return false;

		$result = $this->_redis->set($id, serialize(array($data, time(), $newLifetime)), $newLifetime);
		return $result;
	}

	public function getMetadatas($id) {
		$tmp = $this->_redis->get($id);
		if ($tmp === false)
			return false;

		$tmp = @unserialize($tmp);
		if (!isset($tmp[0], $tmp[1], $tmp[2]))
			return false;

		return array(
			'expire' => $tmp[1] + $tmp[2],
			'tags' => array(),
			'mtime' => $tmp[1],
		);
	}

	public function getCapabilities() {
		return array(
			'automatic_cleaning' => true,
			'tags' => false,
			'expired_read' => false,
			'priority' => false,
			'infinite_lifetime' => true,
			'get_list' => false,
		);
	}

	public function getTags() {
		$this->_log('Tags unsupported');
		return array();
	}

	public function getIds() {
		$this->_log('getIds unsupported by Redis backend');
		return array();
	}

	public function getIdsMatchingTags($tags = array()) {
		$this->_log('Tags unsupported');
		return array();
	}

	public function getIdsNotMatchingTags($tags = array()) {
		$this->_log('Tags unsupported');
		return array();
	}

	public function getIdsMatchingAnyTags($tags = array()) {
		$this->_log('Tags unsupported');
		return array();
	}

	public function getFillingPercentage() {
		return 0;
	}

	public function isAutomaticCleaningAvailable() {
		return true;
	}
}
