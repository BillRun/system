<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing rate class
 *
 * @package  Rate
 * @since    0.5
 */
class Billrun_DBRef {

	protected static $entities;
	protected static $keys = array(
		'rates' => 'key',
		'plans' => 'name',
	);

	protected static function initCollection($collection_name) {
		if (isset(self::$keys[$collection_name])) {
			$coll = Billrun_Factory::db()->{$collection_name . "Collection"}();
			$resource = $coll->query()->cursor();
			foreach ($resource as $entity) {
				$entity->collection($coll);
				self::$entities[$collection_name]['by_id'][strval($entity->getId())] = $entity;
				self::$entities[$collection_name]['by_key'][$entity[self::$keys[$collection_name]]][] = $entity;
			}
		}
	}

	/**
	 * 
	 * @param type $db_ref
	 * @param type $time
	 */
	public static function getEntity($db_ref) {
		$matched_entity = null;
		$collection_name = $db_ref['$ref'];
		if (!isset(self::$entities[$collection_name]) && isset(self::$keys[$collection_name])) {
			self::initCollection($collection_name);
		}
		$id = strval($db_ref['$id']);
		if (isset(self::$entities[$collection_name]['by_id'][$id])) {
			$matched_entity = self::$entities[$collection_name]['by_id'][$id];
		}
		return $matched_entity;
	}

}
