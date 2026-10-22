<?php
declare(strict_types=1);

/*
 * Extraction pipeline. The models only transcribe; code compares, checks and decides.
 *
 *   A: Claude, on the PDF text layer when there is one (minimized), else on the file
 *   B: Gemini, on the file itself (page images + text)
 *   C: Claude, on the file, only when A and B disagree (tie-break)
 *
 * A value is loaded only when two readings agree and it passes the cross-check
 * (RN-02); everything else goes to quarantine (RN-10).
 */

/* ── Output schema shared by every reader ───────────────────────────────── */

function nul(array $schema): array
{
    return ['anyOf' => [$schema, ['type' => 'null']]];
}

function extraction_schema(): array
{
    $str = ['type' => 'string'];
    $num = ['type' => 'number'];
    $int = ['type' => 'integer'];
    $range = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['kind', 'condition', 'label', 'low', 'high'],
        'properties' => [
            'kind' => ['type' => 'string', 'enum' => ['interval', 'upper', 'lower', 'category', 'text']],
            'condition' => nul($str),
            'label' => nul($str),
            'low' => nul($num),
            'high' => nul($num),
        ],
    ];
    $result = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['name_printed', 'section', 'value_printed', 'operator', 'value_number', 'value_text', 'unit_printed',
            'reference_printed', 'reference_ranges', 'flag_printed', 'method', 'specimen', 'column', 'result_date', 'page', 'quote',
            'loinc_code', 'loinc_property', 'loinc_confidence'],
        'properties' => [
            'name_printed' => $str,
            'section' => nul($str),
            'value_printed' => $str,
            'operator' => ['type' => 'string', 'enum' => ['', '<', '<=', '>', '>=']],
            'value_number' => nul($num),
            'value_text' => nul($str),
            'unit_printed' => nul($str),
            'reference_printed' => nul($str),
            'reference_ranges' => ['type' => 'array', 'items' => $range],
            'flag_printed' => nul($str),
            'method' => nul($str),
            'specimen' => nul($str),
            'column' => ['type' => 'string', 'enum' => ['current', 'previous']],
            'result_date' => nul($str),
            'page' => $int,
            'quote' => $str,
            'loinc_code' => nul($str),
            'loinc_property' => nul($str),
            'loinc_confidence' => $num,
        ],
    ];
    $metric = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['name_printed', 'value_printed', 'value_number', 'unit_printed', 'page', 'quote'],
        'properties' => ['name_printed' => $str, 'value_printed' => $str, 'value_number' => nul($num), 'unit_printed' => nul($str), 'page' => $int, 'quote' => $str],
    ];
    $rx = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['drug', 'strength', 'form', 'route', 'instructions', 'page', 'quote'],
        'properties' => ['drug' => $str, 'strength' => nul($str), 'form' => nul($str), 'route' => nul($str), 'instructions' => nul($str), 'page' => $int, 'quote' => $str],
    ];
    return [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['doc_type', 'laboratory', 'collection_date', 'results', 'body_composition', 'prescription_items'],
        'properties' => [
            'doc_type' => ['type' => 'string', 'enum' => ['lab_report', 'bioimpedance', 'prescription', 'imaging_report', 'other']],
            'laboratory' => nul($str),
            'collection_date' => nul($str),
            'results' => ['type' => 'array', 'items' => $result],
            'body_composition' => nul([
                'type' => 'object', 'additionalProperties' => false,
                'required' => ['device_brand', 'device_model', 'technology', 'measured_date', 'metrics'],
                'properties' => [
                    'device_brand' => nul($str), 'device_model' => nul($str), 'technology' => nul($str),
                    'measured_date' => nul($str), 'metrics' => ['type' => 'array', 'items' => $metric],
                ],
            ]),
            'prescription_items' => ['type' => 'array', 'items' => $rx],
        ],
    ];
}

function extraction_instructions(): string
{
    return <<<'TXT'
You transcribe Brazilian medical documents (lab reports, body-composition printouts, prescriptions, imaging reports) into the JSON schema you are given. You are a transcriber, not a clinician.

Rules:
- Copy, never compute. Do not convert units, do not calculate indices, do not fix values that look wrong, do not fill in anything that is not printed. When something is not printed, use null.
- One entry in "results" per printed result. Results printed in a "previous results" / "resultados anteriores" column are separate entries with column "previous" and the date printed for that column in result_date. Current results use column "current" and result_date null unless the report prints a different date for that test.
- value_printed is the result exactly as printed, including any operator or text ("< 0,01", "Não reagente", "5,4"). operator holds only the comparison sign. value_number is the numeric part with a dot as decimal separator and no thousands separator (Brazilian "1.234,5" is 1234.5); null when the result is not numeric. value_text holds non-numeric results.
- unit_printed is the unit as printed. reference_printed is the reference text as printed. reference_ranges structures it: one entry per range or category; condition holds who it applies to as printed (sex, age, cycle phase, trimester) or null.
- flag_printed: only an abnormality mark printed by the lab (H, L, *, "Alto", bold note). null if none. Never add your own.
- section: the report section heading the result sits under (e.g. "Hormônios", "Bioquímica").
- page is the 1-based page number. quote is the exact line of text where the value appears, verbatim, including the value.
- loinc_code: your best LOINC code for the test given name, specimen, unit and method; loinc_property is that code's property (MCnc, SCnc, CCnc, ACnc, NCnc, MFr, NFr, SFr, VFr, Ratio, Mass, Len, Time, Pres, ...). loinc_confidence from 0 to 1. Use null and 0 when unsure.
- Dates as YYYY-MM-DD. collection_date is the specimen collection date ("coleta"), not the release date.
- body_composition: for bioimpedance printouts, the device and every metric printed (weight, fat mass, % fat, lean mass, skeletal muscle mass, total/intra/extracellular water, phase angle, visceral fat, BMR...). Otherwise null.
- prescription_items: medications or supplements on a prescription. Otherwise an empty array.
- Never output the patient's name, CPF, address, request number or the requesting physician's data, even inside quotes; replace them with "[omitido]".
TXT;
}

/* ── Document text layer and minimization ───────────────────────────────── */

/** @return list<string>|null per-page text of a PDF, or null when unavailable */
function pdf_pages_text(string $pdfBytes): ?array
{
    $bin = (string) eixo_config()['pdftotext'];
    // Shared hosting disables shell_exec and friends; proc_open with an argv array needs no shell.
    if (!is_executable($bin) || !function_exists('proc_open')) {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'eixo');
    file_put_contents($tmp, $pdfBytes);
    $out = null;
    try {
        $proc = @proc_open([$bin, '-layout', '-enc', 'UTF-8', $tmp, '-'], [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        if (is_resource($proc)) {
            $out = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            proc_close($proc);
        }
    } finally {
        unlink($tmp);
    }
    if (!is_string($out) || trim($out) === '') {
        return null;
    }
    $pages = explode("\f", $out);
    if (end($pages) === '') {
        array_pop($pages);
    }
    return $pages;
}

/** Removes direct identifiers before text goes to a model (RNF-07). Values and units stay. */
function minimize_text(string $text): string
{
    $text = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/u', '[omitido]', $text);
    $text = preg_replace('/^(\s*(?:paciente|nome|cliente|m[ée]dico(?:\s+solicitante)?|solicitante|dr\.?|dra\.?|crm|pedido|atendimento|prontu[áa]rio|endere[çc]o|cpf|rg|cart[ãa]o|conv[êe]nio)\b[^:\n]{0,20}:)[^\n]*$/imu', '$1 [omitido]', $text);
    $text = preg_replace('/\bCRM[\s\-\/]*[A-Z]{0,2}[\s\-\/]*\d{3,7}\b/u', '[omitido]', $text);
    return $text;
}

/* ── Readers ─────────────────────────────────────────────────────────────── */

/**
 * @param array{bytes:string,mime:string,pages:?list<string>,sha256:string} $doc
 * @return array{ok:bool,payload:?array,provider:string,model:string,mode:string,in:?int,out:?int,error:?string}
 */
function read_document(string $reader, array $doc, string $extraInstruction = ''): array
{
    $providers = (string) eixo_config()['providers'];
    if ($providers === 'fake') {
        return fake_read($reader, $doc);
    }
    return $reader === 'B' ? gemini_read($doc, $extraInstruction) : claude_read($reader, $doc, $extraInstruction);
}

function claude_read(string $reader, array $doc, string $extraInstruction): array
{
    $cfg = eixo_config();
    $model = (string) $cfg['claude_model'];
    $useText = $reader === 'A' && $doc['pages'] !== null && mb_strlen(implode('', $doc['pages'])) > 200;
    $mode = $useText ? 'text_layer' : 'file';
    $base = ['ok' => false, 'payload' => null, 'provider' => 'anthropic', 'model' => $model, 'mode' => $mode, 'in' => null, 'out' => null, 'error' => null];
    if ($cfg['anthropic_api_key'] === '') {
        return ['error' => 'no_api_key'] + $base;
    }
    $content = [];
    if ($useText) {
        $text = '';
        foreach ($doc['pages'] as $i => $p) {
            $text .= "=== Página " . ($i + 1) . " ===\n" . minimize_text($p) . "\n";
        }
        $content[] = ['type' => 'text', 'text' => "Document text layer, page by page:\n\n" . $text];
    } elseif ($doc['mime'] === 'application/pdf') {
        $content[] = ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode($doc['bytes'])]];
    } else {
        $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $doc['mime'], 'data' => base64_encode($doc['bytes'])]];
    }
    $content[] = ['type' => 'text', 'text' => 'Transcribe this document into the schema.' . ($extraInstruction ? "\n\n" . $extraInstruction : '')];

    try {
        $client = new Anthropic\Client(apiKey: (string) $cfg['anthropic_api_key'], baseUrl: $cfg['anthropic_base_url'] ?: null);
        $message = $client->beta->messages->create(
            model: $model,
            maxTokens: 16000,
            system: extraction_instructions(),
            messages: [['role' => 'user', 'content' => $content]],
            outputConfig: ['effort' => 'high', 'format' => ['type' => 'json_schema', 'schema' => extraction_schema()]],
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
            requestOptions: ['timeout' => 240.0, 'maxRetries' => 2],
        );
    } catch (Anthropic\Core\Exceptions\RateLimitException $e) {
        return ['error' => 'rate_limited'] + $base;
    } catch (Anthropic\Core\Exceptions\APIStatusException $e) {
        return ['error' => 'api_' . ($e->type?->value ?? 'status')] + $base;
    } catch (Anthropic\Core\Exceptions\APIConnectionException $e) {
        return ['error' => 'connection'] + $base;
    }
    $base['in'] = $message->usage->inputTokens ?? null;
    $base['out'] = $message->usage->outputTokens ?? null;
    if ($message->stopReason === 'refusal') {
        return ['error' => 'refusal'] + $base;
    }
    if ($message->stopReason === 'max_tokens') {
        return ['error' => 'truncated'] + $base;
    }
    foreach ($message->content as $block) {
        if ($block->type === 'text') {
            $data = json_decode($block->text, true);
            if (is_array($data)) {
                return ['ok' => true, 'payload' => $data] + $base;
            }
        }
    }
    return ['error' => 'bad_json'] + $base;
}

function gemini_read(array $doc, string $extraInstruction): array
{
    $cfg = eixo_config();
    $model = (string) $cfg['gemini_model'];
    $base = ['ok' => false, 'payload' => null, 'provider' => 'google', 'model' => $model, 'mode' => 'file', 'in' => null, 'out' => null, 'error' => null];
    if ($cfg['gemini_api_key'] === '') {
        return ['error' => 'no_api_key'] + $base;
    }
    $part = $doc['mime'] === 'application/pdf'
        ? ['type' => 'document', 'data' => base64_encode($doc['bytes']), 'mime_type' => 'application/pdf']
        : ['type' => 'image', 'data' => base64_encode($doc['bytes']), 'mime_type' => $doc['mime']];
    $body = [
        'model' => $model,
        'store' => false,
        'system_instruction' => extraction_instructions(),
        'input' => [$part, ['type' => 'text', 'text' => 'Transcribe this document into the schema.' . ($extraInstruction ? "\n\n" . $extraInstruction : '')]],
        'response_format' => ['type' => 'text', 'mime_type' => 'application/json', 'schema' => extraction_schema()],
    ];
    $ch = curl_init(rtrim((string) $cfg['gemini_base_url'], '/') . '/interactions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $cfg['gemini_api_key']],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 240,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!is_string($raw)) {
        return ['error' => 'connection'] + $base;
    }
    if ($status === 429) {
        return ['error' => 'rate_limited'] + $base;
    }
    if ($status >= 400) {
        return ['error' => 'api_' . $status] + $base;
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['error' => 'bad_json'] + $base;
    }
    $usage = $json['usage'] ?? $json['usage_metadata'] ?? [];
    $base['in'] = $usage['total_input_tokens'] ?? $usage['input_tokens'] ?? $usage['prompt_token_count'] ?? null;
    $base['out'] = $usage['total_output_tokens'] ?? $usage['output_tokens'] ?? $usage['candidates_token_count'] ?? null;
    $text = gemini_output_text($json);
    $data = $text === null ? null : json_decode($text, true);
    if (!is_array($data)) {
        return ['error' => 'bad_json'] + $base;
    }
    return ['ok' => true, 'payload' => $data] + $base;
}

/** Final model text of an Interactions API response: output_text, else the model_output step text. */
function gemini_output_text(array $json): ?string
{
    if (isset($json['output_text']) && is_string($json['output_text'])) {
        return $json['output_text'];
    }
    $texts = [];
    foreach (($json['steps'] ?? $json['outputs'] ?? []) as $step) {
        if (!is_array($step) || (($step['type'] ?? '') !== 'model_output' && ($step['type'] ?? '') !== 'text')) {
            continue;
        }
        array_walk_recursive($step, function ($v, $k) use (&$texts) {
            if ($k === 'text' && is_string($v)) {
                $texts[] = $v;
            }
        });
    }
    return $texts ? implode('', $texts) : null;
}

/** Test provider: readings come from <data>/fake/<sha256>.<reader>.json (or .json for every reader). */
function fake_read(string $reader, array $doc): array
{
    $dir = eixo_data_dir() . '/fake/';
    $file = is_file($dir . $doc['sha256'] . '.' . $reader . '.json') ? $dir . $doc['sha256'] . '.' . $reader . '.json' : $dir . $doc['sha256'] . '.json';
    $base = ['provider' => 'fake', 'model' => 'fake-' . $reader, 'mode' => $reader === 'A' && $doc['pages'] ? 'text_layer' : 'file', 'in' => 0, 'out' => 0, 'error' => null];
    if (!is_file($file)) {
        return ['ok' => false, 'payload' => null, 'error' => 'no_fixture'] + $base;
    }
    return ['ok' => true, 'payload' => json_decode((string) file_get_contents($file), true)] + $base;
}

/* ── Comparison ─────────────────────────────────────────────────────────── */

function norm_text(?string $s): string
{
    $s = mb_strtolower(trim((string) $s));
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'í' => 'i', 'ï' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c', 'µ' => 'u', 'μ' => 'u']);
    return trim(preg_replace('/[^a-z0-9%<>=.,\/]+/', ' ', $s));
}

function norm_unit(?string $u): string
{
    $ucum = ucum_from_printed($u);
    return $ucum ?? str_replace(' ', '', norm_text($u));
}

/** Matching key of a result within one reading. */
function result_key(array $r): string
{
    return norm_text($r['name_printed'] ?? '') . '|' . ($r['column'] ?? 'current') . '|' . ($r['result_date'] ?? '') . '|' . (int) ($r['page'] ?? 0);
}

/** @return array<string,array> results keyed by result_key, with #n suffix for repeats */
function keyed_results(array $payload): array
{
    $out = [];
    foreach ($payload['results'] ?? [] as $r) {
        if (!is_array($r)) {
            continue;
        }
        $k = result_key($r);
        $n = 1;
        while (isset($out[$k . '#' . $n])) {
            $n++;
        }
        $out[$k . '#' . $n] = $r;
    }
    return $out;
}

function same_value(array $a, array $b): bool
{
    if (($a['operator'] ?? '') !== ($b['operator'] ?? '')) {
        return false;
    }
    $an = $a['value_number'] ?? null;
    $bn = $b['value_number'] ?? null;
    if ($an !== null || $bn !== null) {
        if ($an === null || $bn === null || abs((float) $an - (float) $bn) > 1e-9 * max(1.0, abs((float) $an))) {
            return false;
        }
    } elseif (norm_text($a['value_text'] ?? $a['value_printed'] ?? '') !== norm_text($b['value_text'] ?? $b['value_printed'] ?? '')) {
        return false;
    }
    return norm_unit($a['unit_printed'] ?? null) === norm_unit($b['unit_printed'] ?? null);
}

/* ── Cross-check (RN-02) ────────────────────────────────────────────────── */

/** Number renderings that may appear on a Brazilian report for this value. */
function number_forms(float $v): array
{
    $forms = [];
    foreach ([0, 1, 2, 3, 4] as $dec) {
        if (abs(round($v, $dec) - $v) < 1e-9) {
            $forms[] = number_format($v, $dec, ',', '');
            $forms[] = number_format($v, $dec, '.', '');
            $forms[] = number_format($v, $dec, ',', '.');
            $forms[] = number_format($v, $dec, '.', ',');
        }
    }
    // Trailing zeros printed by the lab (5,40)
    foreach ([1, 2, 3] as $dec) {
        $forms[] = number_format($v, $dec, ',', '');
        $forms[] = number_format($v, $dec, ',', '.');
    }
    return array_values(array_unique($forms));
}

function text_contains_value(string $haystack, array $r): bool
{
    $hay = ' ' . preg_replace('/\s+/u', ' ', mb_strtolower($haystack)) . ' ';
    if (($r['value_number'] ?? null) !== null) {
        foreach (number_forms((float) $r['value_number']) as $f) {
            if (preg_match('/(?<![\d.,])' . preg_quote($f, '/') . '(?![\d]|[.,]\d)/u', $hay)) {
                return true;
            }
        }
        return false;
    }
    $needle = norm_text($r['value_text'] ?? $r['value_printed'] ?? '');
    return $needle !== '' && str_contains(norm_text($haystack), $needle);
}

/**
 * @param list<string>|null $pages document text layer
 * @param list<float> $history loaded values of the same series and lab, already in this unit
 * @return array{pass:bool,checks:array<string,string>}
 */
function cross_check(array $r, ?array $pages, array $otherQuotes, array $history): array
{
    $checks = [];
    // 1. The value exists in the page text (text layer), else in both readings' quotes.
    $page = (int) ($r['page'] ?? 0);
    if ($pages !== null && isset($pages[$page - 1]) && trim($pages[$page - 1]) !== '') {
        $checks['text'] = text_contains_value($pages[$page - 1], $r) ? 'ok' : 'missing';
    } else {
        $all = array_merge([(string) ($r['quote'] ?? '')], $otherQuotes);
        $checks['text'] = array_reduce($all, fn ($c, $qt) => $c && text_contains_value($qt, $r), true) ? 'quotes' : 'missing';
    }
    // 2. Unit compatible with the proposed LOINC property.
    $ucum = ucum_from_printed($r['unit_printed'] ?? null);
    $checks['unit'] = ($r['value_number'] ?? null) === null ? 'n/a' : unit_matches_property($ucum, $r['loinc_property'] ?? null);
    // 3. Order of magnitude against the printed numeric range and the patient's history in the same lab.
    $checks['magnitude'] = 'ok';
    $v = $r['value_number'] ?? null;
    if ($v !== null && ($r['operator'] ?? '') === '') {
        $v = (float) $v;
        foreach ($r['reference_ranges'] ?? [] as $rg) {
            $hi = $rg['high'] ?? null;
            $lo = $rg['low'] ?? null;
            if ($hi !== null && $hi > 0 && $v > $hi * 100) {
                $checks['magnitude'] = 'range';
            }
            if ($lo !== null && $lo > 0 && $v > 0 && $v < $lo / 100) {
                $checks['magnitude'] = 'range';
            }
        }
        if ($history && $v > 0) {
            sort($history);
            $median = $history[intdiv(count($history), 2)];
            if ($median > 0 && ($v > $median * 30 || $v < $median / 30)) {
                $checks['magnitude'] = 'history';
            }
        }
    }
    // 4. A usable date.
    $date = ($r['column'] ?? 'current') === 'previous' ? ($r['result_date'] ?? null) : ($r['result_date'] ?? $r['_collection_date'] ?? null);
    $checks['date'] = valid_date($date) && $date <= gmdate('Y-m-d', time() + 86400) ? 'ok' : 'missing';
    $pass = $checks['text'] !== 'missing' && $checks['unit'] !== 'mismatch' && $checks['magnitude'] === 'ok' && $checks['date'] === 'ok';
    return ['pass' => $pass, 'checks' => $checks];
}

/* ── Pipeline ───────────────────────────────────────────────────────────── */

function store_reading(int $userId, int $docId, string $reader, array $res): void
{
    q('INSERT INTO readings(document_id, reader, provider, model, input_mode, prompt_version, ok, input_tokens, output_tokens, payload_enc, error_code, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [
        $docId, $reader, $res['provider'], $res['model'], $res['mode'], EIXO_PROMPT_VERSION, $res['ok'] ? 1 : 0,
        $res['in'], $res['out'], $res['payload'] !== null ? enc($userId, $res['payload']) : null, $res['error'], now(),
    ]);
}

/**
 * Runs the readings, the comparison and the checks for one document, then stores results.
 * Throws on transient failures so the job is retried.
 */
function process_document(int $docId): void
{
    $doc = row('SELECT * FROM documents WHERE id = ?', [$docId]);
    if (!$doc) {
        return;
    }
    $userId = (int) $doc['user_id'];
    if (!consents($userId)['ai']) {
        q("UPDATE documents SET status = 'stored', error_code = 'no_ai_consent' WHERE id = ?", [$docId]);
        return;
    }
    $bytes = load_blob($userId, $doc['blob_name']);
    $pages = $doc['mime'] === 'application/pdf' ? pdf_pages_text($bytes) : null;
    $input = ['bytes' => $bytes, 'mime' => $doc['mime'], 'pages' => $pages, 'sha256' => $doc['sha256']];

    $a = read_document('A', $input);
    store_reading($userId, $docId, 'A', $a);
    $b = read_document('B', $input);
    store_reading($userId, $docId, 'B', $b);
    if (!$a['ok'] || !$b['ok']) {
        $transient = in_array($a['error'] ?? $b['error'], ['rate_limited', 'connection', 'api_overloaded_error', 'api_500', 'api_503'], true);
        if ($transient) {
            throw new RuntimeException('transient:' . ($a['error'] ?? $b['error']));
        }
        q("UPDATE documents SET status = 'failed', error_code = ?, processed_at = ? WHERE id = ?", [$a['error'] ?? $b['error'], now(), $docId]);
        audit($userId, 'system', 'document.failed', 'document:' . $docId);
        return;
    }

    $outcome = reconcile($userId, $docId, $a['payload'], $b['payload'], $pages, $input);
    persist_outcome($userId, $doc, $outcome, $pages);
}

/**
 * Compares readings A and B, asks C on disputes, and runs the cross-check.
 * @return array<string,mixed>
 */
function reconcile(int $userId, int $docId, array $pa, array $pb, ?array $pages, array $input): array
{
    $agreeMeta = fn (string $f) => norm_text($pa[$f] ?? '') === norm_text($pb[$f] ?? '') ? ($pa[$f] ?? null) : null;
    $collection = valid_date($pa['collection_date'] ?? null) && ($pa['collection_date'] ?? null) === ($pb['collection_date'] ?? null) ? $pa['collection_date'] : null;
    $lab = $agreeMeta('laboratory');
    $docType = ($pa['doc_type'] ?? '') === ($pb['doc_type'] ?? '') ? $pa['doc_type'] : ($pa['doc_type'] ?? 'other');

    $ka = keyed_results($pa);
    $kb = keyed_results($pb);
    $agreed = [];
    $disputed = [];
    foreach (array_unique(array_merge(array_keys($ka), array_keys($kb))) as $k) {
        if (isset($ka[$k], $kb[$k]) && same_value($ka[$k], $kb[$k])) {
            $agreed[$k] = [$ka[$k], $kb[$k], 'ab'];
        } else {
            $disputed[$k] = [$ka[$k] ?? null, $kb[$k] ?? null];
        }
    }

    // Tie-break: one more reading, on the file, that must match one of the two.
    $kc = [];
    if ($disputed) {
        $list = [];
        foreach ($disputed as $k => [$ra, $rb]) {
            $r = $ra ?? $rb;
            $list[] = '- "' . ($r['name_printed'] ?? '') . '" on page ' . (int) ($r['page'] ?? 0) . ' (' . ($r['column'] ?? 'current') . ')';
        }
        $c = read_document('C', $input, "This is a verification reading. Two earlier readings disagreed on these items; transcribe the whole document carefully, paying special attention to them:\n" . implode("\n", array_slice($list, 0, 80)));
        store_reading($userId, $docId, 'C', $c);
        if ($c['ok']) {
            $kc = keyed_results($c['payload']);
        }
    }

    $final = [];
    foreach ($agreed as $k => [$ra, $rb, $how]) {
        $final[] = ['r' => $ra, 'other' => $rb, 'resolution' => 'ab', 'disputed' => null];
    }
    foreach ($disputed as $k => [$ra, $rb]) {
        $rc = $kc[$k] ?? null;
        if ($rc && $ra && same_value($rc, $ra)) {
            $final[] = ['r' => $ra, 'other' => $rc, 'resolution' => 'ac', 'disputed' => $rb];
        } elseif ($rc && $rb && same_value($rc, $rb)) {
            $final[] = ['r' => $rb, 'other' => $rc, 'resolution' => 'bc', 'disputed' => $ra];
        } else {
            $final[] = ['r' => $ra ?? $rb, 'other' => $rb ?? $ra, 'resolution' => 'unresolved', 'disputed' => $rc];
        }
    }

    // Body composition: a metric counts when both readings print the same name, value and unit.
    $body = null;
    if (!empty($pa['body_composition']) && !empty($pb['body_composition'])) {
        $ma = [];
        foreach ($pa['body_composition']['metrics'] ?? [] as $m) {
            $ma[norm_text($m['name_printed'] ?? '')] = $m;
        }
        $metrics = [];
        $unconfirmed = [];
        foreach ($pb['body_composition']['metrics'] ?? [] as $m) {
            $k = norm_text($m['name_printed'] ?? '');
            $x = $ma[$k] ?? null;
            if ($x && ($x['value_number'] ?? null) !== null && abs((float) $x['value_number'] - (float) ($m['value_number'] ?? NAN)) < 1e-9
                && norm_unit($x['unit_printed'] ?? null) === norm_unit($m['unit_printed'] ?? null)
                && ($pages === null || text_contains_value($pages[(int) ($x['page'] ?? 1) - 1] ?? '', $x))) {
                $metrics[] = $x;
                unset($ma[$k]);
            } else {
                $unconfirmed[] = $x ?? $m;
            }
        }
        $unconfirmed = array_merge($unconfirmed, array_values($ma));
        $ba = $pa['body_composition'];
        $bb = $pb['body_composition'];
        $sameDevice = norm_text(($ba['device_brand'] ?? '') . ' ' . ($ba['device_model'] ?? '')) === norm_text(($bb['device_brand'] ?? '') . ' ' . ($bb['device_model'] ?? ''));
        $date = valid_date($ba['measured_date'] ?? null) && ($ba['measured_date'] ?? null) === ($bb['measured_date'] ?? null) ? $ba['measured_date'] : $collection;
        $body = [
            'device' => $sameDevice ? ['brand' => $ba['device_brand'] ?? null, 'model' => $ba['device_model'] ?? null, 'technology' => $ba['technology'] ?? null] : null,
            'date' => $date,
            'metrics' => $metrics,
            'unconfirmed' => $unconfirmed,
        ];
    }

    // Prescription items: kept as suggestions when both readings list the same drug and strength.
    $rx = [];
    $rxb = [];
    foreach ($pb['prescription_items'] ?? [] as $it) {
        $rxb[norm_text(($it['drug'] ?? '') . ' ' . ($it['strength'] ?? ''))] = true;
    }
    foreach ($pa['prescription_items'] ?? [] as $it) {
        $rx[] = $it + ['confirmed' => isset($rxb[norm_text(($it['drug'] ?? '') . ' ' . ($it['strength'] ?? ''))])];
    }

    return ['doc_type' => $docType, 'laboratory' => $lab, 'collection_date' => $collection, 'results' => $final, 'body' => $body, 'prescription' => $rx];
}

/** Series key: strong (same LOINC code, specimen and property) or a conservative name-based fallback (RN-09). */
function series_key(array $r, array $other, ?string $lab): array
{
    $codeA = $r['loinc_code'] ?? null;
    $codeB = $other['loinc_code'] ?? null;
    $strong = $codeA && $codeA === $codeB && preg_match('/^\d{1,7}-\d$/', $codeA)
        && ($r['loinc_property'] ?? null) && ($r['loinc_property'] ?? null) === ($other['loinc_property'] ?? null)
        && min((float) ($r['loinc_confidence'] ?? 0), (float) ($other['loinc_confidence'] ?? 0)) >= 0.7;
    if ($strong) {
        return ['strong' => true, 'key' => 'L|' . $codeA . '|' . norm_text($r['specimen'] ?? '') . '|' . $r['loinc_property']];
    }
    return ['strong' => false, 'key' => 'N|' . norm_text($r['name_printed'] ?? '') . '|' . norm_text($lab) . '|' . norm_unit($r['unit_printed'] ?? null)];
}

/** @return array<string,mixed>|null series row with decrypted payload */
function find_series(int $userId, string $key): ?array
{
    $s = row('SELECT * FROM series WHERE user_id = ? AND key_hash = ?', [$userId, keyed_hash($userId, $key)]);
    if ($s) {
        $s['payload'] = dec($userId, $s['payload_enc']);
    }
    return $s;
}

function create_series(int $userId, string $key, array $payload): array
{
    q('INSERT INTO series(user_id, key_hash, payload_enc, created_at) VALUES (?,?,?,?)', [$userId, keyed_hash($userId, $key), enc($userId, $payload), now()]);
    return ['id' => (int) db()->lastInsertId(), 'payload' => $payload, 'merged_into' => null];
}

/** Values already loaded in a series (same lab), in the series unit, for the magnitude check. */
function series_history(int $userId, int $seriesId, ?string $lab): array
{
    $vals = [];
    foreach (rows("SELECT payload_enc FROM results WHERE series_id = ? AND status = 'loaded'", [$seriesId]) as $r) {
        $p = dec($userId, $r['payload_enc']);
        if (norm_text($p['laboratory'] ?? '') === norm_text($lab) && ($p['series_value'] ?? null) !== null && ($p['operator'] ?? '') === '') {
            $vals[] = (float) $p['series_value'];
        }
    }
    return $vals;
}

function persist_outcome(int $userId, array $doc, array $o, ?array $pages): void
{
    $docId = (int) $doc['id'];
    $labHash = $o['laboratory'] ? keyed_hash($userId, norm_text($o['laboratory'])) : null;

    db()->beginTransaction();
    try {
        // RF-07: same lab and collection date as a document already processed → duplicate.
        if ($labHash && $o['collection_date']) {
            $dup = row("SELECT id FROM documents WHERE user_id = ? AND id <> ? AND lab_hash = ? AND collected_on = ? AND status = 'done'", [$userId, $docId, $labHash, $o['collection_date']]);
            if ($dup) {
                q("UPDATE documents SET status = 'duplicate', duplicate_of = ?, doc_type = ?, collected_on = ?, lab_hash = ?, processed_at = ? WHERE id = ?",
                    [$dup['id'], $o['doc_type'], $o['collection_date'], $labHash, now(), $docId]);
                db()->commit();
                audit($userId, 'system', 'document.duplicate', 'document:' . $docId);
                return;
            }
        }

        $counts = ['loaded' => 0, 'quarantine' => 0, 'skipped_existing' => 0];
        $versions = ['prompt' => EIXO_PROMPT_VERSION, 'claude' => eixo_config()['claude_model'], 'gemini' => eixo_config()['gemini_model'], 'ucum' => 'eixo-ucum-1', 'loinc' => 'model-proposed'];
        foreach ($o['results'] as $item) {
            $r = $item['r'];
            $r['_collection_date'] = $o['collection_date'];
            $observed = ($r['column'] ?? 'current') === 'previous' ? ($r['result_date'] ?? null) : ($r['result_date'] ?? $o['collection_date']);
            $sk = series_key($r, $item['other'], $o['laboratory']);
            $series = find_series($userId, $sk['key']);
            $ucum = ucum_from_printed($r['unit_printed'] ?? null);
            $seriesValue = $r['value_number'] ?? null;
            $converted = null;
            if ($series && $sk['strong'] && $seriesValue !== null && ($series['payload']['unit_ucum'] ?? null) !== $ucum) {
                $conv = ucum_convert((float) $seriesValue, $ucum, $series['payload']['unit_ucum'] ?? null, $r['loinc_property'] ?? null, $series['payload']['property'] ?? null);
                if ($conv === null) {
                    // Not convertible by rule: the value starts its own series in its own unit.
                    $sk['key'] .= '|u:' . ($ucum ?? norm_unit($r['unit_printed'] ?? null));
                    $series = find_series($userId, $sk['key']);
                } else {
                    $converted = ['value' => $conv, 'unit' => $series['payload']['unit_ucum']];
                    $seriesValue = $conv;
                }
            }
            $history = $series ? series_history($userId, (int) $series['id'], $o['laboratory']) : [];
            $otherQuotes = [(string) ($item['other']['quote'] ?? '')];
            $check = cross_check($r, $pages, $otherQuotes, $history);
            $status = ($item['resolution'] !== 'unresolved' && $check['pass']) ? 'loaded' : 'quarantine';

            if ($status === 'loaded' && !$series) {
                $series = create_series($userId, $sk['key'], [
                    'label' => $r['name_printed'], 'strong' => $sk['strong'], 'loinc' => $sk['strong'] ? $r['loinc_code'] : null,
                    'property' => $r['loinc_property'] ?? null, 'specimen' => $r['specimen'] ?? null,
                    'unit_ucum' => $ucum, 'unit_printed' => $r['unit_printed'] ?? null, 'section' => $r['section'] ?? null,
                ]);
            }
            // Historic values already in the series (same date and value) are not duplicated.
            if ($status === 'loaded' && $series && $observed) {
                foreach (rows("SELECT payload_enc FROM results WHERE series_id = ? AND status = 'loaded' AND observed_on = ?", [$series['id'], $observed]) as $ex) {
                    $ep = dec($userId, $ex['payload_enc']);
                    if (($ep['operator'] ?? '') === ($r['operator'] ?? '') && (($ep['series_value'] ?? null) === null ? norm_text($ep['value_text'] ?? '') === norm_text($r['value_text'] ?? '') : abs((float) $ep['series_value'] - (float) $seriesValue) < 1e-9)) {
                        $counts['skipped_existing']++;
                        continue 2;
                    }
                }
            }
            $payload = [
                'name_printed' => $r['name_printed'] ?? null, 'section' => $r['section'] ?? null,
                'value_printed' => $r['value_printed'] ?? null, 'operator' => $r['operator'] ?? '',
                'value_number' => $r['value_number'] ?? null, 'value_text' => $r['value_text'] ?? null,
                'unit_printed' => $r['unit_printed'] ?? null, 'unit_ucum' => $ucum,
                'converted' => $converted, 'series_value' => $seriesValue,
                'reference_printed' => $r['reference_printed'] ?? null, 'reference_ranges' => $r['reference_ranges'] ?? [],
                'flag_printed' => $r['flag_printed'] ?? null, 'method' => $r['method'] ?? null, 'specimen' => $r['specimen'] ?? null,
                'column' => $r['column'] ?? 'current', 'page' => (int) ($r['page'] ?? 0), 'quote' => $r['quote'] ?? '',
                'loinc' => ['code' => $r['loinc_code'] ?? null, 'property' => $r['loinc_property'] ?? null, 'confidence' => $r['loinc_confidence'] ?? 0, 'other_code' => $item['other']['loinc_code'] ?? null],
                'laboratory' => $o['laboratory'], 'resolution' => $item['resolution'], 'checks' => $check['checks'],
                'alternative' => $item['disputed'] ? ['value_printed' => $item['disputed']['value_printed'] ?? null, 'unit_printed' => $item['disputed']['unit_printed'] ?? null] : null,
                'versions' => $versions,
            ];
            q('INSERT INTO results(user_id, document_id, series_id, status, origin, observed_on, payload_enc, created_at) VALUES (?,?,?,?,?,?,?,?)', [
                $userId, $docId, $status === 'loaded' ? $series['id'] : null, $status, 'extraction', valid_date($observed) ? $observed : null, enc($userId, $payload), now(),
            ]);
            $counts[$status]++;
        }

        if ($o['body'] && $o['body']['metrics']) {
            $device = $o['body']['device'];
            $deviceKey = $device ? norm_text(($device['brand'] ?? '') . ' ' . ($device['model'] ?? '')) : '';
            q('INSERT INTO body_measurements(user_id, kind, measured_on, device_hash, document_id, payload_enc, created_at) VALUES (?,?,?,?,?,?,?)', [
                $userId, 'bia', $o['body']['date'] ?? substr((string) $doc['uploaded_at'], 0, 10), $deviceKey !== '' ? keyed_hash($userId, $deviceKey) : null, $docId,
                enc($userId, ['device' => $device, 'conditions' => null, 'date_from' => $o['body']['date'] ? 'document' : 'upload',
                    'metrics' => array_map(fn ($m) => ['name' => $m['name_printed'], 'value' => $m['value_number'], 'value_printed' => $m['value_printed'], 'unit' => $m['unit_printed'], 'page' => $m['page'], 'quote' => $m['quote']], $o['body']['metrics'])]),
                now(),
            ]);
        }

        $meta = ['laboratory' => $o['laboratory'], 'collection_date' => $o['collection_date'], 'counts' => $counts,
            'body_unconfirmed' => $o['body']['unconfirmed'] ?? [], 'prescription' => $o['prescription'], 'versions' => $versions];
        q("UPDATE documents SET status = 'done', doc_type = ?, collected_on = ?, lab_hash = ?, meta_enc = ?, text_enc = ?, error_code = NULL, processed_at = ? WHERE id = ?", [
            $o['doc_type'], $o['collection_date'], $labHash, enc($userId, $meta), $pages !== null ? enc($userId, implode("\f", $pages)) : null, now(), $docId,
        ]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    audit($userId, 'system', 'document.processed', 'document:' . $docId);
}

/* ── Queue ──────────────────────────────────────────────────────────────── */

function enqueue_document(int $docId): void
{
    q("UPDATE documents SET status = 'queued' WHERE id = ?", [$docId]);
    q("INSERT INTO jobs(document_id, status, run_after, created_at) VALUES (?, 'pending', ?, ?)", [$docId, now(), now()]);
}

/** Processes pending jobs until $seconds elapse. Returns the number of jobs handled. */
function run_queue(int $seconds = 50): int
{
    $lock = fopen(eixo_data_dir() . '/worker.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        return 0;
    }
    $deadline = time() + $seconds;
    $n = 0;
    // Jobs left "running" by a crashed worker go back to the queue.
    q("UPDATE jobs SET status = 'pending' WHERE status = 'running' AND locked_at < ?", [gmdate('Y-m-d\TH:i:s\Z', time() - 900)]);
    while (time() < $deadline) {
        $job = row("SELECT * FROM jobs WHERE status = 'pending' AND run_after <= ? ORDER BY id LIMIT 1", [now()]);
        if (!$job) {
            break;
        }
        q("UPDATE jobs SET status = 'running', locked_at = ?, attempts = attempts + 1 WHERE id = ?", [now(), $job['id']]);
        q("UPDATE documents SET status = 'processing' WHERE id = ?", [$job['document_id']]);
        try {
            process_document((int) $job['document_id']);
            q("UPDATE jobs SET status = 'done' WHERE id = ?", [$job['id']]);
        } catch (Throwable $e) {
            $id = log_error('job ' . $job['id'], $e);
            $attempts = (int) $job['attempts'] + 1;
            if ($attempts >= 4) {
                q("UPDATE jobs SET status = 'failed', last_error = ? WHERE id = ?", ['error ' . $id, $job['id']]);
                q("UPDATE documents SET status = 'failed', error_code = 'processing_error' WHERE id = ?", [$job['document_id']]);
            } else {
                q("UPDATE jobs SET status = 'pending', run_after = ?, last_error = ? WHERE id = ?", [gmdate('Y-m-d\TH:i:s\Z', time() + 60 * 2 ** $attempts), 'error ' . $id, $job['id']]);
                q("UPDATE documents SET status = 'queued' WHERE id = ?", [$job['document_id']]);
            }
        }
        $n++;
    }
    flock($lock, LOCK_UN);
    return $n;
}
