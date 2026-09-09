<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing products group class
 * A products group holds the products (rates) and the usage types of an included quota
 *
 * @package  Productgroups
 * @since    7.0
 */
class Billrun_Productgroups {

	/**
	 * container of the entity data
	 *
	 * @var mixed
	 */
	protected $data = null;

	/**
	 * local cache to store all the products groups, so on run-time they will be fetched from memory instead of from DB
	 *
	 * @var array
	 */
	protected static $entities = [];

	/**
	 * constructor
	 * set the data instance
	 *
	 * @param array $params array of parameters (products group key & time)
	 */
	public function __construct(array $params = []) {
		$time = isset($params['time']) ? $params['time'] : time();
		$disableProductgroupCache = isset($params['disable_productgroup_cache']) ? $params['disable_productgroup_cache'] : false;
		if (isset($params['data'])) {
			$this->data = $params['data'];
		} else if (isset($params['key'])) {
			$this->load($params['key'], $time, $disableProductgroupCache);
		}
	}

	/**
	 * load the products group revision active at the given time
	 *
	 * @param string $key the products group key
	 * @param int $time unix timestamp
	 * @param boolean $disableCache load from the DB instead of from the local cache
	 */
	protected function load($key, $time, $disableCache = false) {
		if ($disableCache) {
			$this->loadFromDb($key, $time);
			return;
		}
		$this->data = self::getEntityByKeyAndTime($key, $time);
	}

	/**
	 * load the products group from DB
	 *
	 * @param string $key the products group key
	 * @param int $time unix timestamp
	 */
	protected function loadFromDb($key, $time) {
		$queryTime = new Mongodloid_Date($time);
		$query = [
			'key' => $key,
			'from' => ['$lte' => $queryTime],
			'to' => ['$gt' => $queryTime],
		];
		$coll = self::getCollection();
		$this->data = $coll->query($query)->cursor()->limit(1)->current();
		$this->data->collection($coll);
	}

	/**
	 * method to pull the products group data
	 *
	 * @param string $name the property name; could be mongo key
	 *
	 * @return mixed the property value
	 */
	public function get($name) {
		return $this->data->get($name);
	}

	/**
	 * method to check whether the products group was found
	 *
	 * @return boolean true when there is no active revision of the products group
	 */
	public function isEmpty() {
		return is_null($this->data) || $this->data->isEmpty();
	}

	public static function getCollection() {
		return Billrun_Factory::db()->productgroupsCollection();
	}

	/**
	 * loads all the products groups to a static local variable
	 * these entities will be later use to fetch from the memory instead of from the DB
	 */
	public static function initEntities() {
		$coll = self::getCollection();
		$entities = $coll->query()->cursor();
		self::$entities = [];
		foreach ($entities as $entity) {
			$entity->collection($coll);
			self::$entities[$entity['key']][] = [
				'entity' => $entity,
				'from' => $entity['from'],
				'to' => $entity['to'],
			];
		}
	}

	/**
	 * get the local stored revisions of a products group
	 *
	 * @param string $key the products group key
	 * @return array the products group revisions
	 */
	public static function getEntities($key) {
		if (empty(self::$entities)) {
			self::initEntities();
		}
		return isset(self::$entities[$key]) ? self::$entities[$key] : [];
	}

	/**
	 * get the locally stored products group revision active at the given time
	 *
	 * @param string $key the products group key
	 * @param int $time unix timestamp
	 * @return Mongodloid_Entity the products group, empty when there is no active revision
	 */
	protected static function getEntityByKeyAndTime($key, $time) {
		foreach (self::getEntities($key) as $entityTimes) {
			$from = $entityTimes['from']->sec ?? $entityTimes['from'];
			$to = $entityTimes['to']->sec ?? $entityTimes['to'] ?? null;
			if ($from <= $time && (is_null($to) || $to > $time)) {
				return $entityTimes['entity'];
			}
		}
		return new Mongodloid_Entity([], self::getCollection());
	}

}
