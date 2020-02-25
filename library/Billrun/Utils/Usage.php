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

	static public function retriveEntityFromUsage($row, $entityType, $foreignFieldConfig = array()) {
		$entityQueryData = array();
		$isSingleEntity = true;
		if (!self::conditionsMet($foreignFieldConfig, $row)) {
			return null;
		}
//		TODO  added the cache after complete testing is done for the cache
//		$cache = Billrun_Factory::cache();
		
		switch ($entityType) {
			case 'subscriber' :
				if(empty($row['sid'])) {
					return null;
				}
				$entityQueryData['collection'] = 'subscribers';
				$entityQueryData['query'] = array('sid' => $row['sid'], 'aid'=> $row['aid']);
				$entityQueryData['sort'] = array('from' => -1);
				break;
			case 'account' :
				if(empty($row['aid'])) {
					return null;
				}
				$entityQueryData['collection'] = 'subscribers';
				$entityQueryData['query'] = array('aid' => $row['aid'],'type' => 'account' );
				$entityQueryData['sort'] = array('from' => -1);
				break;			
			
			case 'plan' :
				if(empty($row['plan'])) {
					return null;
				}
				$entityQueryData['collection'] = 'plans';
				$entityQueryData['query'] = array('name' => $row['plan']);
				$entityQueryData['sort'] = array('from' => -1);

				break;
//			case 'service' :
//				TODO find what to do with multiple possible values
//				break;
			case 'product' :
				if(empty($row['arate'])) {
					return null;
				}
				$entityQueryData['collection'] = 'rates';
				$entityQueryData['query'] = array('_id' => $row['arate']['$id']);
				break;
				
			case 'account_subscribers':
				$entityQueryData['collection'] = 'subscribers';
				$entityQueryData['query'] = array('type' => 'subscriber', 'aid' => $row['aid'], 'sid' => array('$ne' => $row['sid']),
											'from' => array('$lt' => new MongoDate()), 'to' => array('$gt' => new MongoDate()));
				$isSingleEntity = false;
				break;

			default:
				Billrun_Factory::log("Foreign entity type {$entityType} isn't supported.", Zend_Log::DEBUG);
				return null;
		}
		$cachHash = Billrun_Util::generateArrayStamp($entityQueryData);
//		TODO  added the cache after  complete testing is done
//		if (!empty($cache) && ($cachedValue = Billrun_Factory::cache()->get($cachHash)) ) {
//			return $cachedValue;
//		}

		$timeBoundingQuery = Billrun_Utils_Mongo::getDateBoundQuery(Billrun_Util::getFirstValueIn($row,Billrun_Util::getFieldVal($foreignFieldConfig['query_time_fields'],["end", "urt"]), $row['urt'])->sec);
		
		$cursor = Billrun_Factory::db()->getCollection($entityQueryData['collection'])->query(array_merge($entityQueryData['query'],$timeBoundingQuery))->cursor();
		if (!empty($entityQueryData['sort'])) {
			$cursor->sort($entityQueryData['sort']);
		}
		if ($isSingleEntity) {
			$cursor = $cursor->limit(1);
			$entity = $cursor->current();
			//fall back to none time bounded query if no entity found.
			if( (empty($entity) || $entity->isEmpty()) 
				&& !empty($foreignFieldConfig['no_time_bounding'])) {
					$cursor = Billrun_Factory::db()->getCollection($entityQueryData['collection'])->query($entityQueryData['query'])->cursor()->limit(1);
				if (!empty($entityQueryData['sort'])) {
					$cursor->sort($entityQueryData['sort']);
				}
				$entity = $cursor->current();
			}
		}
		else {
			foreach (iterator_to_array($cursor) as $document) {
				$entity[] = $document;
			}
		}
//		if ($entity && !empty($cache)) {
//			Billrun_Factory::cache()->set($cachHash, $entity);
//		}

		return (empty($entity)) || (($entity instanceof Mongodloid_Entity) && $entity->isEmpty())? null : $entity;
	}
	
	public static function conditionsMet($fieldConf, $rowData) {
		$conditionsMet = true;
		if (!empty($conditions = @$fieldConf['conditions'])) {
			foreach($conditions as $condition) {
				$conditionsMet &= Billrun_Util::isConditionMet($rowData, $condition);
			}
		}
		return $conditionsMet;
	}

}
