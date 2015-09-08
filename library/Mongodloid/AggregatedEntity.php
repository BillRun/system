<?php // 
/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Class representing an aggregated result entity.
 *
 * @package  Mongoldoid
 * @since    2.8
 */
//class Mongodloid_AggregatedEntity extends Mongodloid_Entity {
//	
//	static $GROUP_BY_IDENTIFIER = 'group_by';
//	
//	/**
//	 * Construct a new instance of the aggregated entity object.
//	 * @param Mongodloid_Entity $entity - Entity to create an aggregated result by.
//	 * @param array $groupKeys - Array of keys the entity is grouped by.
//	 */
//	public function __construct($entity, $groupKeys) {
//		parent::__construct($entity->getRawData(), $entity->collection());
//		
//		foreach ($groupKeys as $key) {
//			$values = $this->getRawData();
//			// TODO: The 'group_by' constant should perheps move to a more fitting location.
//			$this->set(self::$GROUP_BY_IDENTIFIER . '.' . $key,$values['_id'][$key], true);
//		}
//		$this['_id'] = new MongoId();
//	}
//}
