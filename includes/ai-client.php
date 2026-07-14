<?php
/**
 * Pluggable LLM client — OpenRouter, DeepSeek, or OpenAI-compatible.
 * Sends text only (never audio). Kept token-efficient with short prompts.
 */

require_once __DIR__ . '/comm-functions.php';

function aiProviderDefaults(string $provider): array
{
    if ($provider === 'deepseek') {
        return [
            'base_url' => 'https://api.deepseek.com/v1',
            'model' => 'deepseek-chat',
        ];
    }
    if ($provider === 'openai_compatible') {
        return [
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
        ];
    }
    return [
        'base_url' => 'https://openrouter.ai/api/v1',
        'model' => 'deepseek/deepseek-chat',
    ];
}

function aiResolveConfig(?array $override = null): array
{
    $s = array_merge(getAiSettings(), $override ?: []);
    $provider = $s['provider'] ?? 'openrouter';
    if (!in_array($provider, ['openrouter', 'deepseek', 'openai_compatible'], true)) {
        $provider = 'openrouter';
    }
    $defaults = aiProviderDefaults($provider);
    $base = trim((string)($s['base_url'] ?? '')) ?: $defaults['base_url'];
    $model = trim((string)($s['model'] ?? '')) ?: $defaults['model'];
    return [
        'provider' => $provider,
        'api_key' => trim((string)($s['api_key'] ?? '')),
        'base_url' => rtrim($base, '/'),
        'model' => $model,
        'enable_suggest' => !empty($s['enable_suggest']),
        'enable_summarize' => !empty($s['enable_summarize']),
    ];
}

/**
 * @return array{ok:bool, content?:string, err?:string, model?:string}
 */
function aiChatCompletion(array $messages, ?array $cfg = null): array
{
    $cfg = aiResolveConfig($cfg);
    if ($cfg['api_key'] === '') {
        return ['ok' => false, 'err' => 'Add an AI API key in Settings → AI assistants.'];
    }

    $url = $cfg['base_url'] . '/chat/completions';
    $payload = [
        'model' => $cfg['model'],
        'messages' => $messages,
        'temperature' => 0.3,
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $cfg['api_key'],
    ];
    if ($cfg['provider'] === 'openrouter') {
        $headers[] = 'HTTP-Referer: ' . (appBaseUrlSafe());
        $headers[] = 'X-Title: ThreadGlam Events';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 90,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return ['ok' => false, 'err' => 'AI request failed (network).'];
    }
    $data = json_decode((string)$raw, true);
    if ($http >= 400 || !$data) {
        $msg = $data['error']['message'] ?? ('AI API error HTTP ' . $http);
        return ['ok' => false, 'err' => $msg];
    }
    $content = $data['choices'][0]['message']['content'] ?? '';
    if ($content === '') {
        return ['ok' => false, 'err' => 'Empty AI response.'];
    }
    return ['ok' => true, 'content' => $content, 'model' => $cfg['model']];
}

function appBaseUrlSafe(): string
{
    if (function_exists('appBaseUrl')) {
        try { return appBaseUrl(); } catch (Throwable $e) { /* ignore */ }
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function aiExtractJson(string $content): ?array
{
    $content = trim($content);
    if (preg_match('/\{.*\}/s', $content, $m)) {
        $decoded = json_decode($m[0], true);
        return is_array($decoded) ? $decoded : null;
    }
    return null;
}

/**
 * @return array{ok:bool, summary?:array, err?:string, model?:string}
 */
function aiSummarizeSession(array $payload): array
{
    $cfg = aiResolveConfig();
    if (!$cfg['enable_summarize']) {
        return ['ok' => false, 'err' => 'AI summarization is disabled in Settings.'];
    }

    $system = 'You are an event-planning assistant. Summarize a customer design meeting in concise JSON only. No markdown. Keys: summary (string), decisions (string[]), open_questions (string[]), next_actions (string[]). Be brief.';
    $user = 'Meeting context (use only this):\n' . json_encode($payload, JSON_UNESCAPED_UNICODE);

    $res = aiChatCompletion([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], $cfg);
    if (!$res['ok']) return $res;

    $parsed = aiExtractJson($res['content']);
    if (!$parsed) {
        $parsed = [
            'summary' => trim($res['content']),
            'decisions' => [],
            'open_questions' => [],
            'next_actions' => [],
        ];
    }
    return ['ok' => true, 'summary' => $parsed, 'model' => $res['model'] ?? $cfg['model']];
}

/**
 * @return array{ok:bool, questions?:array, err?:string}
 */
function aiSuggestQuestions(array $payload): array
{
    $cfg = aiResolveConfig();
    if (!$cfg['enable_suggest']) {
        return ['ok' => false, 'err' => 'AI question suggestions are disabled in Settings.'];
    }

    $answered = [];
    foreach ($payload['qa'] ?? [] as $row) {
        if (trim((string)($row['a'] ?? '')) !== '') {
            $answered[] = $row['q'] ?? '';
        }
    }
    $system = 'Suggest 3 short follow-up questions for an event décor consultation. Reply JSON only: {"questions":["..."]}. Avoid repeating answered topics. Keep each under 120 chars.';
    $user = json_encode([
        'ceremony_type' => $payload['ceremony_type'] ?? '',
        'venue' => $payload['venue'] ?? '',
        'already_asked' => array_column($payload['qa'] ?? [], 'q'),
        'answered_topics' => $answered,
    ], JSON_UNESCAPED_UNICODE);

    $res = aiChatCompletion([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], $cfg);
    if (!$res['ok']) return $res;

    $parsed = aiExtractJson($res['content']);
    $questions = $parsed['questions'] ?? [];
    if (!is_array($questions)) $questions = [];
    $questions = array_values(array_filter(array_map(fn($q) => trim((string)$q), $questions)));
    return ['ok' => true, 'questions' => array_slice($questions, 0, 5)];
}
