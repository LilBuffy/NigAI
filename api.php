<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . '/config.php';

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server is not configured yet. Copy config.example.php to config.php and add at least one provider API key.']);
    exit;
}

$config    = require $configPath;
$providers = $config['providers'] ?? [];
$maxHist   = (int)($config['max_history_messages'] ?? 20);

/** Removes empty/placeholder keys and returns a clean list. */
function usable_keys(array $rawKeys): array
{
    return array_values(array_filter(array_map('trim', $rawKeys), function ($k) {
        return $k !== '' && stripos($k, 'PUT_YOUR') === false;
    }));
}

/** Providers that are enabled AND have at least one usable key. */
function available_providers(array $providers): array
{
    $out = [];
    foreach ($providers as $id => $p) {
        if (!empty($p['enabled']) && count(usable_keys($p['api_keys'] ?? [])) > 0) {
            $out[$id] = $p;
        }
    }
    return $out;
}

// ---------------------------------------------------------
// GET ?action=meta — safe provider/model list for the UI
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'meta') {
    $available = available_providers($providers);
    $list = [];

    foreach ($available as $id => $p) {
        $models = [];
        foreach ($p['models'] ?? [] as $modelId => $modelLabel) {
            $models[] = ['id' => $modelId, 'label' => $modelLabel];
        }
        $list[] = [
            'id'             => $id,
            'label'          => $p['label'] ?? $id,
            'models'         => $models,
            'default_model'  => $p['default_model'] ?? ($models[0]['id'] ?? ''),
        ];
    }

    echo json_encode(['providers' => $list]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// ---------------------------------------------------------
// Parse + validate the incoming request
// ---------------------------------------------------------
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body.']);
    exit;
}

$providerId = isset($input['provider']) ? (string)$input['provider'] : '';
$modelId    = isset($input['model']) ? (string)$input['model'] : '';
$message    = isset($input['message']) ? trim((string)$input['message']) : '';
$history    = isset($input['history']) && is_array($input['history']) ? $input['history'] : [];

if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message cannot be empty.']);
    exit;
}
if (mb_strlen($message) > 8000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is too long (max 8000 characters).']);
    exit;
}

$available = available_providers($providers);

if (!isset($available[$providerId])) {
    http_response_code(400);
    echo json_encode(['error' => 'That provider is not available. Check config.php (enabled + API key).']);
    exit;
}

$provider = $available[$providerId];
$models   = $provider['models'] ?? [];

if ($modelId === '' || !array_key_exists($modelId, $models)) {
    $modelId = $provider['default_model'] ?? array_key_first($models);
}
if ($modelId === null || !array_key_exists($modelId, $models)) {
    http_response_code(500);
    echo json_encode(['error' => 'No valid model configured for ' . ($provider['label'] ?? $providerId) . '.']);
    exit;
}

$apiKeys = usable_keys($provider['api_keys'] ?? []);
$history = array_slice($history, -$maxHist);

// ---------------------------------------------------------
// Format-specific request builders + response parsers
// ---------------------------------------------------------

function gemini_request(string $endpointTemplate, string $key, string $model, string $message, array $history, array $genCfg): array
{
    $contents = [];
    foreach ($history as $item) {
        if (!is_array($item)) continue;
        $role = ($item['role'] ?? '') === 'model' ? 'model' : 'user';
        $text = isset($item['text']) ? trim((string)$item['text']) : '';
        if ($text === '') continue;
        $contents[] = ['role' => $role, 'parts' => [['text' => mb_substr($text, 0, 8000)]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

    $payload = ['contents' => $contents];
    if (!empty($genCfg)) {
        $payload['generationConfig'] = $genCfg;
    }

    $url = str_replace(['{model}', '{key}'], [$model, $key], $endpointTemplate);

    return http_post_json($url, [], $payload);
}

function gemini_extract_reply(array $data): ?string
{
    $candidate = $data['candidates'][0] ?? null;
    if (!$candidate) return null;

    $text = '';
    foreach ($candidate['content']['parts'] ?? [] as $part) {
        if (isset($part['text'])) $text .= $part['text'];
    }
    return $text !== '' ? $text : null;
}

function openai_request(string $endpoint, string $key, string $model, string $message, array $history, array $extraHeaders = []): array
{
    $messages = [];
    foreach ($history as $item) {
        if (!is_array($item)) continue;
        $role = ($item['role'] ?? '') === 'model' ? 'assistant' : 'user';
        $text = isset($item['text']) ? trim((string)$item['text']) : '';
        if ($text === '') continue;
        $messages[] = ['role' => $role, 'content' => mb_substr($text, 0, 8000)];
    }
    $messages[] = ['role' => 'user', 'content' => $message];

    $payload = ['model' => $model, 'messages' => $messages];

    $headers = ['Authorization: Bearer ' . $key];
    foreach ($extraHeaders as $name => $value) {
        if ($value !== '') $headers[] = $name . ': ' . $value;
    }

    return http_post_json($endpoint, $headers, $payload);
}

function openai_extract_reply(array $data): ?string
{
    $text = $data['choices'][0]['message']['content'] ?? null;
    return ($text !== null && $text !== '') ? $text : null;
}

/** Performs the POST and returns [httpCode, decodedBody, curlErrorOrNull]. */
function http_post_json(string $url, array $headers, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 60,
    ]);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return [0, null, $curlError ?: 'Could not reach the provider.'];
    }

    return [$httpCode, json_decode($response, true), null];
}

// ---------------------------------------------------------
// Try each key for the selected provider until one works
// ---------------------------------------------------------
$format   = $provider['format'] ?? 'openai';
$label    = $provider['label'] ?? $providerId;
$lastError = 'Unknown error.';

foreach ($apiKeys as $key) {

    if ($format === 'gemini') {
        [$httpCode, $data, $networkError] = gemini_request(
            $provider['endpoint'], $key, $modelId, $message, $history, $provider['generation_config'] ?? []
        );
    } else {
        [$httpCode, $data, $networkError] = openai_request(
            $provider['endpoint'], $key, $modelId, $message, $history, $provider['extra_headers'] ?? []
        );
    }

    if ($networkError !== null) {
        $lastError = $networkError;
        continue;
    }

    if ($httpCode === 200 && is_array($data)) {
        $reply = $format === 'gemini' ? gemini_extract_reply($data) : openai_extract_reply($data);

        if ($reply === null) {
            $reason = $data['candidates'][0]['finishReason'] ?? 'unknown';
            echo json_encode(['error' => "$label returned an empty response (reason: $reason). Try rephrasing your message."]);
            exit;
        }

        echo json_encode(['reply' => $reply, 'provider' => $label, 'model' => $modelId]);
        exit;
    }

    $errMessage = $data['error']['message'] ?? $data['error'] ?? ('HTTP ' . $httpCode);
    $errMessage = is_array($errMessage) ? json_encode($errMessage) : (string)$errMessage;
    $errStatus  = is_array($data['error'] ?? null) ? ($data['error']['status'] ?? '') : '';

    $isRateLimitOrQuota = $httpCode === 429 || stripos((string)$errStatus, 'RESOURCE_EXHAUSTED') !== false;
    $isAuthProblem      = in_array($httpCode, [401, 403], true);

    if ($isRateLimitOrQuota || $isAuthProblem) {
        $lastError = $errMessage;
        continue; // try the next key for this same provider
    }

    // Any other error (bad request, invalid model, safety block, etc.) — retrying won't help.
    http_response_code($httpCode >= 400 ? $httpCode : 500);
    echo json_encode(['error' => "$label error: $errMessage"]);
    exit;
}

http_response_code(503);
echo json_encode(['error' => "All configured $label API keys are rate-limited, out of quota, or invalid. Last error: $lastError"]);
