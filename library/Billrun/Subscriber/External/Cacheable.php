<?php

trait Billrun_Subscriber_External_Cacheable {
	protected $enableCaching = true;
	protected $cachePrefix = 'external_subscriber_';
	protected $cachingTTL = 300;

	/**
	 * @return bool
	 */
	public function isEnableCaching(): bool {
		return $this->enableCaching;
	}

	/**
	 * @param bool $enableCaching
	 */
	public function setEnableCaching(bool $enableCaching) {
		$this->enableCaching = $enableCaching;
	}

	/**
	 * @return string
	 */
	public function getCachePrefix(): string {
		return $this->cachePrefix;
	}

	/**
	 * @param string $cachePrefix
	 */
	public function setCachePrefix(string $cachePrefix) {
		$this->cachePrefix = $cachePrefix;
	}

	/**
	 * @return int
	 */
	public function getCachingTTL(): int {
		return $this->cachingTTL;
	}

	/**
	 * @param int $cachingTTL
	 */
	public function setCachingTTL(int $cachingTTL) {
		$this->cachingTTL = $cachingTTL;
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

		return md5(serialize($keyFields));
	}

	private function getCachedExternalEntry(string $cacheKey, int $time) {
		$cache = Billrun_Factory::cache();

		$cachedEntries = $cache->get($cacheKey, $this->cachePrefix);

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
			$cache->set($cacheKey, $cachedEntries, $this->cachePrefix);
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

	private function cacheExternalData(array $externalQuery, array $results) {
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
			$cachedEntries = $cache->get($cacheKey, $this->cachePrefix);

			if (!is_array($cachedEntries)) {
				$cachedEntries = [];
			}

			$expireTime = time() + $this->cachingTTL;

			foreach($queryResults as &$entry){
				$entry['expire_time'] = $expireTime;
			};

			$cachedEntries = array_merge($cachedEntries, $queryResults);

			$cache->set($cacheKey, $cachedEntries, $this->cachePrefix);
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
					$cachedEntries[] = $cachedEntry;
					unset($externalQuery['query'][$key]);
				}
			}
		}

		return [$cachedEntries, $externalQuery];
	}

	private function loadCache(array $requestParams, callable $fallback) {
		if (!$this->enableCaching) {
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