<?php

declare(strict_types=1);

/* ── Fornecedores de leitura ─────────────────────────────────────────────────
 * Cada leitor é escrito como `fornecedor:modelo` na configuração, porque o
 * mesmo modelo responde de formas diferentes conforme por onde se fala com ele.
 *
 * A diferença que importa é o esquema. A OpenAI e a Google aceitam o esquema
 * JSON como parte do pedido e restringem a geração a ele: resposta que não
 * encaixa deixa de existir. A Replicate não tem isso em nenhum invólucro, então
 * lá o esquema viaja no texto do prompt e é conferido aqui depois.
 *
 * O que todo fornecedor devolve é o mesmo:
 *   {ok, text, in, out, error, detail}
 */

/** O leitor como está configurado: ['openai', 'gpt-5-mini']. */
function reader_spec(string $reader): array
{
    $cfg = eixo_config();
    $spec = (string) match ($reader) {
        'B' => $cfg['reader_b'],
        'C' => $cfg['reader_tiebreak'],
        default => $cfg['reader_a'],
    };
    $partes = explode(':', $spec, 2);
    return count($partes) === 2 ? [$partes[0], $partes[1]] : ['replicate', $spec];
}

/** O modelo de um leitor, como aparece no registro de leituras e na tela. */
function reader_model(string $reader): string
{
    [$fornecedor, $modelo] = reader_spec($reader);
    return $fornecedor . ':' . $modelo;
}

/** Onde o fornecedor impõe o esquema, ele não precisa ir escrito no prompt. */
function provider_enforces_schema(string $fornecedor): bool
{
    return in_array($fornecedor, ['openai', 'google'], true);
}

/**
 * Uma geração. Despacha para o fornecedor do leitor.
 * @return array{ok:bool,text:?string,in:?int,out:?int,error:?string,detail:?string}
 */
function model_predict(string $fornecedor, string $modelo, string $prompt): array
{
    return match ($fornecedor) {
        'openai' => openai_predict($modelo, $prompt),
        'google' => google_predict($modelo, $prompt),
        default => replicate_predict($modelo, $prompt),
    };
}

/**
 * Uma transcrição. O documento vai como arquivo e volta texto corrido, sem esquema: é o que
 * repõe a camada de texto de um laudo digitalizado ou de uma foto, para que a conferência de
 * cada valor contra a página continue valendo. Só os fornecedores que recebem arquivo servem.
 *
 * @return array{ok:bool,text:?string,in:?int,out:?int,error:?string,detail:?string}
 */
function model_transcribe(string $fornecedor, string $modelo, string $prompt, string $bytes, string $mime): array
{
    return match ($fornecedor) {
        'openai' => openai_transcribe($modelo, $prompt, $bytes, $mime),
        'google' => google_transcribe($modelo, $prompt, $bytes, $mime),
        default => ['ok' => false, 'text' => null, 'in' => null, 'out' => null,
            'error' => 'no_file_input', 'detail' => $fornecedor . ' não recebe arquivo'],
    };
}

/**
 * O documento como parte de uma mensagem da OpenAI. O PDF vai num campo de arquivo e a foto
 * num campo de imagem: são campos diferentes, e mandar um no lugar do outro derruba a chamada.
 */
function openai_file_part(string $bytes, string $mime): array
{
    $dados = 'data:' . $mime . ';base64,' . base64_encode($bytes);
    return $mime === 'application/pdf'
        ? ['type' => 'file', 'file' => ['filename' => 'laudo.pdf', 'file_data' => $dados]]
        : ['type' => 'image_url', 'image_url' => ['url' => $dados]];
}

function openai_transcribe(string $model, string $prompt, string $bytes, string $mime): array
{
    $cfg = eixo_config();
    $falha = fn (string $code, ?string $detail = null) => ['ok' => false, 'text' => null, 'in' => null, 'out' => null, 'error' => $code, 'detail' => $detail];
    if ((string) $cfg['openai_key'] === '') {
        return $falha('no_api_key');
    }
    $corpo = [
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => $prompt],
            openai_file_part($bytes, $mime),
        ]]],
        'max_completion_tokens' => (int) $cfg['reader_max_tokens'],
        'reasoning_effort' => (string) $cfg['openai_reasoning_effort'],
    ];
    $r = reader_http('https://api.openai.com/v1/chat/completions',
        ['Authorization: Bearer ' . $cfg['openai_key']], $corpo, (int) $cfg['reader_timeout']);
    if ($r['status'] !== 200) {
        return $falha(reader_error_code($r['status'], $r['curl']), provider_detail($r['raw'] ?: $r['curl']));
    }
    $j = json_decode($r['raw'], true);
    if (!is_array($j)) {
        return $falha('bad_response', provider_detail($r['raw']));
    }
    $uso = ['in' => $j['usage']['prompt_tokens'] ?? null, 'out' => $j['usage']['completion_tokens'] ?? null];
    $escolha = $j['choices'][0] ?? [];
    if (($escolha['finish_reason'] ?? '') === 'length') {
        return ['ok' => false, 'text' => null, 'error' => 'truncated', 'detail' => 'a transcrição bateu no teto de tokens'] + $uso;
    }
    $texto = (string) ($escolha['message']['content'] ?? '');
    return trim($texto) === ''
        ? ['ok' => false, 'text' => null, 'error' => 'empty_output', 'detail' => null] + $uso
        : ['ok' => true, 'text' => $texto, 'error' => null, 'detail' => null] + $uso;
}

/**
 * Uma chamada ao Google, com uma saída para o teto de raciocínio. Pedir thinkingBudget 0 é
 * recusado por parte dos modelos com um 400 que não diz qual campo é o problema: medido em
 * 30/10/2026, o gemini-3.5-flash-lite recusa e o gemini-3.8-flash aceita. Como o teto não é
 * essencial — ele existe só para tirar o modelo do laço de repetição —, uma recusa derruba o
 * campo e repete a chamada, em vez de derrubar a leitura.
 */
function google_generate(string $model, array $corpo, int $timeout): array
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $chave = ['x-goog-api-key: ' . eixo_config()['gemini_api_key']];
    $r = reader_http($url, $chave, $corpo, $timeout);
    if ($r['status'] === 400 && isset($corpo['generationConfig']['thinkingConfig'])) {
        unset($corpo['generationConfig']['thinkingConfig']);
        $r = reader_http($url, $chave, $corpo, $timeout);
    }
    return $r;
}

function google_transcribe(string $model, string $prompt, string $bytes, string $mime): array
{
    $cfg = eixo_config();
    $falha = fn (string $code, ?string $detail = null) => ['ok' => false, 'text' => null, 'in' => null, 'out' => null, 'error' => $code, 'detail' => $detail];
    if ((string) $cfg['gemini_api_key'] === '') {
        return $falha('no_api_key');
    }
    $corpo = [
        'contents' => [['parts' => [
            ['text' => $prompt],
            ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
        ]]],
        'generationConfig' => [
            'temperature' => 0,
            'maxOutputTokens' => (int) $cfg['reader_max_tokens'],
            'thinkingConfig' => ['thinkingBudget' => (int) $cfg['google_thinking_budget']],
        ],
    ];
    $r = google_generate($model, $corpo, (int) $cfg['reader_timeout']);
    if ($r['status'] !== 200) {
        return $falha(reader_error_code($r['status'], $r['curl']), provider_detail($r['raw'] ?: $r['curl']));
    }
    $j = json_decode($r['raw'], true);
    if (!is_array($j)) {
        return $falha('bad_response', provider_detail($r['raw']));
    }
    $uso = ['in' => $j['usageMetadata']['promptTokenCount'] ?? null,
        'out' => ($j['usageMetadata']['candidatesTokenCount'] ?? 0) + ($j['usageMetadata']['thoughtsTokenCount'] ?? 0)];
    $candidato = $j['candidates'][0] ?? [];
    if (($candidato['finishReason'] ?? '') === 'MAX_TOKENS') {
        return ['ok' => false, 'text' => null, 'error' => 'truncated', 'detail' => 'a transcrição bateu no teto de tokens'] + $uso;
    }
    // A transcrição é longa e pode voltar repartida em vários trechos de texto.
    $texto = '';
    foreach ($candidato['content']['parts'] ?? [] as $parte) {
        $texto .= (string) ($parte['text'] ?? '');
    }
    return trim($texto) === ''
        ? ['ok' => false, 'text' => null, 'error' => 'empty_output',
            'detail' => provider_detail((string) ($candidato['finishReason'] ?? ''))] + $uso
        : ['ok' => true, 'text' => $texto, 'error' => null, 'detail' => null] + $uso;
}

/** Uma chamada HTTP de leitura, com o corpo em JSON e a resposta crua de volta. */
function reader_http(string $url, array $headers, array $body, int $timeout = 900): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    // 429 e 5xx são passageiros: espera a vez em vez de perder a geração.
    for ($tentativa = 1; ; $tentativa++) {
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        if (($status !== 429 && $status < 500) || $tentativa >= 3) {
            break;
        }
        sleep(10 * $tentativa);
    }
    curl_close($ch);
    return ['raw' => is_string($raw) ? $raw : '', 'status' => $status, 'curl' => $curlError];
}

/** O código de erro de uma resposta HTTP que não deu certo. */
function reader_error_code(int $status, string $curlError): string
{
    if ($curlError !== '') {
        return str_contains($curlError, 'timed out') ? 'timeout' : 'connection';
    }
    return $status === 429 ? 'rate_limited' : 'api_' . $status;
}

/* ── OpenAI ──────────────────────────────────────────────────────────────── */

/**
 * O modo estrito exige `additionalProperties: false` em todo objeto e todo
 * campo listado em `required` — o nosso esquema já é assim, mas garantir aqui
 * deixa o esquema livre para mudar sem quebrar a chamada.
 */
function to_openai_schema(array $s): array
{
    if (isset($s['anyOf'])) {
        return ['anyOf' => array_map('to_openai_schema', $s['anyOf'])];
    }
    $g = $s;
    if (($s['type'] ?? '') === 'object') {
        $props = [];
        foreach ($s['properties'] ?? [] as $k => $v) {
            $props[$k] = to_openai_schema($v);
        }
        $g['properties'] = $props;
        $g['required'] = array_keys($props);
        $g['additionalProperties'] = false;
    }
    if (($s['type'] ?? '') === 'array' && isset($s['items'])) {
        $g['items'] = to_openai_schema($s['items']);
    }
    return $g;
}

function openai_predict(string $model, string $prompt): array
{
    $cfg = eixo_config();
    $falha = fn (string $code, ?string $detail = null) => ['ok' => false, 'text' => null, 'in' => null, 'out' => null, 'error' => $code, 'detail' => $detail];
    if ((string) $cfg['openai_key'] === '') {
        return $falha('no_api_key');
    }
    $corpo = [
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'max_completion_tokens' => (int) $cfg['reader_max_tokens'],
        'reasoning_effort' => (string) $cfg['openai_reasoning_effort'],
        'response_format' => ['type' => 'json_schema', 'json_schema' => [
            'name' => 'laudo', 'strict' => true, 'schema' => to_openai_schema(extraction_schema()),
        ]],
    ];
    $r = reader_http('https://api.openai.com/v1/chat/completions',
        ['Authorization: Bearer ' . $cfg['openai_key']], $corpo, (int) $cfg['reader_timeout']);
    if ($r['status'] !== 200) {
        return $falha(reader_error_code($r['status'], $r['curl']), provider_detail($r['raw'] ?: $r['curl']));
    }
    $j = json_decode($r['raw'], true);
    if (!is_array($j)) {
        return $falha('bad_response', provider_detail($r['raw']));
    }
    $uso = ['in' => $j['usage']['prompt_tokens'] ?? null, 'out' => $j['usage']['completion_tokens'] ?? null];
    $escolha = $j['choices'][0] ?? [];
    if (($escolha['finish_reason'] ?? '') === 'length') {
        return ['ok' => false, 'text' => null, 'error' => 'truncated', 'detail' => 'a resposta bateu no teto de tokens'] + $uso;
    }
    $texto = (string) ($escolha['message']['content'] ?? '');
    if (trim($texto) === '') {
        return ['ok' => false, 'text' => null, 'error' => 'empty_output', 'detail' => null] + $uso;
    }
    return ['ok' => true, 'text' => $texto, 'error' => null, 'detail' => null] + $uso;
}

/* ── Google ──────────────────────────────────────────────────────────────── */

/**
 * O dialeto do Google é um subconjunto do OpenAPI: sem `additionalProperties`,
 * com os tipos em maiúscula e com `nullable` no lugar da união com null.
 */
function to_google_schema(array $s): array
{
    if (isset($s['anyOf'])) {
        $reais = array_values(array_filter($s['anyOf'], fn ($b) => ($b['type'] ?? '') !== 'null'));
        $g = to_google_schema($reais[0] ?? ['type' => 'string']);
        $g['nullable'] = true;
        return $g;
    }
    $tipos = ['string' => 'STRING', 'number' => 'NUMBER', 'integer' => 'INTEGER',
        'boolean' => 'BOOLEAN', 'array' => 'ARRAY', 'object' => 'OBJECT'];
    $g = ['type' => $tipos[$s['type'] ?? 'string'] ?? 'STRING'];
    if (isset($s['enum'])) {
        // O Google recusa string vazia num enum; nesse caso o campo fica livre.
        $valores = array_values(array_filter($s['enum'], fn ($v) => $v !== ''));
        if (count($valores) === count($s['enum'])) {
            $g['enum'] = $valores;
        }
    }
    if (($s['type'] ?? '') === 'object') {
        $props = [];
        foreach ($s['properties'] ?? [] as $k => $v) {
            $props[$k] = to_google_schema($v);
        }
        $g['properties'] = $props;
        $g['propertyOrdering'] = array_keys($props);
        if (!empty($s['required'])) {
            $g['required'] = array_values($s['required']);
        }
    }
    if (($s['type'] ?? '') === 'array' && isset($s['items'])) {
        $g['items'] = to_google_schema($s['items']);
    }
    return $g;
}

function google_predict(string $model, string $prompt): array
{
    $cfg = eixo_config();
    $falha = fn (string $code, ?string $detail = null) => ['ok' => false, 'text' => null, 'in' => null, 'out' => null, 'error' => $code, 'detail' => $detail];
    if ((string) $cfg['gemini_api_key'] === '') {
        return $falha('no_api_key');
    }
    $corpo = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0,
            'responseMimeType' => 'application/json',
            'responseSchema' => to_google_schema(extraction_schema()),
            'maxOutputTokens' => (int) $cfg['reader_max_tokens'],
            // Raciocínio ligado é o que põe estes modelos no laço de repetição.
            'thinkingConfig' => ['thinkingBudget' => (int) $cfg['google_thinking_budget']],
        ],
    ];
    $r = google_generate($model, $corpo, (int) $cfg['reader_timeout']);
    if ($r['status'] !== 200) {
        return $falha(reader_error_code($r['status'], $r['curl']), provider_detail($r['raw'] ?: $r['curl']));
    }
    $j = json_decode($r['raw'], true);
    if (!is_array($j)) {
        return $falha('bad_response', provider_detail($r['raw']));
    }
    $uso = ['in' => $j['usageMetadata']['promptTokenCount'] ?? null,
        'out' => ($j['usageMetadata']['candidatesTokenCount'] ?? 0) + ($j['usageMetadata']['thoughtsTokenCount'] ?? 0)];
    $candidato = $j['candidates'][0] ?? [];
    if (($candidato['finishReason'] ?? '') === 'MAX_TOKENS') {
        return ['ok' => false, 'text' => null, 'error' => 'truncated', 'detail' => 'a resposta bateu no teto de tokens'] + $uso;
    }
    $texto = (string) ($candidato['content']['parts'][0]['text'] ?? '');
    if (trim($texto) === '') {
        return ['ok' => false, 'text' => null, 'error' => 'empty_output',
            'detail' => provider_detail((string) ($candidato['finishReason'] ?? ''))] + $uso;
    }
    return ['ok' => true, 'text' => $texto, 'error' => null, 'detail' => null] + $uso;
}

/* ── Replicate ───────────────────────────────────────────────────────────
 * Aqui o esquema não é imposto por ninguém: ele viaja no prompt e a resposta
 * é conferida depois. Cada modelo declara a própria entrada, e mandar um campo
 * que ele não declara derruba a chamada com 422.
 */
/**
 * The input for one model. Every model on Replicate declares its own input: the ceiling on
 * the reply is max_tokens for one, max_completion_tokens for another, max_output_tokens for
 * a third, and some take no temperature at all. Sending a field a model does not declare
 * fails the call with 422, so the declaration is read and obeyed.
 */
function replicate_input(string $model, string $prompt): array
{
    $ceiling = (int) eixo_config()['reader_max_tokens'];
    $props = model_input_properties($model);
    if (!$props) {
        return ['prompt' => $prompt, 'max_tokens' => $ceiling];
    }
    $input = ['prompt' => $prompt];
    foreach (['max_tokens', 'max_completion_tokens', 'max_output_tokens', 'max_new_tokens'] as $field) {
        if (isset($props[$field])) {
            $declared = $props[$field]['maximum'] ?? null;
            $input[$field] = $declared === null ? $ceiling : min($ceiling, (int) $declared);
            break;
        }
    }
    if (isset($props['temperature'])) {
        $input['temperature'] = 0;
    }
    return $input;
}

/**
 * What a model declares it accepts, asked once per run. An unanswered question returns
 * nothing, and the caller falls back to the field every open-weight model here takes.
 * @return array<string,array>
 */
function model_input_properties(string $model): array
{
    static $known = [];
    if (!array_key_exists($model, $known)) {
        $res = replicate_http('GET', rtrim((string) eixo_config()['replicate_base_url'], '/') . '/models/' . $model, null);
        $known[$model] = $res['ok']
            ? ($res['json']['latest_version']['openapi_schema']['components']['schemas']['Input']['properties'] ?? [])
            : [];
    }
    return $known[$model];
}

/**
 * One prediction: created, then asked for until it reaches a final state. Nothing holds the
 * connection, so a reading that takes minutes never depends on one long-lived request.
 * @return array{ok:bool,text:?string,in:?int,out:?int,error:?string,detail:?string}
 */
function replicate_predict(string $model, string $prompt): array
{
    $cfg = eixo_config();
    $started = microtime(true);
    $deadline = $started + (float) $cfg['reader_timeout'];
    $input = replicate_input($model, $prompt);
    $res = replicate_http('POST', rtrim((string) $cfg['replicate_base_url'], '/') . '/models/' . $model . '/predictions', ['input' => $input]);
    if (!$res['ok']) {
        return $res;
    }
    $pred = $res['json'];
    $blips = 0;
    while (in_array($pred['status'] ?? '', ['starting', 'processing'], true)) {
        if (microtime(true) > $deadline) {
            return ['ok' => false, 'text' => null, 'in' => null, 'out' => null, 'error' => 'timeout', 'detail' => null];
        }
        sleep(microtime(true) - $started < 60 ? 2 : 5);
        $get = replicate_http('GET', (string) ($pred['urls']['get'] ?? ''), null);
        if (!$get['ok']) {
            // A blip while asking for the state should not throw away a generation already paid for.
            if (++$blips >= 5) {
                return $get;
            }
            continue;
        }
        $blips = 0;
        $pred = $get['json'];
    }
    $metrics = is_array($pred['metrics'] ?? null) ? $pred['metrics'] : [];
    $usage = ['in' => $metrics['input_token_count'] ?? null, 'out' => $metrics['output_token_count'] ?? null];
    if (($pred['status'] ?? '') !== 'succeeded') {
        $code = ($pred['status'] ?? '') === 'canceled' ? 'canceled' : 'prediction_failed';
        return ['ok' => false, 'text' => null, 'error' => $code, 'detail' => provider_detail((string) ($pred['error'] ?? ''))] + $usage;
    }
    $output = $pred['output'] ?? null;
    $text = is_array($output) ? implode('', array_map('strval', $output)) : (is_string($output) ? $output : null);
    if ($text === null || trim($text) === '') {
        return ['ok' => false, 'text' => null, 'error' => 'empty_output', 'detail' => null] + $usage;
    }
    return ['ok' => true, 'text' => $text, 'error' => null, 'detail' => null] + $usage;
}

/**
 * One HTTP call to the Replicate API.
 * @return array{ok:bool,json:?array,text:?string,in:?int,out:?int,error:?string,detail:?string}
 */
function replicate_http(string $method, string $url, ?array $body): array
{
    $cfg = eixo_config();
    $fail = fn (string $code, ?string $detail = null) => ['ok' => false, 'json' => null, 'text' => null, 'in' => null, 'out' => null, 'error' => $code, 'detail' => $detail];
    if ($url === '') {
        return $fail('bad_response');
    }
    // No 'Prefer: wait': a conexão presa estourava o tempo do cURL e deixava para trás uma
    // predição já criada, paga e sem dono. O POST volta na hora e o estado vem por consulta.
    $headers = ['Authorization: Bearer ' . $cfg['replicate_key'], 'Content-Type: application/json'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    // The account's prediction rate limit is a few per minute, so a throttled call waits its
    // turn instead of failing the document and paying again for the reading that already worked.
    for ($attempt = 1; ; $attempt++) {
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        if ($status !== 429 || $attempt >= 3) {
            break;
        }
        sleep(12);
    }
    curl_close($ch);
    if (!is_string($raw)) {
        return $fail('connection', provider_detail($curlError));
    }
    if ($status === 429) {
        return $fail('rate_limited', provider_detail($raw));
    }
    if ($status >= 400) {
        return $fail('api_' . $status, provider_detail($raw));
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return $fail('bad_response', provider_detail($raw));
    }
    return ['ok' => true, 'json' => $json, 'text' => null, 'in' => null, 'out' => null, 'error' => null, 'detail' => null];
}

/** As chaves que faltam para os leitores configurados. */
function reader_keys_missing(): array
{
    $cfg = eixo_config();
    $chave = ['openai' => 'openai_key', 'google' => 'gemini_api_key', 'replicate' => 'replicate_key'];
    $falta = [];
    foreach (['A', 'B', 'C'] as $reader) {
        [$fornecedor] = reader_spec($reader);
        $k = $chave[$fornecedor] ?? null;
        if ($k !== null && (string) ($cfg[$k] ?? '') === '') {
            $falta[$k] = $k;
        }
    }
    return array_values($falta);
}
