<?php
/**
 * Lightweight CRM mock used by the docker environment.
 * - GAD endpoint returns account data for the requested aid.
 * - GSD endpoint returns subscriber data for the requested aid/sid.
 * - Billable endpoint serves the raw CRM fixture.
 * The endpoints read CRM fixtures from crm_data/<aid>.json and shape the payload
 * to mimic the original CRM behavior for tests and local development.
 */
$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
$folderPath = getFolderPath($path);
$aid = extractAid($payload);
$sid = extractSid($payload);
$data = loadAidData($aid, $folderPath);

if (preg_match('/\/gad/', $_SERVER["REQUEST_URI"])) {
	$response = filterAccountsOnly($data);
	echo $response ?: json_encode(['status' => 0, 'message' => 'aid not found']);
} elseif (preg_match('/\/gsd/', $_SERVER["REQUEST_URI"])) {
	$response = filterSubscribersOnly($data, $sid);
	echo $response ?: json_encode(['status' => 0, 'message' => 'aid not found']);
} elseif (preg_match('/\/billable/', $_SERVER["REQUEST_URI"])) {
	echo $data;
} else {
	// unsupported endpoint
}

function getFolderPath($path){
	$pluginName = extractPlugin($path);
	if(isset($pluginName)){
		$folderPath = "crm_data/plugin/{$pluginName}";
	}else{
		$folderPath = "crm_data";
	}
	if (is_dir($folderPath) && is_readable($folderPath)) {
		return $folderPath;
	}
	return null;
}

function extractPlugin($path) {
	$parts = explode('/', trim($path, '/'));
	if(count($parts)== 3){
		// $parts[0] is 'crm'
		// $parts[1] is your plugin
		$plugin = $parts[1] ?? null;
		return $plugin;
	}
	return null;
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
					return (int) $param['value'];
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
 * Load the CRM fixture for the given aid.
 * Returns an empty string when aid is missing or file unreadable.
 */
function loadAidData($aid, $folderPath = null) {
	if ($aid === null) {
		return '';
	}
	if(isset($folderPath)){
		$filePath = "{$folderPath}/{$aid}.json";
		if (is_readable($filePath)) {
			return file_get_contents($filePath, true);
		}
	}

	$filePath = "crm_data/{$aid}.json";
	if (!is_readable($filePath)) {
		return file_get_contents($filePath, true);
	}

	return '';
}

/**
 * Return only subscriber entries (optionally filtered by SID).
 */
function filterSubscribersOnly($data, $sid = null) {
	if (!$data) {
		return '';
	}

	$decoded = json_decode($data, true);
	if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
		return $data;
	}

	$decoded['data'] = array_values(array_filter($decoded['data'], function ($entry) use ($sid) {
		if (!isset($entry['type']) || $entry['type'] !== 'subscriber') {
			return false;
		}

		if ($sid === null) {
			return true;
		}

		return isset($entry['sid']) && (int) $entry['sid'] === $sid;
	}));

	return json_encode($decoded);
}

/**
 * Return only account entries from payload.
 */
function filterAccountsOnly($data) {
	if (!$data) {
		return '';
	}

	$decoded = json_decode($data, true);
	if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
		return $data;
	}

	$decoded['data'] = array_values(array_filter($decoded['data'], function ($entry) {
		return isset($entry['type']) && $entry['type'] === 'account';
	}));

	return json_encode($decoded);
}
?>
