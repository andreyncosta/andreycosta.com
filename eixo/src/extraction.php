<?php
declare(strict_types=1);

/*
 * Extraction pipeline. The models only transcribe; code compares, checks and decides.
 * Every reader runs on Replicate, on the document's text layer (minimized).
 *
 *   A: replicate_model_a, on the page text
 *   B: replicate_model_b, a different model family, on the same page text
 *   C: replicate_tiebreak_model, only when A and B disagree (tie-break)
 *
 * A value is loaded only when two readings agree and it passes the cross-check
 * (RN-02); everything else goes to quarantine (RN-10). These models read text, so a
 * document with no text layer is not read at all: it fails with no_text_layer.
 */

/* ── Output schema shared by every reader ───────────────────────────────── */

function nul(array $schema): array
{
    return ['anyOf' => [$schema, ['type' => 'null']]];
}

function extraction_schema(): array
{
    $str = ['type' => 'string'];
    // Optional text is "" rather than null: fewer union-typed fields, which smaller models fill more reliably.
    $opt = $str;
    $num = ['type' => 'number'];
    $int = ['type' => 'integer'];
    $range = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['kind', 'condition', 'label', 'low', 'high'],
        'properties' => [
            'kind' => ['type' => 'string', 'enum' => ['interval', 'upper', 'lower', 'category', 'text']],
            'condition' => $opt,
            'label' => $opt,
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
            'section' => $opt,
            'value_printed' => $str,
            'operator' => ['type' => 'string', 'enum' => ['', '<', '<=', '>', '>=']],
            'value_number' => nul($num),
            'value_text' => $opt,
            'unit_printed' => $opt,
            'reference_printed' => $opt,
            'reference_ranges' => ['type' => 'array', 'items' => $range],
            'flag_printed' => $opt,
            'method' => $opt,
            'specimen' => $opt,
            'column' => ['type' => 'string', 'enum' => ['current', 'previous']],
            'result_date' => $opt,
            'page' => $int,
            'quote' => $str,
            'loinc_code' => $opt,
            'loinc_property' => $opt,
            'loinc_confidence' => $num,
        ],
    ];
    $metric = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['name_printed', 'value_printed', 'value_number', 'unit_printed', 'page', 'quote'],
        'properties' => ['name_printed' => $str, 'value_printed' => $str, 'value_number' => nul($num), 'unit_printed' => $opt, 'page' => $int, 'quote' => $str],
    ];
    $rx = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['drug', 'strength', 'form', 'route', 'instructions', 'page', 'quote'],
        'properties' => ['drug' => $str, 'strength' => $opt, 'form' => $opt, 'route' => $opt, 'instructions' => $opt, 'page' => $int, 'quote' => $str],
    ];
    return [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['doc_type', 'laboratory', 'collection_date', 'results', 'body_composition', 'prescription_items'],
        'properties' => [
            'doc_type' => ['type' => 'string', 'enum' => ['lab_report', 'bioimpedance', 'prescription', 'imaging_report', 'other']],
            'laboratory' => $opt,
            'collection_date' => $opt,
            'results' => ['type' => 'array', 'items' => $result],
            'body_composition' => nul([
                'type' => 'object', 'additionalProperties' => false,
                'required' => ['device_brand', 'device_model', 'technology', 'measured_date', 'metrics'],
                'properties' => [
                    'device_brand' => $opt, 'device_model' => $opt, 'technology' => $opt,
                    'measured_date' => $opt, 'metrics' => ['type' => 'array', 'items' => $metric],
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
- Copy, never compute. Do not convert units, do not calculate indices, do not fix values that look wrong, do not fill in anything that is not printed. When something is not printed, use an empty string for text fields and null for numbers.
- One entry in "results" per printed result. Results printed in a "previous results" / "resultados anteriores" column are separate entries with column "previous" and the date printed for that column in result_date. Current results use column "current" and result_date empty unless the report prints a different date for that test.
- value_printed is the result exactly as printed, including any operator or text ("< 0,01", "Não reagente", "5,4"). operator holds only the comparison sign. value_number is the numeric part with a dot as decimal separator and no thousands separator (Brazilian "1.234,5" is 1234.5); null when the result is not numeric. value_text holds non-numeric results.
- unit_printed is the unit as printed. reference_printed is the reference text as printed. reference_ranges structures it: one entry per range or category; condition holds who it applies to as printed (sex, age, cycle phase, trimester) or empty.
- flag_printed: only an abnormality mark printed by the lab (H, L, *, "Alto", bold note). Empty if none. Never add your own.
- section: the report section heading the result sits under (e.g. "Hormônios", "Bioquímica").
- page is the 1-based page number. quote is the exact line of text where the value appears, verbatim, including the value.
- loinc_code: your best LOINC code for the test given name, specimen, unit and method; loinc_property is that code's property (MCnc, SCnc, CCnc, ACnc, NCnc, MFr, NFr, SFr, VFr, Ratio, Mass, Len, Time, Pres, ...). loinc_confidence from 0 to 1. Use empty and 0 when unsure.
- Dates as YYYY-MM-DD. collection_date is the specimen collection date ("coleta"), not the release date.
- body_composition: for bioimpedance printouts, the device and every metric printed (weight, fat mass, % fat, lean mass, skeletal muscle mass, total/intra/extracellular water, phase angle, visceral fat, BMR...). Otherwise null.
- prescription_items: medications or supplements on a prescription. Otherwise an empty array.
- Never output the patient's name, CPF, address, request number or the requesting physician's data, even inside quotes; replace them with "[omitido]".
TXT;
}

/**
 * Pure-PHP fallback when pdftotext is not installed (Hostinger shared hosting).
 * Much cheaper for the readers than sending page images.
 * @return list<string>|null
 */
function pdf_pages_text_php(string $pdfBytes): ?array
{
    if (!class_exists(Smalot\PdfParser\Parser::class)) {
        return null;
    }
    try {
        $pages = [];
        foreach ((new Smalot\PdfParser\Parser())->parseContent($pdfBytes)->getPages() as $page) {
            $pages[] = (string) $page->getText();
        }
    } catch (Throwable $e) {
        log_error('pdfparser', $e);
        return null;
    }
    return trim(implode('', $pages)) === '' ? null : $pages;
}

/* ── Document text layer and minimization ───────────────────────────────── */

/** @return list<string>|null per-page text of a PDF, or null when unavailable */
function pdf_pages_text(string $pdfBytes): ?array
{
    $bin = (string) eixo_config()['pdftotext'];
    // Shared hosting disables shell_exec and friends; proc_open with an argv array needs no shell.
    if (!is_executable($bin) || !function_exists('proc_open')) {
        return pdf_pages_text_php($pdfBytes);
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
    $res = replicate_read($reader, $doc, $extraInstruction);
    if ($res['ok']) {
        $res['payload'] = empty_text_to_null($res['payload']);
    }
    return $res;
}

/** The schema uses "" for optional text (see extraction_schema); the rest of the pipeline expects null. */
function empty_text_to_null(array $data): array
{
    static $optional = ['laboratory', 'collection_date', 'section', 'value_text', 'unit_printed', 'reference_printed', 'flag_printed',
        'method', 'specimen', 'result_date', 'loinc_code', 'loinc_property', 'condition', 'label', 'device_brand', 'device_model',
        'technology', 'measured_date', 'strength', 'form', 'route', 'instructions'];
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            $data[$k] = empty_text_to_null($v);
        } elseif (is_string($k) && $v === '' && in_array($k, $optional, true)) {
            $data[$k] = null;
        }
    }
    return $data;
}

/** Provider error text for the server log: whitespace collapsed, capped; API errors do not echo document content. */
function provider_detail(string $msg): string
{
    return mb_substr((string) preg_replace('/\s+/', ' ', $msg), 0, 600);
}

/* ── Replicate ───────────────────────────────────────────────────────────── */

/** The model behind each reader. C is the tie-break, asked only when A and B disagree. */
function reader_model(string $reader): string
{
    $cfg = eixo_config();
    return (string) match ($reader) {
        'B' => $cfg['replicate_model_b'],
        'C' => $cfg['replicate_tiebreak_model'],
        default => $cfg['replicate_model_a'],
    };
}

/**
 * Everything the model needs in one string: these models take no system prompt and enforce
 * no schema, so the instructions, the schema and the document travel together and the reply
 * is validated here instead of by the provider.
 */
function extraction_prompt(string $text, string $task, ?array $repair): string
{
    $prompt = extraction_instructions()
        . "\n\nReturn one JSON object and nothing else: no explanation, no markdown fence. It must validate against this JSON Schema:\n"
        . json_encode(extraction_schema(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        . "\n\nDocument text layer, page by page:\n\n" . $text . "\n" . $task;
    if ($repair) {
        $prompt .= "\n\nYour previous reply did not fit the schema:\n- " . implode("\n- ", array_slice($repair, 0, 20))
            . "\nSend the corrected JSON object only. Do not invent a value to fill a gap: \"\" for text that is not printed, null for a number that is not printed.";
    }
    return $prompt;
}

/**
 * One reading. Every reader works on the page text, because these models take text only:
 * a document with no text layer fails with no_text_layer instead of being read badly.
 *
 * The pages go in groups, not all at once: a whole report asked for in a single call takes
 * minutes to generate and Replicate interrupts it, while a group of pages comes back in about
 * a minute and can be retried on its own. The groups are put back together here.
 */
function replicate_read(string $reader, array $doc, string $extraInstruction): array
{
    $cfg = eixo_config();
    $model = reader_model($reader);
    $base = ['ok' => false, 'payload' => null, 'provider' => 'replicate', 'model' => $model, 'mode' => 'text_layer', 'in' => null, 'out' => null, 'error' => null];
    if ((string) $cfg['replicate_key'] === '') {
        return ['error' => 'no_api_key'] + $base;
    }
    $pages = $doc['pages'];
    if ($pages === null || mb_strlen(implode('', $pages)) <= 200) {
        return ['error' => 'no_text_layer'] + $base;
    }
    $total = count($pages);
    // O desempate pede só as páginas em disputa; os outros leitores pedem o documento todo.
    $wanted = $doc['only_pages'] ?? null;
    $in = 0;
    $out = 0;
    $parts = [];
    foreach (array_chunk($pages, max(1, (int) $cfg['replicate_pages_per_call']), true) as $index => $group) {
        if ($wanted !== null && !array_intersect(array_map(fn (int $i): int => $i + 1, array_keys($group)), $wanted)) {
            continue;
        }
        $kept = remembered_group($doc, $reader, $model, $index, $extraInstruction);
        if ($kept !== null) {
            $parts[] = $kept;
            continue;
        }
        $call = read_page_group($model, $group, $total, $extraInstruction);
        $in += $call['in'];
        $out += $call['out'];
        $base['in'] = $in ?: null;
        $base['out'] = $out ?: null;
        if (!$call['ok']) {
            return ['error' => $call['error'], 'detail' => $call['detail']] + $base;
        }
        $parts[] = $call['payload'];
        remember_group($doc, $reader, $model, $index, $extraInstruction, $call['payload']);
    }
    return ['ok' => true, 'payload' => merge_page_groups($parts)] + $base;
}

/** Replicate interrompe predições longas e a rede falha: nesses casos vale tentar de novo. */
function transient_error(?string $code): bool
{
    return in_array($code, ['rate_limited', 'connection', 'timeout', 'prediction_failed',
        'api_500', 'api_502', 'api_503', 'api_504'], true);
}

/**
 * A group already read. The cron worker is killed after thirty minutes and a long report
 * does not fit in one run, so each group is kept and the next run carries on from there
 * instead of paying again for what was already read. The rows go away in
 * forget_group_readings(), when the document leaves the queue either way.
 */
function remembered_group(array $doc, string $reader, string $model, int $index, string $extraInstruction): ?array
{
    if (empty($doc['document_id'])) {
        return null;
    }
    $row = row('SELECT payload_enc FROM page_group_readings WHERE document_id = ? AND reader = ? AND group_index = ?
        AND pages_per_call = ? AND model = ? AND prompt_version = ?',
        [$doc['document_id'], $reader, $index, (int) eixo_config()['replicate_pages_per_call'], $model, group_prompt_key($extraInstruction)]);
    return $row ? dec((int) $doc['user_id'], $row['payload_enc']) : null;
}

function remember_group(array $doc, string $reader, string $model, int $index, string $extraInstruction, array $payload): void
{
    if (empty($doc['document_id'])) {
        return;
    }
    q('INSERT OR REPLACE INTO page_group_readings(document_id, reader, group_index, pages_per_call, model, prompt_version, payload_enc, created_at)
        VALUES (?,?,?,?,?,?,?,?)',
        [$doc['document_id'], $reader, $index, (int) eixo_config()['replicate_pages_per_call'], $model,
            group_prompt_key($extraInstruction), enc((int) $doc['user_id'], $payload), now()]);
}

/** What a group was read with: the prompt version, and the tie-break's own instruction on top. */
function group_prompt_key(string $extraInstruction): string
{
    return $extraInstruction === '' ? EIXO_PROMPT_VERSION
        : EIXO_PROMPT_VERSION . '+' . substr(hash('sha256', $extraInstruction), 0, 16);
}

/** Nothing of the document stays behind once it is out of the queue (RNF-07). */
function forget_group_readings(int $docId): void
{
    q('DELETE FROM page_group_readings WHERE document_id = ?', [$docId]);
}

/**
 * One group of pages, with three attempts at most. A reply whose schema did not fit is
 * told exactly what was wrong; a reply that did not decode at all gets a clean retry,
 * since there is nothing useful to say about it and the complaint only crowds the prompt.
 * @param array<int,string> $group page text, still keyed by its zero-based page index
 * @return array{ok:bool,payload:?array,in:int,out:int,error:?string,detail:?string}
 */
function read_page_group(string $model, array $group, int $total, string $extraInstruction): array
{
    $text = '';
    $numbers = [];
    foreach ($group as $i => $p) {
        $numbers[] = $i + 1;
        $text .= "=== Página " . ($i + 1) . " ===\n" . minimize_text($p) . "\n";
    }
    $task = 'Transcribe into the schema everything printed on these pages, which are pages '
        . reset($numbers) . '-' . end($numbers) . ' of a ' . $total . '-page document. Transcribe only what is printed'
        . ' here, and leave a list empty when these pages hold none of it.'
        . ($extraInstruction ? "\n\n" . $extraInstruction : '');
    $in = 0;
    $out = 0;
    $errors = [];
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $call = replicate_predict($model, extraction_prompt($text, $task, $errors ?: null));
        $in += (int) ($call['in'] ?? 0);
        $out += (int) ($call['out'] ?? 0);
        if (!$call['ok']) {
            // Replicate interrompe predições longas na cara; repetir o grupo custa menos do que
            // perder a leitura inteira e refazer os grupos que já tinham dado certo.
            if (transient_error($call['error']) && $attempt < 2) {
                $errors = [];
                continue;
            }
            return ['ok' => false, 'payload' => null, 'in' => $in, 'out' => $out, 'error' => $call['error'], 'detail' => $call['detail']];
        }
        $data = json_from_model_text((string) $call['text']);
        if ($data === null) {
            $errors = [];
            continue;
        }
        $data = coerce_to_schema($data, extraction_schema());
        $errors = schema_errors($data, extraction_schema());
        if (!$errors) {
            return ['ok' => true, 'payload' => $data, 'in' => $in, 'out' => $out, 'error' => null, 'detail' => null];
        }
    }
    return ['ok' => false, 'payload' => null, 'in' => $in, 'out' => $out,
        'error' => $errors ? 'schema_mismatch' : 'no_json',
        'detail' => $errors ? provider_detail(implode('; ', $errors)) : 'no attempt returned a JSON object'];
}

/**
 * Results a model emitted more than once for the same printed line. These models fall into
 * a repetition loop on some pages: the same name and the same quote come back six times, and
 * the values that come with them do not even match. Both readers of the same family loop the
 * same way, so agreement does not catch it and the repeats would load into the series.
 *
 * The quote is the evidence for a value. Where one line yields the same value twice, one of
 * them is kept; where it yields different values, none is: the evidence cannot say which is
 * right, and guessing is not an option here.
 * @return array{0: list<array>, 1: int} the results that stand, and how many were dropped
 */
function drop_looped_results(array $results): array
{
    $byLine = [];
    foreach ($results as $r) {
        $line = ($r['page'] ?? 0) . '|' . ($r['column'] ?? '') . '|' . norm_text($r['name_printed'] ?? '') . '|' . norm_text($r['quote'] ?? '');
        $byLine[$line][] = $r;
    }
    $kept = [];
    $dropped = 0;
    foreach ($byLine as $group) {
        if (count($group) === 1) {
            $kept[] = $group[0];
            continue;
        }
        $first = $group[0];
        $same = true;
        foreach ($group as $r) {
            if (!same_value($first, $r)) {
                $same = false;
                break;
            }
        }
        $dropped += count($group) - ($same ? 1 : 0);
        if ($same) {
            $kept[] = $first;
        }
    }
    return [$kept, $dropped];
}

/**
 * The groups back into one document: the lists are concatenated in page order, and a header
 * field takes the first group that printed it, since later pages repeat it or leave it out.
 * @param list<array> $parts
 */
function merge_page_groups(array $parts): array
{
    $merged = ['doc_type' => 'other', 'laboratory' => '', 'collection_date' => '', 'results' => [],
        'body_composition' => null, 'prescription_items' => []];
    foreach ($parts as $p) {
        if ($merged['doc_type'] === 'other') {
            $merged['doc_type'] = (string) ($p['doc_type'] ?? 'other');
        }
        foreach (['laboratory', 'collection_date'] as $field) {
            if ($merged[$field] === '') {
                $merged[$field] = (string) ($p[$field] ?? '');
            }
        }
        [$results, $looped] = drop_looped_results($p['results'] ?? []);
        if ($looped > 0) {
            error_log("[eixo] $looped repeated result(s) dropped from a page group");
        }
        $merged['results'] = array_merge($merged['results'], $results);
        $merged['prescription_items'] = array_merge($merged['prescription_items'], $p['prescription_items'] ?? []);
        $composition = $p['body_composition'] ?? null;
        if (!is_array($composition)) {
            continue;
        }
        if (is_array($merged['body_composition'])) {
            $merged['body_composition']['metrics'] = array_merge($merged['body_composition']['metrics'] ?? [], $composition['metrics'] ?? []);
        } else {
            $merged['body_composition'] = $composition;
        }
    }
    return $merged;
}

/**
 * The input for one model. Every model on Replicate declares its own input: the ceiling on
 * the reply is max_tokens for one, max_completion_tokens for another, max_output_tokens for
 * a third, and some take no temperature at all. Sending a field a model does not declare
 * fails the call with 422, so the declaration is read and obeyed.
 */
function replicate_input(string $model, string $prompt): array
{
    $ceiling = (int) eixo_config()['replicate_max_tokens'];
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
    $deadline = $started + (float) $cfg['replicate_timeout'];
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

/* ── Reply parsing and schema validation ─────────────────────────────────── */

/**
 * The JSON object out of a model reply. A reply arrives bare, wrapped in prose or inside a
 * markdown fence, so the same reply is read several ways and the first that decodes wins.
 * Nothing is repaired here: a reply that decodes no way at all is refused.
 */
function json_from_model_text(string $text): ?array
{
    foreach (json_candidates($text) as $candidate) {
        $data = json_decode($candidate, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return null;
}

/**
 * The same reply read several ways, cheapest first: bare, inside a fence, from the first
 * brace to the last, and the first run of braces that balances.
 * @return list<string>
 */
function json_candidates(string $text): array
{
    $candidates = [trim($text)];
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $fenced)) {
        $candidates[] = $fenced[1];
    }
    $first = strpos($text, '{');
    $last = strrpos($text, '}');
    if ($first !== false && $last > $first) {
        $candidates[] = substr($text, $first, $last - $first + 1);
    }
    $balanced = balanced_object($text);
    if ($balanced !== null) {
        $candidates[] = $balanced;
    }
    return $candidates;
}

/** The first run of braces that balances, counting quotes and escapes. */
function balanced_object(string $text): ?string
{
    $start = strpos($text, '{');
    if ($start === false) {
        return null;
    }
    $depth = 0;
    $inString = false;
    $escaped = false;
    $len = strlen($text);
    for ($i = $start; $i < $len; $i++) {
        $ch = $text[$i];
        if ($inString) {
            if ($escaped) {
                $escaped = false;
            } elseif ($ch === "\\") {
                $escaped = true;
            } elseif ($ch === '"') {
                $inString = false;
            }
            continue;
        }
        if ($ch === '"') {
            $inString = true;
        } elseif ($ch === '{') {
            $depth++;
        } elseif ($ch === '}' && --$depth === 0) {
            return substr($text, $start, $i - $start + 1);
        }
    }
    return null;
}

/**
 * Narrow repairs to a reply before validation: a numeric string becomes a number, a missing
 * optional text becomes "", a missing list becomes [], an unknown key is dropped. It never
 * fills in a value, so nothing the model did not read can enter the pipeline here.
 */
function coerce_to_schema(mixed $data, array $schema): mixed
{
    if (isset($schema['anyOf'])) {
        if ($data === null) {
            return null;
        }
        foreach ($schema['anyOf'] as $branch) {
            if (($branch['type'] ?? null) !== 'null') {
                return coerce_to_schema($data, $branch);
            }
        }
        return $data;
    }
    $type = $schema['type'] ?? null;
    if ($type === 'object') {
        if (!is_array($data)) {
            return $data;
        }
        $out = [];
        foreach (($schema['properties'] ?? []) as $key => $sub) {
            if (array_key_exists($key, $data)) {
                $out[$key] = coerce_to_schema($data[$key], $sub);
            } elseif (($sub['type'] ?? null) === 'array') {
                $out[$key] = [];
            } elseif (($sub['type'] ?? null) === 'string' && !isset($sub['enum'])) {
                $out[$key] = '';
            }
        }
        return $out;
    }
    if ($type === 'array') {
        return is_array($data) ? array_map(fn ($v) => coerce_to_schema($v, $schema['items'] ?? []), array_values($data)) : $data;
    }
    if ($type === 'string') {
        if ($data === null) {
            return '';
        }
        return is_int($data) || is_float($data) ? (string) $data : $data;
    }
    if ($type === 'number' || $type === 'integer') {
        if (is_string($data) && preg_match('/^-?\d+(\.\d+)?$/', trim($data))) {
            return $type === 'integer' ? (int) trim($data) : (float) trim($data);
        }
        if ($type === 'integer' && is_float($data) && floor($data) === $data) {
            return (int) $data;
        }
    }
    return $data;
}

/**
 * Why the data does not fit the schema, in words the model can act on. Empty means it fits.
 * @return list<string>
 */
function schema_errors(mixed $data, array $schema, string $path = ''): array
{
    $at = $path === '' ? 'the object' : $path;
    if (isset($schema['anyOf'])) {
        foreach ($schema['anyOf'] as $branch) {
            if (!schema_errors($data, $branch, $path)) {
                return [];
            }
        }
        return ["$at does not match any allowed type"];
    }
    $errors = [];
    switch ($schema['type'] ?? null) {
        case 'null':
            return $data === null ? [] : ["$at must be null"];
        case 'string':
            if (!is_string($data)) {
                return ["$at must be a string"];
            }
            if (isset($schema['enum']) && !in_array($data, $schema['enum'], true)) {
                return ["$at must be one of: \"" . implode('", "', $schema['enum']) . '"'];
            }
            return [];
        case 'integer':
            return is_int($data) ? [] : ["$at must be an integer"];
        case 'number':
            return is_int($data) || is_float($data) ? [] : ["$at must be a number"];
        case 'array':
            if (!is_array($data) || ($data !== [] && array_keys($data) !== range(0, count($data) - 1))) {
                return ["$at must be an array"];
            }
            foreach ($data as $i => $item) {
                $errors = array_merge($errors, schema_errors($item, $schema['items'] ?? [], $at . '[' . $i . ']'));
                if (count($errors) > 20) {
                    break;
                }
            }
            return $errors;
        case 'object':
            if (!is_array($data)) {
                return ["$at must be an object"];
            }
            foreach (($schema['required'] ?? []) as $key) {
                if (!array_key_exists($key, $data)) {
                    $errors[] = ($path === '' ? $key : "$path.$key") . ' is missing';
                }
            }
            foreach (($schema['properties'] ?? []) as $key => $sub) {
                if (array_key_exists($key, $data)) {
                    $errors = array_merge($errors, schema_errors($data[$key], $sub, $path === '' ? $key : "$path.$key"));
                }
                if (count($errors) > 20) {
                    break;
                }
            }
            return $errors;
    }
    return [];
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
    if (!empty($res['detail'])) {
        error_log("[eixo] doc $docId reader $reader {$res['error']}: {$res['detail']}");
    }
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
    $input = ['bytes' => $bytes, 'mime' => $doc['mime'], 'pages' => $pages, 'sha256' => $doc['sha256'],
        'document_id' => $docId, 'user_id' => $userId];

    $a = read_document('A', $input);
    store_reading($userId, $docId, 'A', $a);
    $b = read_document('B', $input);
    store_reading($userId, $docId, 'B', $b);
    if (!$a['ok'] || !$b['ok']) {
        foreach ([$a, $b] as $r) {
            if (!$r['ok'] && transient_error($r['error'])) {
                throw new RuntimeException('transient:' . $r['error']);
            }
        }
        q("UPDATE documents SET status = 'failed', error_code = ?, processed_at = ? WHERE id = ?", [$a['error'] ?? $b['error'], now(), $docId]);
        forget_group_readings($docId);
        audit($userId, 'system', 'document.failed', 'document:' . $docId);
        return;
    }

    $outcome = reconcile($userId, $docId, $a['payload'], $b['payload'], $pages, $input);
    persist_outcome($userId, $doc, $outcome, $pages);
    forget_group_readings($docId);
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
        $onlyPages = [];
        foreach ($disputed as $k => [$ra, $rb]) {
            $r = $ra ?? $rb;
            $onlyPages[] = (int) ($r['page'] ?? 0);
            $list[] = '- "' . ($r['name_printed'] ?? '') . '" on page ' . (int) ($r['page'] ?? 0) . ' (' . ($r['column'] ?? 'current') . ')';
        }
        // Só as páginas em disputa: reler um laudo de 45 páginas para resolver dois valores
        // gasta dezenas de minutos de um modelo lento e dá à Replicate muito mais ocasiões
        // de interromper a predição, que foi o que derrubou os dois primeiros desempates.
        $c = read_document('C', ['only_pages' => array_values(array_unique($onlyPages))] + $input,
            "This is a verification reading. Two earlier readings disagreed on these items; transcribe these pages carefully, paying special attention to them:\n" . implode("\n", array_slice($list, 0, 80)));
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
        $versions = ['prompt' => EIXO_PROMPT_VERSION, 'reader_a' => reader_model('A'), 'reader_b' => reader_model('B'), 'tiebreak' => reader_model('C'), 'ucum' => 'eixo-ucum-1', 'loinc' => 'model-proposed'];
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
    // Jobs left "running" by a crashed worker go back to the queue. The cron kills the worker
    // after thirty minutes, and only one worker runs at a time (worker.lock), so a job locked
    // for longer than that is dead and waiting an hour for it only idles the queue.
    q("UPDATE jobs SET status = 'pending' WHERE status = 'running' AND locked_at < ?", [gmdate('Y-m-d\TH:i:s\Z', time() - 2100)]);
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
