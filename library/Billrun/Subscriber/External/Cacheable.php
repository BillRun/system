<?php

trait Billrun_Subscriber_External_Cacheable {
	private $cacheEnabled = true;
	private $cachingTTL = 300;

	/**
	 * @return string
	 */
	abstract public static function getCachingEntityIdKey();

	/**
	 * @param $id
	 * @return void
	 */
	public static function cleanExternalCache($id = false) {

		$cache = Billrun_Factory::cache();
		if($cache) {
			if( false !== $id) {
				$tagKey = static::buildCacheTagKey($id);
				$cacheKeysList = $cache->get($tagKey, static::getCacheTagPrefix());

				foreach ($cacheKeysList as $cacheKey) {
					$cache->remove($cacheKey, static::getCachePrefix());
				}

				$cache->remove($tagKey, static::getCacheTagPrefix());
			} else {
				$cache->clean();
			}
		}

		return true;
	}

	/**
	 * @return bool
	 */
	protected function isCacheEnabled(): bool {
		return $this->cacheEnabled;
	}

	/**
	 * @param bool $cacheEnabled
	 */
	public function setCacheEnabled(bool $cacheEnabled) {
		$this->cacheEnabled = $cacheEnabled;
	}

	/**
	 * @return int
	 */
	protected function getCachingTTL(): int {
		return $this->cachingTTL;
	}

	/**
	 * @param int $cachingTTL
	 */
	public function setCachingTTL(int $cachingTTL) {
		$this->cachingTTL = $cachingTTL;
	}

	/**
	 * @return string
	 */
	protected static function getCachePrefix(): string {
		return 'external_subscriber_';
	}

	/**
	 * @return string
	 */
	private static function getCacheTagPrefix() {
		return static::getCachePrefix() . '_tag_';
	}

	private static function buildCacheTagKey($entityId) {
		return md5(serialize([static::getCachingEntityIdKey() => $entityId]));
	}

	private function tagCache($cacheKey, $cachedEntries) {
		$cache = Billrun_Factory::cache();
		foreach ($cachedEntries as $cachedEntry) {
			$id = $cachedEntry[static::getCachingEntityIdKey()];

			if (!$id) {
				continue;
			}

			$tagKey = static::buildCacheTagKey($id);
			$cacheKeysList = $cache->get($tagKey, static::getCacheTagPrefix());

			if (!is_array($cacheKeysList)) {
				$cacheKeysList = [];
			}

			if (!in_array($cacheKey, $cacheKeysList)) {
				$cacheKeysList[] = $cacheKey;
			}
			$cache->set($tagKey, $cacheKeysList, static::getCacheTagPrefix());
		}
	}

	private function buildExternalDataCacheKey(array $query): string {
		$keyFields = $query['params'];
		uasort($keyFields, function($a, $b) {
			return $a['key'] != $b['key']
				? strcmp($a['key'], $b['key']) 				// sort by keys
				: strcmp($a['operator'], $b['operator']);	// if keys are equal compare operator
		});

		// sort values as well
		array_walk($keyFields, function($value, $key) {
			if (is_array($value['value'])) {
				sort($value['value']);
			}
			return $value;
		});
		$keyFields = array_values($keyFields);

		return md5(serialize($keyFields));
	}

	private function getCachedExternalEntry(string $cacheKey, int $time) {
		$cache = Billrun_Factory::cache();

		$cachedEntries = $cache->get($cacheKey, $this->getCachePrefix());

		if (!is_array($cachedEntries)) {
			return null;
		}

		$needToUpdateCache = false;

		$returnEntry = null;

		foreach ($cachedEntries as $key => $cachedEntry) {
			if ($cachedEntry['expire_time'] < time()) {
				unset($cachedEntries[$key]); // remove expired entry
				$needToUpdateCache = true;
				continue; // ignore expired entries
			}

			$fromTime = strtotime($cachedEntry['from']);
			$toTime = strtotime($cachedEntry['to']);

			if($fromTime < $time && $time < $toTime) {
				unset($cachedEntry['expire_time']);
				$returnEntry = $cachedEntry;
				break; // found an entry for the specified time
			}
		}

		if ($needToUpdateCache) {
			$cache->set($cacheKey, $cachedEntries, $this->getCachePrefix());
		}

		return $returnEntry;
	}


	private function filterResultsPerQuery(array $results, array $query) : array {
		$queryResults = [];
		foreach ($results as $entry) {
			if ($query['id'] == $entry['id']) {
				$queryResults[] = $entry;
			}
		}
		return $queryResults;
	}

	private function removeCacheDuplicates($queryResults, $cachedEntries) {
		$filteredResults = [];

		foreach($queryResults as $entry) {
			unset($entry['id']); // clean up from the query ID before the check
			$unique = true;

			foreach($cachedEntries as $cachedEntry) {
				unset($cachedEntry['expire_time']);
				if ($entry === $cachedEntry) {
					$unique = false;
				}
			}
			if ($unique) {
				$filteredResults[] = $entry;
			}
		}
		return $filteredResults;
	}

	public function cacheExternalData(array $externalQuery, array $results) {
		$cache = Billrun_Factory::cache();

		$queries = $externalQuery['query'] ?? [];

		foreach($queries as $query) {

			$time = $query['time'] ?? $externalQuery['time'];

			// cache queries if the time parameter is specified
			if (empty($time) || empty($query['params'])) {
				continue;
			}

			$queryResults = $this->filterResultsPerQuery($results, $query);

			if (empty($queryResults)) {
				continue;
			}

			$cacheKey = $this->buildExternalDataCacheKey($query);
			$cachedEntries = $cache->get($cacheKey, $this->getCachePrefix());

			if (!is_array($cachedEntries)) {
				$cachedEntries = [];
			}

			$queryResults = $this->removeCacheDuplicates($queryResults, $cachedEntries);

			if (empty($queryResults)) {
				continue;
			}

			$expireTime = time() + $this->cachingTTL;

			foreach($queryResults as &$entry) {
				$entry['expire_time'] = $expireTime;
			};

			$cachedEntries = array_merge($cachedEntries, $queryResults);

			$cache->set($cacheKey, $cachedEntries, $this->getCachePrefix());
			$this->tagCache($cacheKey, $cachedEntries);
		}
	}

	private function getCachedExternalEntries(array $externalQuery): array {
		$cachedEntries = [];

		if (!empty($externalQuery['query'])) {
			foreach ($externalQuery['query'] as $key => $query) {
				$cacheKey = $this->buildExternalDataCacheKey($query);
				$time = $query['time'] ?? $externalQuery['time'];

				// ignore the limit parameter and assume that the time parameter always means limit 1
				$cachedEntry = $time ? $this->getCachedExternalEntry($cacheKey, strtotime($time)) : null;

				if ($cachedEntry !== null) {
					// set the query ID to match the cached result with the query
					$cachedEntry['id'] = $query['id'];
					$cachedEntries[] = $cachedEntry;
					unset($externalQuery['query'][$key]);
				}
			}
			$externalQuery['query'] = array_values($externalQuery['query']); // reset indexes
		}

		return [$cachedEntries, $externalQuery];
	}

	private function loadCache(array $requestParams, callable $fallback) {
		if (!$this->cacheEnabled) {
			return $fallback($requestParams);
		}

		list($cachedEntries, $requestParams) = $this->getCachedExternalEntries($requestParams);

		$results = [];

		if (!empty($requestParams['query'])) {
			$results = $fallback($requestParams);

			$this->cacheExternalData($requestParams, $results);
		}

		return array_merge($results, $cachedEntries);
	}
}
