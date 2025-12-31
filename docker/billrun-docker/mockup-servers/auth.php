<?php

const AUTH_STATE_FILE = __DIR__ . '/temp/auth_mock_state.json';

header('Content-Type: application/json');

$rawBody = file_get_contents('php://input') ?: '';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/auth-mock/reset') {
    $config = parseRequestBody($rawBody);
    $state = resetAuthState(is_array($config) ? $config : []);
    jsonResponse(['status' => 'reset', 'state' => $state]);
}

if ($path === '/auth-mock/stats') {
    $state = loadAuthState();
    jsonResponse([
        'token_index' => $state['token_index'],
        'token_sequence' => $state['token_sequence'],
        'token_calls' => $state['token_calls'],
        'api_calls' => $state['api_calls'],
        'api_rules' => $state['api_rules'],
        'default_api_status' => $state['default_api_status'],
        'default_api_body' => $state['default_api_body'],
    ]);
}

if ($path === '/token') {
    handleToken($rawBody);
}

if ($path === '/api') {
    handleApi($rawBody);
}

jsonResponse(['error' => 'not_found', 'path' => $path], 404);

function jsonResponse($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

function loadAuthState()
{
    if (!file_exists(AUTH_STATE_FILE)) {
        return resetAuthState();
    }

    $state = json_decode((string) file_get_contents(AUTH_STATE_FILE), true);

    if (!is_array($state)) {
        return resetAuthState();
    }

    return ensureAuthStateDefaults($state);
}

function ensureAuthStateDefaults(array $state)
{
    $defaults = [
        'token_sequence' => [
            ['access_token' => 'T1', 'expires_in' => 30],
        ],
        'token_index' => 0,
        'token_calls' => [],
        'api_calls' => [],
        'api_rules' => [],
        'default_api_status' => 200,
        'default_api_body' => ['ok' => true],
    ];

    return array_merge($defaults, $state);
}

function resetAuthState(array $config = [])
{
    $state = ensureAuthStateDefaults([
        'token_sequence' => normalizeTokenSequence($config['token_sequence'] ?? []),
        'api_rules' => normalizeApiRules($config['api_rules'] ?? []),
        'default_api_status' => $config['default_api_status'] ?? 200,
        'default_api_body' => $config['default_api_body'] ?? ['ok' => true],
        'token_calls' => [],
        'api_calls' => [],
        'token_index' => 0,
    ]);

    persistAuthState($state);

    return $state;
}

function persistAuthState(array $state)
{
    $dir = dirname(AUTH_STATE_FILE);

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents(AUTH_STATE_FILE, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
}

function parseRequestBody($rawBody)
{
    $json = json_decode($rawBody, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        return $json;
    }

    return $_POST ?: [];
}

function handleToken($rawBody)
{
    $state = loadAuthState();
    $headers = getRequestHeaders();
    $payload = parseRequestBody($rawBody);

    $sequence = $state['token_sequence'];

    if (empty($sequence)) {
        $sequence = [['access_token' => 'T1', 'expires_in' => 30]];
    }

    $index = $state['token_index'];
    $response = $sequence[min($index, count($sequence) - 1)];
    $state['token_index'] = $index + 1;

    $state['token_calls'][] = [
        'time' => microtime(true),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'headers' => $headers,
        'payload' => $payload ?: $rawBody,
    ];

    persistAuthState($state);

    $body = [
        'access_token' => $response['access_token'] ?? 'mock-token',
        'token_type' => $response['token_type'] ?? 'Bearer',
        'expires_in' => $response['expires_in'] ?? 30,
    ];

    $status = $response['status'] ?? 200;

    jsonResponse($body, $status);
}

function handleApi($rawBody)
{
    $state = loadAuthState();
    $headers = getRequestHeaders();
    $payload = parseRequestBody($rawBody);
    $token = extractBearerToken($headers);
    $callNumber = count($state['api_calls']) + 1;

    [$status, $body] = resolveApiResponse($state, $token, $callNumber);

    $state['api_calls'][] = [
        'time' => microtime(true),
        'call' => $callNumber,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'headers' => $headers,
        'payload' => $payload ?: $rawBody,
        'token' => $token,
        'status' => $status,
    ];

    persistAuthState($state);

    jsonResponse($body, $status);
}

function resolveApiResponse(array $state, $token, $callNumber)
{
    $status = $state['default_api_status'] ?? 200;
    $body = $state['default_api_body'] ?? ['ok' => true];

    foreach ($state['api_rules'] as $rule) {
        if (isset($rule['call']) && (int) $rule['call'] !== (int) $callNumber) {
            continue;
        }

        if (isset($rule['token']) && $rule['token'] !== $token) {
            continue;
        }

        $status = $rule['status'] ?? $status;
        $body = $rule['body'] ?? $body;
        break;
    }

    if (is_array($body) && !array_key_exists('token', $body)) {
        $body['token'] = $token;
    }

    return [$status, $body];
}

function extractBearerToken(array $headers)
{
    $rawHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (preg_match('/Bearer\\s+(.+)/i', $rawHeader, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function normalizeTokenSequence(array $sequence)
{
    if (empty($sequence)) {
        return [['access_token' => 'T1', 'expires_in' => 30]];
    }

    $normalized = [];

    foreach ($sequence as $item) {
        $normalized[] = [
            'access_token' => $item['access_token'] ?? 'mock-token',
            'expires_in' => $item['expires_in'] ?? 30,
            'token_type' => $item['token_type'] ?? 'Bearer',
            'status' => $item['status'] ?? 200,
        ];
    }

    return $normalized;
}

function normalizeApiRules(array $rules)
{
    $normalized = [];

    foreach ($rules as $rule) {
        $normalized[] = [
            'token' => $rule['token'] ?? null,
            'call' => isset($rule['call']) ? (int) $rule['call'] : null,
            'status' => $rule['status'] ?? 200,
            'body' => $rule['body'] ?? ['ok' => $rule['status'] < 400],
        ];
    }

    return $normalized;
}

function getRequestHeaders()
{
    if (function_exists('getallheaders')) {
        return getallheaders();
    }

    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (strpos($name, 'HTTP_') === 0) {
            $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$headerName] = $value;
        }
    }

    return $headers;
}

