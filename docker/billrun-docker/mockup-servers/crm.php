<?php
$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

$aid = extractAid($payload);
$sid = extractSid($payload);
$data = loadAidData($aid);

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

function loadAidData($aid) {
	if ($aid === null) {
		return '';
	}

	$filePath = "crm_data/{$aid}.json";
	if (!is_readable($filePath)) {
		return '';
	}

	return file_get_contents($filePath, true);
}

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
