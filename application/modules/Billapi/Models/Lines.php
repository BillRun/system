<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for lines entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Lines extends Models_Entity {

	/**
	 * 
	 * @see Models_Entity::canEntityBeDeleted
	 */
	protected function canEntityBeDeleted() {
		$linesCollection = Billrun_Factory::db()->linesCollection();;
		$query = array_merge($this->query, $this->getCanBeDeletedQuery());
		return !$linesCollection->query($query)->cursor()->current()->isEmpty();
	}
	
	/**
	 * Lines entity can be delete only if:
	 *   - line is of type "credit"
	 *   - line is not in queue
	 *   - billrun is greater than or equal to the runtime billrun key
	 * 
	 * @return array
	 */
	protected function getCanBeDeletedQuery() {
		return array(
			'type' => 'credit',
			'$and' => array(
				array('$or' => array(
					array('in_queue' => false),
					array('in_queue' => array('$exists' => false)),
					),
				),
				array('$or' => array(
					array('billrun' => array('$gte' => Billrun_Billrun::getActiveBillrun())),
					array('billrun' => array('$exists' => false)),
					)
				),
			),
		);
	}
	
	/**
	 * Performs a delete from the DB by a query
	 * we are overriding to protect vertical remove
	 * 
	 * @param array $query
	 */
	protected function remove($query) {
		return $this->collection->remove($query, array('w' => 1, 'justOne' => true));
	}

}
