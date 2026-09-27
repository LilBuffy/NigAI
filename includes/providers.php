<?php

declare(strict_types=1);

function nigai_gemini_contents(array $history, string $message, int $charLimit): array
{
    $contents = [];
    foreach ($history as $item) {
        if (!is_array($item)) {
            continue;
        }
        $role = ($item['role'] ?? '') === 'model' ? 'model' : 'user';
        $text = isset($item['text']) ? trim((string)$item['text']) : '';
        if ($text === '') {
            continue;
        }
        $contents[] = ['role' => $role, 'parts' => [['text' => mb_substr($text, 0, $charLimit)]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];
    return $contents;
}

function nigai_gemini_payload(array $history, string $message, array $genCfg, string $systemPrompt, int $charLimit): array
{
    $payload = ['contents' => nigai_gemini_contents($history, $message, $charLimit)];
    if (!empty($genCfg)) {
        $payload['generationConfig'] = $genCfg;
    }
    if (trim($systemPrompt) !== '') {
        $payload['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
    }
    return $payload;
}

function nigai_gemini_url(string $template, string $model, string $key): string
{
    return str_replace(['{model}', '{key}'], [$model, $key], $template);
}

function nigai_gemini_extract_reply(array $data): ?string
{
    $candidate = $data['candidates'][0] ?? null;
    if (!$candidate) {
        return null;
    }
    $text = '';
    foreach ($candidate['content']['parts'] ?? [] as $part) {
        if (isset($part['text'])) {
            $text .= $part['text'];
        }
    }
    return $text !== '' ? $text : null;
}

function nigai_gemini_stream_chunk(string $chunk, string &$buffer, callable $emit): void
{
    $buffer .= $chunk;
    $lines = explode("\n", $buffer);
    $buffer = array_pop($lines);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, 'data:') !== 0) {
            continue;
        }
        $json = trim(substr($line, 5));
        if ($json === '') {
            continue;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            continue;
        }
        $text = nigai_gemini_extract_reply($data);
        if ($text !== null && $text !== '') {
            $emit($text);
        }
    }
}

function nigai_openai_messages(array $history, string $message, string $systemPrompt, int $charLimit): array
{
    $messages = [];
    if (trim($systemPrompt) !== '') {
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    }
    foreach ($history as $item) {
        if (!is_array($item)) {
            continue;
        }
        $role = ($item['role'] ?? '') === 'model' ? 'assistant' : 'user';
        $text = isset($item['text']) ? trim((string)$item['text']) : '';
        if ($text === '') {
            continue;
        }
        $messages[] = ['role' => $role, 'content' => mb_substr($text, 0, $charLimit)];
    }
    $messages[] = ['role' => 'user', 'content' => $message];
    return $messages;
}

function nigai_openai_payload(array $history, string $message, string $model, string $systemPrompt, int $charLimit, bool $stream): array
{
    $payload = [
        'model' => $model,
        'messages' => nigai_openai_messages($history, $message, $systemPrompt, $charLimit),
    ];
    if ($stream) {
        $payload['stream'] = true;
    }
    return $payload;
}

function nigai_openai_headers(string $key, array $extraHeaders): array
{
    $headers = ['Authorization: Bearer ' . $key];
    foreach ($extraHeaders as $name => $value) {
        if ($value !== '') {
            $headers[] = $name . ': ' . $value;
        }
    }
    return $headers;
}

function nigai_openai_extract_reply(array $data): ?string
{
    $text = $data['choices'][0]['message']['content'] ?? null;
    return ($text !== null && $text !== '') ? $text : null;
}

function nigai_openai_stream_chunk(string $chunk, string &$buffer, callable $emit): void
{
    $buffer .= $chunk;
    $lines = explode("\n", $buffer);
    $buffer = array_pop($lines);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, 'data:') !== 0) {
            continue;
        }
        $json = trim(substr($line, 5));
        if ($json === '' || $json === '[DONE]') {
            continue;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            continue;
        }
        $text = $data['choices'][0]['delta']['content'] ?? null;
        if ($text !== null && $text !== '') {
            $emit($text);
        }
    }
}
