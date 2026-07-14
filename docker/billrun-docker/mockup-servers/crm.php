<?php
/**
 * Lightweight CRM mock used by the docker environment.
 * - GAD endpoint returns account data for the requested aid.
 * - GSD endpoint returns subscriber data for the requested aid/sid/sub_number.
 * - Billable endpoint returns paged billable data across CRM fixtures.
 * The endpoints read CRM fixtures from crm_data/<aid>.json and shape the payload
 * to mimic the original CRM behavior for tests and local development.
 */

// Fixtures are json_decoded whole into memory per request; large ones (e.g. 300K
// subscribers) exhaust the default 128M limit, so raise the cap to 16G for this
// mock server (a fixed ceiling rather than unlimited, to still bound runaway loads).
ini_set('memory_limit', '16384M');
$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

$aid = extractAid($payload);
$sid = extractSid($payload);
$subNumber = extractSubNumber($payload);

if ($aid === null && $subNumber !== null && ctype_digit($subNumber)) {
	$aid = (int) $subNumber;
}

if (preg_match('/\/gad/', $_SERVER["REQUEST_URI"])) {
	$accounts = resolveGadAccounts($payload, $aid);
	echo json_encode($accounts);
} elseif (preg_match('/\/gsd/', $_SERVER["REQUEST_URI"])) {
	$subscribers = resolveGsdSubscribers($payload, $aid, $sid, $subNumber);
	echo json_encode($subscribers);
} elseif (preg_match('/\/billable/', $_SERVER["REQUEST_URI"])) {
	$billable = resolveBillable($payload);
	echo json_encode($billable);
} else {
	// unsupported endpoint
}

/**
 * Resolve the requested aid from POST/GET arrays or JSON payload.
 * Supports the aggregator query structure where params contain aid.
 */
function extractAid($payload) {
	foreach ([ $_POST, $_GET ] as $source) {
		foreach (['aids', 'aid'] as $key) {
			if (isset($source[$key])) {
				return (int) $source[$key];
			}
		}
	}

	if (!is_array($payload)) {
		return null;
	}

	foreach (['aids', 'aid'] as $directKey) {
		if (isset($payload[$directKey])) {
			return (int) $payload[$directKey];
		}
	}

	if (!empty($payload['query']) && is_array($payload['query'])) {
		foreach ($payload['query'] as $query) {
			if (empty($query['params']) || !is_array($query['params'])) {
				continue;
			}

				foreach ($query['params'] as $param) {
					if (isset($param['key'], $param['value']) && $param['key'] === 'aid') {
						$aidValues = normalizeQueryValues($param['value']);
						if (!empty($aidValues)) {
							return (int) $aidValues[0];
						}
					}
				}
			}
		}

	return null;
}

/**
 * Resolve the requested sid from POST/GET arrays or JSON payload.
 * Mirrors extractAid but looks for subscriber identifiers.
 */
function extractSid($payload) {
	foreach ([ $_POST, $_GET ] as $source) {
		foreach (['sid', 'sids'] as $key) {
			if (isset($source[$key])) {
				return (int) $source[$key];
			}
		}
	}

	if (!is_array($payload)) {
		return null;
	}

	foreach (['sid', 'sids'] as $directKey) {
		if (isset($payload[$directKey])) {
			return (int) $payload[$directKey];
		}
	}

	if (!empty($payload['query']) && is_array($payload['query'])) {
		foreach ($payload['query'] as $query) {
			if (empty($query['params']) || !is_array($query['params'])) {
				continue;
			}

			foreach ($query['params'] as $param) {
				if (isset($param['key'], $param['value']) && $param['key'] === 'sid') {
					return (int) $param['value'];
				}
			}
		}
	}

	return null;
}

/**
 * Resolve the requested sub_number from POST/GET arrays or JSON payload.
 * Supports the aggregator query structure where params contain sub_number.
 */
function extractSubNumber($payload) {
	foreach ([ $_POST, $_GET ] as $source) {
		if (isset($source['sub_number'])) {
			return (string) $source['sub_number'];
		}
	}

	if (!is_array($payload)) {
		return null;
	}

	if (isset($payload['sub_number'])) {
		return (string) $payload['sub_number'];
	}

	if (!empty($payload['query']) && is_array($payload['query'])) {
		foreach ($payload['query'] as $query) {
			if (empty($query['params']) || !is_array($query['params'])) {
				continue;
			}

			foreach ($query['params'] as $param) {
				if (isset($param['key'], $param['value']) && $param['key'] === 'sub_number') {
					return (string) $param['value'];
				}
			}
		}
	}

	return null;
}

/**
 * Load the CRM fixture for the given aid.
 * Returns an empty string when aid is missing or file unreadable.
 */
function loadAidData($aid) {
	if ($aid === null) {
		return '';
	}

	return loadAidDataByKey((string) $aid);
}

/**
 * Load the CRM fixture by aid-like file key.
 */
function loadAidDataByKey($aidKey) {
	$aidKey = trim((string) $aidKey);
	if ($aidKey === '' || !preg_match('/^\d+$/', $aidKey)) {
		return '';
	}

	$filePath = "crm_data/{$aidKey}.json";
	if (!is_readable($filePath)) {
		return '';
	}

	return file_get_contents($filePath, true);
}

/**
 * Return billable payload with pagination and optional aids filtering.
 */
function resolveBillable($payload) {
	$page = max(0, extractRequestInt($payload, 'page', 0));
	$size = extractRequestInt($payload, 'size', 100);
	if ($size <= 0) {
		$size = 100;
	}

	$requestedAids = extractBillableAids($payload);
	$availableAids = listBillableAids();
	if (!empty($requestedAids)) {
		$requestedAidSet = array_fill_keys($requestedAids, true);
		$selectedAids = array_values(array_filter($availableAids, function ($aidKey) use ($requestedAidSet) {
			return isset($requestedAidSet[$aidKey]);
		}));
	} else {
		$selectedAids = $availableAids;
	}

	$totalAids = count($selectedAids);
	$offset = $page * $size;
	$pagedAids = $offset < $totalAids ? array_slice($selectedAids, $offset, $size) : [];

	$data = [];
	$billableAccounts = [];
	foreach ($pagedAids as $aidKey) {
		$rawData = loadAidDataByKey($aidKey);
		if ($rawData === '') {
			continue;
		}

		$decoded = json_decode($rawData, true);
		if (!is_array($decoded)) {
			continue;
		}

		if (!empty($decoded['data']) && is_array($decoded['data'])) {
			foreach ($decoded['data'] as $entry) {
				$data[] = $entry;
			}
		}

		if (!empty($decoded['billableAccounts']) && is_array($decoded['billableAccounts'])) {
			$billableAccounts = array_replace_recursive($billableAccounts, $decoded['billableAccounts']);
		}
	}

	$totalPages = $size > 0 ? (int) ceil($totalAids / $size) : 0;
	return [
		'status' => 1,
		'data' => $data,
		'options' => [
			'page' => $page,
			'size' => $size,
			'total' => $totalAids,
			'total_pages' => $totalPages,
			'returned' => count($pagedAids),
			'start_date' => extractRequestValue($payload, 'start_date'),
			'end_date' => extractRequestValue($payload, 'end_date'),
		],
		'billableAccounts' => $billableAccounts,
	];
}

/**
 * Return request value from POST/GET first, then JSON payload.
 */
function extractRequestValue($payload, $key, $default = null) {
	foreach ([ $_POST, $_GET ] as $source) {
		if (array_key_exists($key, $source)) {
			return $source[$key];
		}
	}

	if (is_array($payload) && array_key_exists($key, $payload)) {
		return $payload[$key];
	}

	return $default;
}

/**
 * Extract numeric request value with fallback.
 */
function extractRequestInt($payload, $key, $default) {
	$value = extractRequestValue($payload, $key, $default);
	return is_numeric($value) ? (int) $value : (int) $default;
}

/**
 * Parse aids filter from request payload.
 */
function extractBillableAids($payload) {
	$rawAids = extractRequestValue($payload, 'aids');
	if ($rawAids === null) {
		$rawAids = extractRequestValue($payload, 'aid');
	}
	if ($rawAids === null) {
		return [];
	}

	$chunks = [];
	if (is_array($rawAids)) {
		foreach ($rawAids as $item) {
			if (is_scalar($item)) {
				$chunks[] = (string) $item;
			}
		}
	} elseif (is_scalar($rawAids)) {
		$chunks[] = (string) $rawAids;
	}

	$parsedAids = [];
	foreach ($chunks as $chunk) {
		foreach (explode(',', $chunk) as $part) {
			$trimmed = trim($part);
			if ($trimmed === '' || !ctype_digit($trimmed)) {
				continue;
			}
			$normalized = (string) ((int) $trimmed);
			$parsedAids[$normalized] = true;
		}
	}

	return array_keys($parsedAids);
}

/**
 * List available crm_data fixture keys in stable numeric order.
 */
function listBillableAids() {
	$aidKeys = [];
	try {
		$directory = new DirectoryIterator('crm_data');
	} catch (Exception $exception) {
		return [];
	}

	foreach ($directory as $fileInfo) {
		if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'json') {
			continue;
		}

		$aidKey = $fileInfo->getBasename('.json');
		if ($aidKey === '' || !ctype_digit($aidKey)) {
			continue;
		}

		$aidKeys[] = (string) ((int) $aidKey);
	}

	$aidKeys = array_values(array_unique($aidKeys));
	usort($aidKeys, function ($left, $right) {
		$leftLength = strlen($left);
		$rightLength = strlen($right);
		if ($leftLength !== $rightLength) {
			return $leftLength - $rightLength;
		}

		return strcmp($left, $right);
	});

	return $aidKeys;
}

/**
 * Return accounts for GAD requests, including query_id per account.
 * When a query has no aid filter but carries other params (e.g. in_collection),
 * scan every fixture and apply the remaining params as a cross-account filter.
 */
function resolveGadAccounts($payload, $defaultAid = null) {
	$accounts = [];
	$dataCache = [];

	if (is_array($payload) && !empty($payload['query']) && is_array($payload['query'])) {
		foreach ($payload['query'] as $queryItem) {
			if (!is_array($queryItem)) {
				continue;
			}

			$queryAid = extractQueryParamValue($queryItem, 'aid');
			$queryId = isset($queryItem['id']) ? (string) $queryItem['id'] : null;
			$queryParams = array_values(array_filter(extractQueryParams($queryItem), function ($param) {
				return isset($param['key']) && $param['key'] !== 'aid';
			}));

			if ($queryAid !== null) {
				foreach (normalizeQueryValues($queryAid) as $aidValue) {
					appendGadAccountsForAid($accounts, (int) $aidValue, $queryId, $queryParams, $dataCache);
				}
				continue;
			}

			if (empty($queryParams)) {
				continue;
			}

			foreach (listBillableAids() as $aidKey) {
				appendGadAccountsForAid($accounts, (int) $aidKey, $queryId, $queryParams, $dataCache);
			}
		}

		return $accounts;
	}

	$queryId = is_array($payload) && isset($payload['id']) ? (string) $payload['id'] : null;
	if ($defaultAid !== null) {
		appendGadAccountsForAid($accounts, (int) $defaultAid, $queryId, [], $dataCache);
	}

	return $accounts;
}

/**
 * Append all matching account entries for a specific aid into GAD response.
 */
function appendGadAccountsForAid(&$accounts, $aid, $queryId, $queryParams = [], &$dataCache = null) {
	if ($aid === null) {
		return;
	}

	if ($dataCache === null) {
		$dataCache = [];
	}

	$cacheKey = (string) $aid;
	if (!array_key_exists($cacheKey, $dataCache)) {
		$dataCache[$cacheKey] = loadAidData($aid);
	}

	$rawData = $dataCache[$cacheKey];
	if ($rawData === '') {
		return;
	}

	$matchedAccounts = filterAccountsOnly($rawData);
	if (!empty($queryParams)) {
		// CRM query params are AND-ed together for each matched account.
		$matchedAccounts = array_values(array_filter($matchedAccounts, function ($account) use ($queryParams) {
			return matchesAllQueryParams($account, $queryParams);
		}));
	}

	foreach ($matchedAccounts as &$account) {
		if ($queryId !== null && $queryId !== '') {
			$account['query_id'] = $queryId;
			if (!isset($account['id'])) {
				$account['id'] = $queryId;
			}
		}
	}
	unset($account);

	foreach ($matchedAccounts as $matchedAccount) {
		$accounts[] = $matchedAccount;
	}
}

/**
 * Return subscribers for GSD requests, including batched query payloads.
 *
 * When a query carries an aid (directly, or via a numeric sub_number) we
 * load just that fixture. Otherwise we scan every fixture and AND-filter
 * subscribers by all remaining query params (sid, sub_number, firstname,
 * msisdn, etc.) so callers can look subscribers up by any field.
 */
function resolveGsdSubscribers($payload, $defaultAid = null, $defaultSid = null, $defaultSubNumber = null) {
	$subscribers = [];
	$dataCache = [];

	if (is_array($payload) && !empty($payload['query']) && is_array($payload['query'])) {
		foreach ($payload['query'] as $queryItem) {
			if (!is_array($queryItem)) {
				continue;
			}

			$queryAid = extractQueryParamValue($queryItem, 'aid');
			$querySubNumber = extractQueryParamValue($queryItem, 'sub_number');
			$queryId = isset($queryItem['id']) ? (string) $queryItem['id'] : null;
			// aid drives fixture selection, so it is not used as a per-entry
			// filter. The rest of the params (sid, sub_number, firstname, ...)
			// are passed straight to matchesAllQueryParams.
			$subscriberParams = array_values(array_filter(extractQueryParams($queryItem), function ($param) {
				return isset($param['key']) && $param['key'] !== 'aid';
			}));

			$resolvedAid = null;
			if ($queryAid !== null) {
				$resolvedAid = (int) $queryAid;
			} elseif ($querySubNumber !== null && ctype_digit((string) $querySubNumber)) {
				$resolvedAid = (int) $querySubNumber;
			}

			if ($resolvedAid !== null) {
				appendGsdSubscribersForAid($subscribers, $resolvedAid, $queryId, $subscriberParams, $dataCache);
				continue;
			}

			if (empty($subscriberParams)) {
				continue;
			}

			// No aid hint — scan every fixture and let the params filter
			// subscribers across the directory.
			foreach (listBillableAids() as $aidKey) {
				appendGsdSubscribersForAid($subscribers, (int) $aidKey, $queryId, $subscriberParams, $dataCache);
			}
		}

		return $subscribers;
	}

	// POST/GET fallback (no payload.query): apply the legacy defaults.
	$queryId = is_array($payload) && isset($payload['id']) ? (string) $payload['id'] : null;
	if ($defaultAid === null) {
		return $subscribers;
	}

	$params = [];
	if ($defaultSid !== null) {
		$params[] = ['key' => 'sid', 'value' => $defaultSid];
	}
	if ($defaultSubNumber !== null) {
		$params[] = ['key' => 'sub_number', 'value' => $defaultSubNumber];
	}

	appendGsdSubscribersForAid($subscribers, (int) $defaultAid, $queryId, $params, $dataCache);

	return $subscribers;
}

/**
 * Append subscriber entries from one fixture aid into the GSD response.
 * AND-filters entries by $subscriberParams (empty means "all subscribers").
 */
function appendGsdSubscribersForAid(&$subscribers, $aid, $queryId, $subscriberParams, &$dataCache) {
	if ($aid === null) {
		return;
	}

	$cacheKey = (string) $aid;
	if (!array_key_exists($cacheKey, $dataCache)) {
		$dataCache[$cacheKey] = loadAidData($aid);
	}

	$rawData = $dataCache[$cacheKey];
	if ($rawData === '') {
		return;
	}

	$decoded = json_decode($rawData, true);
	if (!is_array($decoded) || empty($decoded['data']) || !is_array($decoded['data'])) {
		return;
	}

	$matchedSubscribers = array_values(array_filter($decoded['data'], function ($entry) use ($subscriberParams) {
		if (!isset($entry['type']) || $entry['type'] !== 'subscriber') {
			return false;
		}
		if (empty($subscriberParams)) {
			return true;
		}
		return matchesAllQueryParams($entry, $subscriberParams);
	}));

	foreach ($matchedSubscribers as &$subscriber) {
		if ($queryId !== null && $queryId !== '') {
			$subscriber['query_id'] = $queryId;
			if (!isset($subscriber['id'])) {
				$subscriber['id'] = $queryId;
			}
		}
	}
	unset($subscriber);

	foreach ($matchedSubscribers as $matchedSubscriber) {
		$subscribers[] = $matchedSubscriber;
	}
}

/**
 * Parse GSD request selectors from payload query items or fallback defaults.
 * @deprecated kept for backward compatibility; resolveGsdSubscribers reads
 * the payload directly now.
 */
function extractGsdRequests($payload, $defaultAid, $defaultSid, $defaultSubNumber) {
	$requests = [];

	if (is_array($payload) && !empty($payload['query']) && is_array($payload['query'])) {
		foreach ($payload['query'] as $queryItem) {
			if (!is_array($queryItem)) {
				continue;
			}

			$queryAid = extractQueryParamValue($queryItem, 'aid');
			$querySid = extractQueryParamValue($queryItem, 'sid');
			$querySubNumber = extractQueryParamValue($queryItem, 'sub_number');

			if ($queryAid === null && $querySid === null && $querySubNumber === null) {
				continue;
			}

			$requests[] = [
				'aid' => $queryAid !== null ? (int) $queryAid : null,
				'sid' => $querySid !== null ? (int) $querySid : null,
				'sub_number' => $querySubNumber !== null ? (string) $querySubNumber : null,
				'query_id' => isset($queryItem['id']) ? (string) $queryItem['id'] : null,
			];
		}
	}

	if (empty($requests)) {
		$requests[] = [
			'aid' => $defaultAid,
			'sid' => $defaultSid,
			'sub_number' => $defaultSubNumber,
			'query_id' => is_array($payload) && isset($payload['id']) ? (string) $payload['id'] : null,
		];
	}

	return $requests;
}

/**
 * Extract query params from query item, keeping only valid key/value pairs.
 */
function extractQueryParams($queryItem) {
	if (empty($queryItem['params']) || !is_array($queryItem['params'])) {
		return [];
	}

	return array_values(array_filter($queryItem['params'], function ($param) {
		return is_array($param) && isset($param['key']) && array_key_exists('value', $param);
	}));
}

/**
 * Extract a single parameter value from query.params by key.
 */
function extractQueryParamValue($queryItem, $key) {
	if (empty($queryItem['params']) || !is_array($queryItem['params'])) {
		return null;
	}

	foreach ($queryItem['params'] as $param) {
		if (isset($param['key'], $param['value']) && $param['key'] === $key) {
			return $param['value'];
		}
	}

	return null;
}

/**
 * Normalize query param value into a list of scalar values.
 */
function normalizeQueryValues($value) {
	if (is_array($value)) {
		return array_values(array_filter($value, function ($item) {
			return is_scalar($item) && $item !== '';
		}));
	}

	if (is_scalar($value) && $value !== '') {
		return [ $value ];
	}

	return [];
}

/**
 * Check if a record matches all query params (AND semantics).
 */
function matchesAllQueryParams($entry, $queryParams) {
	foreach ($queryParams as $param) {
		if (!matchesSingleQueryParam($entry, $param)) {
			return false;
		}
	}

	return true;
}

/**
 * Match a single query param against an entry.
 */
function matchesSingleQueryParam($entry, $param) {
	if (!is_array($param) || !isset($param['key']) || !array_key_exists('value', $param)) {
		return true;
	}

	$key = (string) $param['key'];
	$operator = isset($param['operator']) ? strtolower((string) $param['operator']) : 'equal';
	$entryHasValue = false;
	$entryValue = extractEntryValueByPath($entry, $key, $entryHasValue);
	if (!$entryHasValue) {
		return false;
	}

	$queryValue = $param['value'];
	switch ($operator) {
		case 'equal':
		case 'eq':
			return valuesMatch($entryValue, $queryValue);
		case 'in':
			foreach (normalizeQueryValues($queryValue) as $candidate) {
				if (valuesMatch($entryValue, $candidate)) {
					return true;
				}
			}
			return false;
		case 'not_equal':
		case 'ne':
		case 'neq':
			return !valuesMatch($entryValue, $queryValue);
		case 'nin':
		case 'not_in':
			foreach (normalizeQueryValues($queryValue) as $candidate) {
				if (valuesMatch($entryValue, $candidate)) {
					return false;
				}
			}
			return true;
		case 'gt':
		case 'gte':
		case 'lt':
		case 'lte':
			return compareValuesByOperator($entryValue, $queryValue, $operator);
		default:
			return valuesMatch($entryValue, $queryValue);
	}
}

/**
 * Resolve scalar and nested values from an entry using dot notation.
 */
function extractEntryValueByPath($entry, $path, &$exists) {
	$exists = false;
	if (!is_array($entry)) {
		return null;
	}

	if (array_key_exists($path, $entry)) {
		$exists = true;
		return $entry[$path];
	}

	$current = $entry;
	foreach (explode('.', $path) as $segment) {
		if (!is_array($current) || !array_key_exists($segment, $current)) {
			return null;
		}
		$current = $current[$segment];
	}

	$exists = true;
	return $current;
}

/**
 * Compare two values for equality while normalizing bool/numeric scalars.
 */
function valuesMatch($entryValue, $queryValue) {
	if (is_array($queryValue)) {
		foreach (normalizeQueryValues($queryValue) as $candidate) {
			if (valuesMatch($entryValue, $candidate)) {
				return true;
			}
		}
		return false;
	}

	if (is_array($entryValue)) {
		foreach ($entryValue as $valuePart) {
			if (valuesMatch($valuePart, $queryValue)) {
				return true;
			}
		}
		return false;
	}

	if (!is_scalar($entryValue) || !is_scalar($queryValue)) {
		return $entryValue === $queryValue;
	}

	return normalizeComparableScalar($entryValue) === normalizeComparableScalar($queryValue);
}

/**
 * Compare two values for inequality operators.
 */
function compareValuesByOperator($entryValue, $queryValue, $operator) {
	if (is_array($entryValue)) {
		foreach ($entryValue as $valuePart) {
			if (compareValuesByOperator($valuePart, $queryValue, $operator)) {
				return true;
			}
		}
		return false;
	}

	if (!is_scalar($entryValue) || !is_scalar($queryValue)) {
		return false;
	}

	$left = normalizeComparableScalar($entryValue);
	$right = normalizeComparableScalar($queryValue);

	if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
		$cmp = $left <=> $right;
	} else {
		$cmp = strcmp((string) $left, (string) $right);
	}

	switch ($operator) {
		case 'gt':
			return $cmp > 0;
		case 'gte':
			return $cmp >= 0;
		case 'lt':
			return $cmp < 0;
		case 'lte':
			return $cmp <= 0;
	}

	return false;
}

/**
 * Normalize scalar values so string/number/bool comparisons are stable.
 */
function normalizeComparableScalar($value) {
	if (is_int($value) || is_float($value) || is_bool($value)) {
		return $value;
	}

	if (!is_string($value)) {
		return $value;
	}

	$trimmed = trim($value);
	if (strcasecmp($trimmed, 'true') === 0) {
		return true;
	}
	if (strcasecmp($trimmed, 'false') === 0) {
		return false;
	}
	if (is_numeric($trimmed)) {
		return $trimmed + 0;
	}

	return $trimmed;
}

/**
 * Return only subscriber entries (optionally filtered by SID/sub_number).
 */
function filterSubscribersOnly($data, $sid = null, $subNumber = null) {
	if (!$data) {
		return [];
	}

	$decoded = json_decode($data, true);
	if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
		return [];
	}

	return array_values(array_filter($decoded['data'], function ($entry) use ($sid, $subNumber) {
		if (!isset($entry['type']) || $entry['type'] !== 'subscriber') {
			return false;
		}

		if ($sid !== null) {
			return isset($entry['sid']) && (int) $entry['sid'] === $sid;
		}

		if ($subNumber !== null) {
			return isset($entry['sub_number']) && (string) $entry['sub_number'] === (string) $subNumber;
		}

		return true;
	}));
}

/**
 * Return only account entries from payload.
 */
function filterAccountsOnly($data) {
	if (!$data) {
		return [];
	}

	$decoded = json_decode($data, true);
	if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
		return [];
	}

	return array_values(array_filter($decoded['data'], function ($entry) {
		return isset($entry['type']) && $entry['type'] === 'account';
	}));
}
?>