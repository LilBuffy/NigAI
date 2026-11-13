<?php

declare(strict_types=1);

function nigai_usable_keys(array $rawKeys): array
{
    return array_values(array_filter(array_map('trim', $rawKeys), function ($k) {
        return $k !== '' && stripos($k, 'PUT_YOUR') === false;
    }));
}

function nigai_available_providers(array $providers): array
{
    $out = [];
    foreach ($providers as $id => $p) {
        if (!empty($p['enabled']) && count(nigai_usable_keys($p['api_keys'] ?? [])) > 0) {
            $out[$id] = $p;
        }
    }
    return $out;
}

function nigai_http_collect(string $url, array $headers, array $payload, int $timeoutSeconds): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        return [0, null, $curlError ?: 'Could not reach the provider.'];
    }

    return [$httpCode, json_decode($body, true), null];
}

function nigai_http_stream(
    string $url,
    array $headers,
    array $payload,
    callable $onGoodChunk,
    int $timeoutSeconds
): array {
    $statusCode = 0;
    $errorBody = '';

    $headerFn = function ($ch, string $headerLine) use (&$statusCode) {
        if ($statusCode === 0 && preg_match('#^HTTP/\S+\s+(\d+)#', $headerLine, $m)) {
            $statusCode = (int)$m[1];
        }
        return strlen($headerLine);
    };

    $writeFn = function ($ch, string $chunk) use (&$statusCode, &$errorBody, $onGoodChunk) {
        if ($statusCode >= 200 && $statusCode < 300) {
            $onGoodChunk($chunk);
        } else {
            $errorBody .= $chunk;
        }
        return strlen($chunk);
    };

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HEADERFUNCTION => $headerFn,
        CURLOPT_WRITEFUNCTION => $writeFn,
    ]);

    $ok = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($ok === false && $statusCode === 0) {
        return [0, null, $curlError ?: 'Could not reach the provider.'];
    }

    if ($statusCode >= 200 && $statusCode < 300) {
        return [$statusCode, null, null];
    }

    return [$statusCode, json_decode($errorBody, true) ?? $errorBody, null];
}

function nigai_is_retryable_error(int $httpCode, $decodedErrorData): bool
{
    if ($httpCode === 429 || in_array($httpCode, [401, 403], true)) {
        return true;
    }

    if (is_array($decodedErrorData)) {
        $status = '';
        if (is_array($decodedErrorData['error'] ?? null)) {
            $status = (string)($decodedErrorData['error']['status'] ?? '');
        }
        if (stripos($status, 'RESOURCE_EXHAUSTED') !== false) {
            return true;
        }
    }

    return false;
}

function nigai_extract_error_message($decodedErrorData, int $httpCode): string
{
    if (is_array($decodedErrorData)) {
        $msg = $decodedErrorData['error']['message'] ?? $decodedErrorData['error'] ?? null;
        if ($msg !== null) {
            return is_array($msg) ? json_encode($msg) : (string)$msg;
        }
    }
    if (is_string($decodedErrorData) && $decodedErrorData !== '') {
        return $decodedErrorData;
    }
    return 'HTTP ' . $httpCode;
}

function nigai_friendly_error_message(string $label, int $httpCode, $decodedErrorData): string
{
    $raw = strtolower(nigai_extract_error_message($decodedErrorData, $httpCode));

    if (strpos($raw, 'safety') !== false || strpos($raw, 'blocked') !== false || strpos($raw, 'block') !== false) {
        return $label . ' could not respond to that message due to its content safety rules. Try rephrasing it.';
    }
    if ($httpCode === 404) {
        return $label . ' could not find the selected model. Try a different model.';
    }
    if ($httpCode === 400) {
        return $label . ' could not process that request. Try rephrasing your message.';
    }
    if ($httpCode >= 500) {
        return $label . ' is currently unavailable. Please try again shortly.';
    }
    return $label . ' could not complete this request right now.';
}
