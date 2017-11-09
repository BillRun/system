<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Static functions providing general usage CDR functionality
 *
 */
class Billrun_Utils_Usage {

	static public function retriveEntityFromUsage($row, $entityType) {
		$entityQueryData = array();
		$cache = Billrun_Factory::cache();
		switch ($entityType) {
			case 'subscriber' :
				$entityQueryData['collection'] = 'subscribers';
				$entityQueryData['query'] = array_merge(array('sid' => $row['sid']), Billrun_Utils_Mongo::getDateBoundQuery($row['urt']->sec));
				$entityQueryData['sort'] = array('from' => -1);
				break;
			case 'plan' :
				$entityQueryData['collection'] = 'plans';
				$entityQueryData['query'] = array_merge(array('name' => $row['plan']), Billrun_Utils_Mongo::getDateBoundQuery($row['urt']->sec));
				$entityQueryData['sort'] = array('from' => -1);

				break;
//			case 'service' :
//				TODO find what to do with multiple possible values
//				break;
			case 'product' :
				$entityQueryData['collection'] = 'rates';
				$entityQueryData['query'] = array_merge(array('_id' => $row['arate']['$id']), Billrun_Utils_Mongo::getDateBoundQuery($row['urt']->sec));
				break;

			default:
				Billrun_Factory::log("Forgin entity type {$entityType} isn't supported.", Zend_Log::ERR);
				return null;
		}
		$cachHash = Billrun_Util::generateArrayStamp($entityQueryData);
		if (!empty($cache) && Billrun_Factory::cache()->exists($cachHash)) {
			return Billrun_Factory::cache()->get($cachHash);
		}

		$cursor = Billrun_Factory::db()->getCollection($entityQueryData['collection'])->query($entityQueryData['query'])->cursor()->limit(1);
		if (!empty($entityQueryData['sort'])) {
			$cursor->sort($entityQueryData['sort']);
		}
		$entity = $cursor->current();
		if ($entity && !empty($cache)) {
			Billrun_Factory::cache()->set($cachHash, $entity);
		}

		return $entity;
	}

}
