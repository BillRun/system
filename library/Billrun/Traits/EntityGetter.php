<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Entity getter by given conditions (filters).
 * Supports priorities and complex conditions match
 */
trait Billrun_Traits_EntityGetter {
	
	protected static $entities = [];
	protected static $entitiesData = [];
	
	/**
	 * get filters for fetching the required entity/entities
	 * 
	 * @param array $row
	 * @param array $params
	 */
	protected abstract function getFilters($row = [], $params = []);
	
	/**
	 * get the collection to fetch the entity/entities from
	 * 
	 * @param array $params
	 */
	protected abstract function getCollection($params = []);
	
	/**
	 * get matching entities for all categories by the specific conditions of every category
	 * 
	 * @param array $row
	 * @param array $params
	 * @return mixed
	 */
	public function getMatchingEntitiesByCategories($row, $params = []) {
		$ret = [];
		$matchFilters = $this->getFilters($row, $params);
		$mustMatch = Billrun_Util::getIn($params, 'must_match', true);
		$skipCategories = Billrun_Util::getIn($params, 'skip_categories', []);
		
		if (empty($matchFilters)) {
			Billrun_Factory::log('No filters found for row ' . $row['stamp'] . ', params: ' . print_R($params, 1), Billrun_Log::WARN);
			return $this->afterEntityNotFound($row, $params);
		}

		foreach ($matchFilters as $category => $categoryFilters) {
			if (in_array($category, $skipCategories)) {
				continue;
			}
			
			$params['category'] = $category;
			$params['filters'] = $this->getCategoryFilters($categoryFilters, $row, $params);
			$params['default_fallback'] = $this->useDefaultFallback($ret, $category, $row, $params);
			$entity = $this->getMatchingEntity($row, $params);
			if ($entity) {
				$ret[$category] = $entity;
			} else if ($mustMatch) {
				return $this->afterEntityNotFound($row, $params);
			}
		}
		
		return $ret;
	}
	
	/**
	 * get matching entity by filters
	 * filters can be passed under params variable OR through getFilters function
	 * 
	 * @param array $row
	 * @param array $params
	 * @return Mongodloid_Entity if found, false otherwise
	 */
	public function getMatchingEntity($row, $params = []) {
		$filters = Billrun_Util::getIn($params, 'filters', $this->getFilters($row, $params));
		$category = Billrun_Util::getIn($params, 'category', '');
		$defaultFallback = Billrun_Util::getIn($params, 'default_fallback', '');
		
		if (empty($filters)) {
			Billrun_Factory::log('No category filters found for row ' . $row['stamp'] . '. category: ' . (Billrun_Util::getIn($params, 'category', '')) . ', filters: ' . print_R($categoryFilters, 1) . ', params: ' . print_R($params, 1), Billrun_Log::WARN);
			return $this->afterEntityNotFound($row, $params);
		}

		$entity = $this->getEntityByFilters($row, $filters, $params);
		
		if (empty($entity) && $defaultFallback) {
			$entity = $this->getDefaultEntity($filters, $category, $row, $params);
		}

		if (empty($entity)) {
			Billrun_Factory::log('Entity not found for row ' . $row['stamp'] . '. params: ' . print_R($params, 1), Billrun_Log::WARN);
			return $this->afterEntityNotFound($row, $params);
		}

		if (!$this->isEntityLegitimate($entity, $row, $params)) {
			Billrun_Factory::log('non-legitimate entity found for row ' . $row['stamp'] . '. entity: ' . print_R($entity, 1) . ', params: ' . print_R($params, 1), Billrun_Log::WARN);
			$params['entity'] = $entity;
			return $this->afterEntityNotFound($row, $params);
		}

		Billrun_Factory::log('Entity found for row ' . $row['stamp'], Billrun_Log::DEBUG);
		$this->afterEntityFound($row, $entity, $category, $params);
		return $entity;
	}
	
	/**
	 * get entity by given filters
	 * 
	 * @param array $row
	 * @param array $filters
	 * @param array $params
	 * @return Mongodloid_Entity if found, false otherwise
	 */
	protected function getEntityByFilters($row, $filters, $params = []) {
		$category = Billrun_Util::getIn($params, 'category', '');
		$matchedEntity = null;
		foreach ($filters as $priority) {
			$currentPriorityFilters = Billrun_Util::getIn($priority, 'filters', $priority);
			$params['cache_db_queries'] = Billrun_Util::getIn($priority, 'cache_db_queries', false);
			$query = $this->getEntityQuery($row, $currentPriorityFilters, $category, $params);
			
			if (!$query) {
				Billrun_Factory::log('Cannot get query for row ' . $row['stamp'] . '. filters: ' . print_R($currentPriorityFilters, 1) . ', params: ' . print_R($params, 1), Billrun_Log::DEBUG);
				continue;
			}
			
			Billrun_Factory::dispatcher()->trigger('extendEntityParamsQuery', [&$query, &$row, &$this, $params]);
			
			$matchedEntity = $this->getEntity($row, $query, $params);
			if ($matchedEntity && !$matchedEntity->isEmpty()) {
				break;
			}
		}

		if (empty($matchedEntity) || $matchedEntity->isEmpty()) {
			return false;
		}

		return $this->getFullEntityData($matchedEntity, $row, $params);
	}
	
	/**
	 * should use cache to get the entity
	 * 
	 * @param array $params
	 * @return boolean
	 */
	protected function shouldCacheEntity($params = []) {
		return !empty($params['cache_db_queries']);
	}
	
	/**
	 * get keys to remove from the query to get the cache key
	 * 
	 * @param array $row
	 * @param array $filters
	 * @param array $params
	 * @return array of keys
	 */
	protected function getEntityCacheKeyFieldsToRemove($row, $query, $params = []) {
		return ['from', 'to'];
	}
	
	/**
	 * get entity cache key
	 * 
	 * @param array $row
	 * @param array $filters
	 * @param array $params
	 * @return string
	 */
	protected function getEntityCacheKey($row, $query, $params = []) {
		$keysToRemove = $this->getEntityCacheKeyFieldsToRemove($row, $query, $params);
		foreach ($query as $i => &$pipelineStage) {
			foreach ($pipelineStage as $op => &$pipeline) {
				foreach ($pipeline as $key => $val) {
					if (in_array($key, $keysToRemove)) {
						unset($pipeline[$key]);
					}
				}

				if (empty($pipeline)) {
					unset($pipelineStage[$op]);
				}
			}

			if (empty($pipelineStage)) {
				unset($query[$i]);
			}
		}
		return md5(serialize($query));
	}
	
	/**
	 * get entity data cache key
	 * 
	 * @param array $row
	 * @param array $filters
	 * @param array $params
	 * @return string
	 */
	protected function getEntityDataCacheKey($entity, $row = [], $params = []) {
		return strval($entity->getRawData()['_id']['_id']);
	}
	
	/**
	 * get entity from internal cache or DB
	 * 
	 * @param array $row
	 * @param array $query
	 * @param array $params
	 * @return Mongodloid entity if found, false or empty Mongodloid otherwise
	 */
	protected function getEntity($row, $query, $params = []) {
		$useCache = $this->shouldCacheEntity($params);
		$cacheKey = $useCache ? $this->getEntityCacheKey($row, $query, $params) : '';
		$entity = false;
		
		if ($useCache && !empty(self::$entities[$cacheKey])) {
			$time = isset($row['urt']) ? $row['urt']->sec : time();
			foreach (self::$entities[$cacheKey] as $cachedEntity) {
				if ($cachedEntity['from'] <= $time && (!isset($cachedEntity['to']) || is_null($cachedEntity['to']) || $cachedEntity['to'] >= $time)) {
					$entity = $cachedEntity['entity'];
					break;
				}
			}
		}
		
		if (empty($entity)) {
			$coll = $this->getCollection($params);
			$entity = $coll->aggregate($query)->current();
			if ($useCache && isset($entity['from']) && isset($entity['to'])) {
				self::$entities[$cacheKey][] = [
					'entity' => $entity,
					'from' => $entity['from']->sec,
					'to' => $entity['to']->sec,
				];
			}
		}
		
		return $entity;
	}
	
	/**
	 * Builds aggregate query from configuration
	 * 
	 * @return array Mongo query
	 */
	protected function getEntityQuery($row, $filters, $category = '', $params = []) {
		$match = $this->getBasicMatchQuery($row, $category, $params);
		$additional = [];
		$group = $this->getBasicGroupQuery($row, $category, $params);
		$additionalAfterGroup = [];
		$sort = $this->getBasicSortQuery($row, $category, $params);
		$entityKeyInCondition = $this->getConditionEntityKey($params);

		foreach ($filters as $filter) {
			$filter['entity_key'] = $filter[$entityKeyInCondition];
			$handlerClass = Billrun_EntityGetter_Filters_Manager::getFilterHandler($filter);
			if (!$handlerClass) {
				Billrun_Factory::log('getEntityQuery: cannot find filter hander. details: ' . print_r($filter, 1));
				continue;
			}
			
			$handlerClass->updateQuery($match, $additional, $group, $additionalAfterGroup, $sort, $row);
			if (!$handlerClass->canHandle()) {
				return false;
			}
		}

		$matchQuery = [['$match' => $match]];
		$sortQuery = !empty($sort) ? [['$sort' => $sort]] : [];
		$groupQuery = [['$group' => $group]];
		$limitQuery = [['$limit' => 1]];
		
		return array_merge($matchQuery, $additional, $groupQuery, $additionalAfterGroup, $sortQuery, $limitQuery);
	}
	
	/**
	 * build basic match Mongo query for aggregation
	 * 
	 * @param array $row
	 * @param string $category
	 * @param array $params
	 * @return array
	 */
	protected function getBasicMatchQuery($row, $category, $params = []) {
		$sec = $row['urt']->sec;
		$usec = $row['urt']->usec;
		return Billrun_Utils_Mongo::getDateBoundQuery($sec, false, $usec);
	}
	
	/**
	 * build basic group Mongo query for aggregation
	 * 
	 * @param array $row
	 * @param string $category
	 * @param array $params
	 * @return array
	 */
	protected function getBasicGroupQuery($row, $category, $params = []) {
		return [
			'_id' => [
				'_id' => '$_id',
			],
			'from' => [
				'$first' => '$from',
			],
			'to' => [
				'$first' => '$to',
			],
		];
	}
	
	/**
	 * build basic sort Mongo query for aggregation
	 * 
	 * @param array $row
	 * @param string $category
	 * @param array $params
	 * @return array
	 */
	protected function getBasicSortQuery($row, $category, $params = []) {
		return [];
	}
	
	/**
	 * get filters for the specific category
	 * 
	 * @param array $categoryFilters
	 * @param array $row
	 * @param array $params
	 * @return array
	 */
	protected function getCategoryFilters($categoryFilters, $row = [], $params = []) {
		if (isset($categoryFilters['priorities'])) {
			return $categoryFilters['priorities'];
		}
		return !empty($categoryFilters) ? $categoryFilters : [];
	}
	
	/**
	 * checks if a default entity should be used as fallback in the category
	 * 
	 * @param array $categoryFilters
	 * @param string $category
	 * @param array $row
	 * @param array $params
	 * @return boolean
	 */
	protected function useDefaultFallback($categoryFilters, $category = '', $row = [], $params = []) {
		return Billrun_Util::getIn($categoryFilters, 'default_fallback', false);
	}

	/**
	 * get default entity for the category
	 * 
	 * @param array $categoryFilters
	 * @param string $category
	 * @param array $row
	 * @param array $params
	 * @return Entity
	 */
	protected function getDefaultEntity($categoryFilters, $category = '', $row = [], $params = []) {
		return null;
	}
	
	/**
	 * return whether or not the entity founded is legitimate
	 * 
	 * @param array $entity
	 * @param array $row
	 * @param array $params
	 * @return boolean
	 */
	protected function isEntityLegitimate($entity, $row = [], $params = []) {
		return !empty($entity);
	}
	
	/**
	 * handles the case of entity found
	 * 
	 * @param array $row
	 * @param array $entity
	 * @param string $category
	 * @param array $params
	 */
	protected function afterEntityFound(&$row, $entity, $category = '', $params = []) {
	}
	
	/**
	 * handles the case of entity not found
	 * 
	 * @param array $row
	 * @param array $params
	 */
	protected function afterEntityNotFound($row = [], $params = []) {
		$ret = false;
		Billrun_Factory::dispatcher()->trigger('afterEntityNotFound', [&$row, $params, $this, &$ret]);
		return $ret;
	}
	
	/**
	 * get the key of the entity key in the config
	 * 
	 * @todo this is required because currently rate calculator in the input processor is saving rate_key as the key
	 * @param array $params
	 * @return string
	 */
	protected function getConditionEntityKey($params = []) {
		return 'entity_key';
	}
	
	/**
	 * get full entity data, since the aggregation will only return some of the data
	 * 
	 * @param array $entity
	 * @param array $row
	 * @param array $params
	 * @return Mongodloid_Entity if found, false otherwise
	 */
	protected function getFullEntityData($entity, $row = [], $params = []) {
		$cacheKey = $this->getEntityDataCacheKey($entity, $row, $params);
		if (empty(self::$entitiesData[$cacheKey])) {
			$rawEntity = $entity->getRawData();
			$query = $this->getFullEntityDataQuery($rawEntity);
			if (!$query) {
				return false;
			}

			$coll = $this->getCollection($params);
			self::$entitiesData[$cacheKey] = $coll->query($query)->cursor()->current();
		}
		
		return self::$entitiesData[$cacheKey];
	}
	
	/**
	 * gets the query that will be used in getFullEntityData
	 * 
	 * @param array $rawEntity
	 * @return array
	 */
	protected function getFullEntityDataQuery($rawEntity) {		
		if (!isset($rawEntity['_id']['_id']) || !($rawEntity['_id']['_id'] instanceof MongoId)) {
 			return false;	
 		}
		
		return [
 			'_id' => $rawEntity['_id']['_id'],
 		];
	}

}
