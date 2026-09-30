<?php
/**
 * Collection "state change" receiver mock (BRCD-5519).
 *
 * Stands in for the CRM endpoint configured as a collection process change_state_url and records
 * every request BillRun sends there, so tests can assert what the CRM actually received
 * (the form fields as parsed by the receiver, and the raw body as sent).
 *
 *   POST   /collection-state-change/<run>   record the request, respond {"success": true}
 *   GET    /collection-state-change/<run>   the recorded requests of <run>, as a JSON list
 *   DELETE /collection-state-change/<run>   forget the recorded requests of <run>
 *
 * <run> isolates test runs from each other (each test picks its own); the requests are kept in
 * temp/collection_state_change_<run>.json.
 */

header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!preg_match('#^/collection-state-change/([A-Za-z0-9_.-]+)/?$#', $path, $matches)) {
	http_response_code(404);
	echo json_encode(['error' => 'not_found', 'path' => $path]);
	exit;
}
$run = $matches[1];

$dir = __DIR__ . '/temp';
if (!is_dir($dir)) {
	mkdir($dir, 0777, true);
}
$file = $dir . '/collection_state_change_' . $run . '.json';

switch ($_SERVER['REQUEST_METHOD'] ?? 'GET') {
	case 'POST':
		$requests = loadStateChangeRequests($file);
		$requests[] = [
			'time' => date('c'),
			'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
			'raw' => file_get_contents('php://input') ?: '',
			'post' => $_POST,
			'query' => $_GET,
		];
		file_put_contents($file, json_encode($requests, JSON_PRETTY_PRINT), LOCK_EX);
		echo json_encode(['success' => true, 'recorded' => count($requests)]);
		break;
	case 'DELETE':
		if (file_exists($file)) {
			unlink($file);
		}
		echo json_encode(['status' => 'reset', 'run' => $run]);
		break;
	default:
		echo json_encode(loadStateChangeRequests($file));
}

function loadStateChangeRequests($file) {
	if (!file_exists($file)) {
		return [];
	}
	$requests = json_decode((string) file_get_contents($file), true);
	return is_array($requests) ? $requests : [];
}
