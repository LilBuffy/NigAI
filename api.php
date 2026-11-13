<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require __DIR__ . '/includes/http.php';
require __DIR__ . '/includes/providers.php';

$configPath = __DIR__ . '/config.php';

if (!file_exists($configPath)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'NigAI is not configured yet. Add your API keys to config.php.']);
    exit;
}

$config = require $configPath;
$providers = $config['providers'] ?? [];
$maxHistory = (int)($config['max_history_messages'] ?? 20);
$maxMessageLength = (int)($config['max_message_length'] ?? 8000);
$timeoutSeconds = (int)($config['request_timeout_seconds'] ?? 90);
$systemPrompt = (string)($config['system_prompt'] ?? '');
$fallbackAcrossProviders = (bool)($config['fallback_across_providers'] ?? true);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'meta') {
    header('Content-Type: application/json; charset=utf-8');
    $available = nigai_available_providers($providers);
    $list = [];

    foreach ($available as $id => $p) {
        $models = [];
        foreach ($p['models'] ?? [] as $modelId => $modelLabel) {
            $models[] = ['id' => $modelId, 'label' => $modelLabel];
        }
        $list[] = [
            'id' => $id,
            'label' => $p['label'] ?? $id,
            'models' => $models,
            'default_model' => $p['default_model'] ?? ($models[0]['id'] ?? ''),
        ];
    }

    echo json_encode(['providers' => $list, 'app_name' => $config['app_name'] ?? 'NigAI']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body.']);
    exit;
}

$requestedProvider = isset($input['provider']) ? (string)$input['provider'] : '';
$requestedModel = isset($input['model']) ? (string)$input['model'] : '';
$message = isset($input['message']) ? trim((string)$input['message']) : '';
$history = isset($input['history']) && is_array($input['history']) ? $input['history'] : [];

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);
set_time_limit(0);

function nigai_emit(array $payload): void
{
    echo 'data: ' . json_encode($payload) . "\n\n";
    if (function_exists('fastcgi_finish_request') === false) {
        @ob_flush();
    }
    flush();
}

if ($message === '') {
    nigai_emit(['error' => 'Message cannot be empty.']);
    exit;
}
if (mb_strlen($message) > $maxMessageLength) {
    nigai_emit(['error' => 'Message is too long (max ' . $maxMessageLength . ' characters).']);
    exit;
}

$available = nigai_available_providers($providers);

if (empty($available)) {
    nigai_emit(['error' => 'No AI providers are configured on the server yet.']);
    exit;
}

$providerOrder = [];
if ($requestedProvider !== '' && isset($available[$requestedProvider])) {
    $providerOrder[] = $requestedProvider;
}
if ($fallbackAcrossProviders || empty($providerOrder)) {
    foreach ($available as $id => $p) {
        if (!in_array($id, $providerOrder, true)) {
            $providerOrder[] = $id;
        }
    }
}

$history = array_slice($history, -$maxHistory);
$lastInternalError = 'No provider attempt succeeded.';

foreach ($providerOrder as $providerId) {
    $provider = $available[$providerId];
    $models = $provider['models'] ?? [];
    $format = $provider['format'] ?? 'openai';
    $label = $provider['label'] ?? $providerId;
    $supportsStream = $provider['stream'] ?? true;

    $modelId = $requestedModel;
    if ($modelId === '' || !array_key_exists($modelId, $models)) {
        $modelId = $provider['default_model'] ?? array_key_first($models);
    }
    if ($modelId === null) {
        continue;
    }

    $apiKeys = nigai_usable_keys($provider['api_keys'] ?? []);

    foreach ($apiKeys as $key) {

        $emitAndForward = function (string $text) {
            nigai_emit(['text' => $text]);
        };

        if ($supportsStream) {
            $buffer = '';

            if ($format === 'gemini') {
                $url = nigai_gemini_url($provider['stream_endpoint'] ?? $provider['endpoint'], $modelId, $key);
                $payload = nigai_gemini_payload($history, $message, $provider['generation_config'] ?? [], $systemPrompt, $maxMessageLength);
                [$httpCode, $errData, $netErr] = nigai_http_stream($url, [], $payload, function (string $chunk) use (&$buffer, $emitAndForward) {
                    nigai_gemini_stream_chunk($chunk, $buffer, $emitAndForward);
                }, $timeoutSeconds);
            } else {
                $url = $provider['endpoint'];
                $payload = nigai_openai_payload($history, $message, $modelId, $systemPrompt, $maxMessageLength, true);
                $headers = nigai_openai_headers($key, $provider['extra_headers'] ?? []);
                [$httpCode, $errData, $netErr] = nigai_http_stream($url, $headers, $payload, function (string $chunk) use (&$buffer, $emitAndForward) {
                    nigai_openai_stream_chunk($chunk, $buffer, $emitAndForward);
                }, $timeoutSeconds);
            }

            if ($netErr !== null) {
                $lastInternalError = $netErr;
                continue;
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                nigai_emit(['done' => true, 'provider' => $label, 'model' => $modelId]);
                exit;
            }

            if (nigai_is_retryable_error($httpCode, $errData)) {
                $lastInternalError = nigai_extract_error_message($errData, $httpCode);
                continue;
            }

            error_log('NigAI: ' . $label . ' hard failure: ' . nigai_extract_error_message($errData, $httpCode));
            nigai_emit(['error' => nigai_friendly_error_message($label, $httpCode, $errData)]);
            exit;
        }

        if ($format === 'gemini') {
            $url = nigai_gemini_url($provider['endpoint'], $modelId, $key);
            $payload = nigai_gemini_payload($history, $message, $provider['generation_config'] ?? [], $systemPrompt, $maxMessageLength);
            [$httpCode, $data, $netErr] = nigai_http_collect($url, [], $payload, $timeoutSeconds);
        } else {
            $url = $provider['endpoint'];
            $payload = nigai_openai_payload($history, $message, $modelId, $systemPrompt, $maxMessageLength, false);
            $headers = nigai_openai_headers($key, $provider['extra_headers'] ?? []);
            [$httpCode, $data, $netErr] = nigai_http_collect($url, $headers, $payload, $timeoutSeconds);
        }

        if ($netErr !== null) {
            $lastInternalError = $netErr;
            continue;
        }

        if ($httpCode === 200 && is_array($data)) {
            $reply = $format === 'gemini' ? nigai_gemini_extract_reply($data) : nigai_openai_extract_reply($data);
            if ($reply === null) {
                $lastInternalError = $label . ' returned an empty response.';
                continue;
            }
            nigai_emit(['text' => $reply]);
            nigai_emit(['done' => true, 'provider' => $label, 'model' => $modelId]);
            exit;
        }

        if (nigai_is_retryable_error($httpCode, $data)) {
            $lastInternalError = nigai_extract_error_message($data, $httpCode);
            continue;
        }

        error_log('NigAI: ' . $label . ' hard failure: ' . nigai_extract_error_message($data, $httpCode));
        nigai_emit(['error' => nigai_friendly_error_message($label, $httpCode, $data)]);
        exit;
    }
}

error_log('NigAI: all providers exhausted, last reason: ' . $lastInternalError);
nigai_emit(['error' => 'NigAI is temporarily unavailable. Please try again in a moment.']);
