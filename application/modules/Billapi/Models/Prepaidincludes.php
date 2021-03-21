<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi PrepaidIncludes model for prepaidincludes entity
 *
 * @package  Billapi
 * @since    5.5
 */
class Models_Prepaidincludes extends Models_Entity {

	/**
	 * See parent::duplicateCheck
	 * Check if one of the unique fields exists (instead of all of them, like in the parent's function)
	 */
	protected function duplicateCheck($data, $ignoreIds = array()) {
		$query = array('$or' => array());
		foreach (Billrun_Util::getFieldVal($this->config['duplicate_check'], []) as $fieldName) {
			$query['$or'][] = array(
				$fieldName => $data[$fieldName],
			);
		}

		if (empty($query['$or'])) {
			unset($query['$or']);
		}
		
		if ($ignoreIds) {
			$query['_id'] = array(
				'$nin' => $ignoreIds,
			);
		}
		return $query ? !$this->collection->query($query)->count() : TRUE;
	}

}
