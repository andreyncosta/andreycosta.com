<?php
declare(strict_types=1);

/* Read models for the UI and the doctor view, the descriptive summary, export and deletion.
 * $share, when given, limits everything to what that share authorizes (RNF-03). */

/** @return list<array<string,mixed>> */
function documents_for(int $userId, ?array $share = null): array
{
    $out = [];
    foreach (rows('SELECT * FROM documents WHERE user_id = ? ORDER BY COALESCE(collected_on, substr(uploaded_at,1,10)) DESC, id DESC', [$userId]) as $d) {
        if ($share && !in_share_scope($share, $d['collected_on'] ?? substr($d['uploaded_at'], 0, 10))) {
            continue;
        }
        $d['meta'] = dec($userId, $d['meta_enc']);
        $out[] = $d;
    }
    return $out;
}

/** @return array<string,mixed>|null */
function document_for(int $userId, int $docId, ?array $share = null): ?array
{
    $d = row('SELECT * FROM documents WHERE id = ? AND user_id = ?', [$docId, $userId]);
    if (!$d || ($share && !in_share_scope($share, $d['collected_on'] ?? substr($d['uploaded_at'], 0, 10)))) {
        return null;
    }
    $d['meta'] = dec($userId, $d['meta_enc']);
    return $d;
}

/** @return list<array<string,mixed>> results of one document (loaded, quarantined and corrected) */
function document_results(int $userId, int $docId): array
{
    $out = [];
    foreach (rows('SELECT * FROM results WHERE user_id = ? AND document_id = ? ORDER BY id', [$userId, $docId]) as $r) {
        $r['p'] = dec($userId, $r['payload_enc']);
        $out[] = $r;
    }
    return $out;
}

/** Root series id after patient merges. */
function series_root(int $seriesId): int
{
    for ($i = 0; $i < 20; $i++) {
        $s = row('SELECT merged_into FROM series WHERE id = ?', [$seriesId]);
        if (!$s || !$s['merged_into']) {
            break;
        }
        $seriesId = (int) $s['merged_into'];
    }
    return $seriesId;
}

/**
 * All loaded series with their points, grouped by root series.
 * @return list<array<string,mixed>>
 */
function series_for(int $userId, ?array $share = null): array
{
    $series = [];
    foreach (rows('SELECT * FROM series WHERE user_id = ?', [$userId]) as $s) {
        $s['payload'] = dec($userId, $s['payload_enc']);
        $series[(int) $s['id']] = $s;
    }
    $groups = [];
    foreach (rows("SELECT * FROM results WHERE user_id = ? AND status = 'loaded' AND series_id IS NOT NULL ORDER BY observed_on, id", [$userId]) as $r) {
        if ($share && !in_share_scope($share, $r['observed_on'])) {
            continue;
        }
        $root = series_root((int) $r['series_id']);
        if (!isset($series[$root])) {
            continue;
        }
        $p = dec($userId, $r['payload_enc']);
        $groups[$root] ??= $series[$root] + ['points' => [], 'members' => []];
        $groups[$root]['members'][(int) $r['series_id']] = true;
        $groups[$root]['points'][] = [
            'id' => (int) $r['id'], 'date' => $r['observed_on'], 'document_id' => $r['document_id'] ? (int) $r['document_id'] : null,
            'value' => $p['series_value'], 'operator' => $p['operator'] ?? '', 'value_printed' => $p['value_printed'], 'value_text' => $p['value_text'] ?? null,
            'unit' => $p['unit_printed'], 'converted' => $p['converted'] ?? null, 'ranges' => $p['reference_ranges'] ?? [], 'reference_printed' => $p['reference_printed'] ?? null,
            'flag' => $p['flag_printed'] ?? null, 'lab' => $p['laboratory'] ?? null, 'method' => $p['method'] ?? null,
            'page' => $p['page'] ?? null, 'quote' => $p['quote'] ?? '', 'origin' => $r['origin'], 'section' => $p['section'] ?? null,
        ];
    }
    foreach ($groups as &$g) {
        // A change of lab or method between consecutive points is a discontinuity (RF-18).
        $prev = null;
        foreach ($g['points'] as &$pt) {
            $pt['break'] = $prev !== null && (norm_text($prev['lab']) !== norm_text($pt['lab']) || norm_text($prev['method']) !== norm_text($pt['method']));
            $prev = $pt;
        }
        unset($pt);
        $g['last'] = end($g['points']) ?: null;
    }
    unset($g);
    $list = array_values($groups);
    usort($list, fn ($a, $b) => [$b['pinned'], $b['last']['date'] ?? ''] <=> [$a['pinned'], $a['last']['date'] ?? '']);
    return $list;
}

/** @return list<array<string,mixed>> */
function quarantine_for(int $userId, ?array $share = null): array
{
    $out = [];
    foreach (rows("SELECT * FROM results WHERE user_id = ? AND status = 'quarantine' ORDER BY id DESC", [$userId]) as $r) {
        $d = row('SELECT collected_on, uploaded_at FROM documents WHERE id = ?', [$r['document_id']]);
        if ($share && !in_share_scope($share, $r['observed_on'] ?? ($d['collected_on'] ?? null))) {
            continue;
        }
        $r['p'] = dec($userId, $r['payload_enc']);
        $out[] = $r;
    }
    return $out;
}

/** @return list<array<string,mixed>> medications with their events */
function medications_for(int $userId, ?array $share = null): array
{
    $out = [];
    foreach (rows('SELECT * FROM medications WHERE user_id = ? ORDER BY id', [$userId]) as $m) {
        $m['p'] = dec($userId, $m['payload_enc']);
        $m['events'] = [];
        foreach (rows('SELECT * FROM dose_events WHERE medication_id = ? ORDER BY event_on, id', [$m['id']]) as $e) {
            if ($share && !in_share_scope($share, $e['event_on'])) {
                continue;
            }
            $e['p'] = dec($userId, $e['payload_enc']);
            $m['events'][] = $e;
        }
        if ($share && !$m['events']) {
            continue;
        }
        $out[] = $m;
    }
    return $out;
}

/** Dose events flattened for chart markers. */
function dose_markers(array $meds): array
{
    $labels = ['start' => 'início', 'adjust' => 'ajuste', 'pause' => 'pausa', 'resume' => 'retomada', 'stop' => 'suspensão'];
    $out = [];
    foreach ($meds as $m) {
        foreach ($m['events'] as $e) {
            $out[] = ['date' => $e['event_on'], 'label' => ($m['p']['name'] ?? '') . ' · ' . $labels[$e['type']] . (($e['p']['dose'] ?? '') !== '' ? ' ' . $e['p']['dose'] : '')];
        }
    }
    usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']));
    return $out;
}

/**
 * Body measurements grouped into series; bioimpedance series break when the device changes (RN-04).
 * @return array{bia:list<array>,anthro:list<array>,metrics:array<string,list<array>>}
 */
function body_for(int $userId, ?array $share = null): array
{
    $bia = [];
    $anthro = [];
    foreach (rows('SELECT * FROM body_measurements WHERE user_id = ? ORDER BY measured_on, id', [$userId]) as $b) {
        if ($share && !in_share_scope($share, $b['measured_on'])) {
            continue;
        }
        $b['p'] = dec($userId, $b['payload_enc']);
        if ($b['kind'] === 'bia') {
            $bia[] = $b;
        } else {
            $anthro[] = $b;
        }
    }
    // Metric series per device (and per metric name/unit).
    $metrics = [];
    $prevByMetric = [];
    foreach ($bia as $b) {
        $device = $b['p']['device'] ?? null;
        $deviceLabel = $device ? trim(($device['brand'] ?? '') . ' ' . ($device['model'] ?? '')) : 'aparelho não identificado';
        foreach ($b['p']['metrics'] ?? [] as $m) {
            if (($m['value'] ?? null) === null) {
                continue;
            }
            $name = norm_text($m['name']) . '|' . norm_unit($m['unit'] ?? null);
            $key = $name . '|' . ($b['device_hash'] ?? 'none');
            $prevDevice = $prevByMetric[$name] ?? null;
            $metrics[$key] ??= ['label' => $m['name'], 'unit' => $m['unit'] ?? '', 'device' => $deviceLabel, 'points' => [],
                'warning' => $prevDevice !== null && $prevDevice !== ($b['device_hash'] ?? 'none') ? 'Aparelho diferente da série anterior: a série recomeça aqui e não se compara com a anterior.' : null];
            $metrics[$key]['points'][] = ['date' => $b['measured_on'], 'value' => (float) $m['value'], 'conditions' => $b['p']['conditions'] ?? null, 'document_id' => $b['document_id']];
            $prevByMetric[$name] = $b['device_hash'] ?? 'none';
        }
    }
    // Condition changes within a series (RF-26).
    foreach ($metrics as &$s) {
        $prev = null;
        foreach ($s['points'] as &$pt) {
            $c = $pt['conditions'];
            $pt['condition_change'] = $prev !== null && conditions_signature($c) !== conditions_signature($prev);
            $prev = $c;
        }
        unset($pt);
    }
    unset($s);
    return ['bia' => $bia, 'anthro' => $anthro, 'metrics' => $metrics];
}

function conditions_signature(?array $c): string
{
    if (!$c) {
        return 'unknown';
    }
    $hour = isset($c['time']) && preg_match('/^(\d{2})/', (string) $c['time'], $m) ? (int) $m[1] : null;
    $period = $hour === null ? '?' : ($hour < 12 ? 'manhã' : ($hour < 18 ? 'tarde' : 'noite'));
    return implode('|', [$period, $c['fasting'] ?? '?', $c['exercise'] ?? '?', $c['hydration'] ?? '?']);
}

/* ── Descriptive pre-visit summary (RF-33, RN-03) ──────────────────────── */

/**
 * Builds the summary from code; every number comes from stored values with its source.
 * No interpretation: values are shown beside the lab's own range and printed flags only.
 */
function summary_for(int $userId, ?array $share, string $since): array
{
    $series = series_for($userId, $share);
    $new = [];
    foreach ($series as $s) {
        $recent = array_values(array_filter($s['points'], fn ($p) => ($p['date'] ?? '') >= $since));
        if (!$recent) {
            continue;
        }
        $before = array_values(array_filter($s['points'], fn ($p) => ($p['date'] ?? '') < $since));
        $new[] = ['label' => $s['payload']['label'], 'section' => $s['payload']['section'] ?? null, 'recent' => $recent, 'previous' => $before ? end($before) : null, 'series_id' => (int) $s['id']];
    }
    usort($new, fn ($a, $b) => strcmp((string) $a['section'] . $a['label'], (string) $b['section'] . $b['label']));
    $meds = [];
    foreach (medications_for($userId, $share) as $m) {
        $ev = array_values(array_filter($m['events'], fn ($e) => $e['event_on'] >= $since));
        $current = end($m['events']) ?: null;
        if ($ev || ($current && $current['type'] !== 'stop')) {
            $meds[] = ['name' => $m['p']['name'] ?? '', 'kind' => $m['kind'], 'p' => $m['p'], 'events' => $ev, 'current' => $current];
        }
    }
    $body = body_for($userId, $share);
    $bodyChanges = [];
    foreach ($body['metrics'] as $m) {
        $pts = $m['points'];
        $recent = array_values(array_filter($pts, fn ($p) => $p['date'] >= $since));
        if (!$recent) {
            continue;
        }
        $first = $pts[0];
        $bodyChanges[] = ['label' => $m['label'], 'unit' => $m['unit'], 'device' => $m['device'], 'first' => $first, 'last' => end($pts), 'warning' => $m['warning'],
            'condition_change' => (bool) array_filter($pts, fn ($p) => $p['condition_change'])];
    }
    $anthro = array_values(array_filter($body['anthro'], fn ($a) => $a['measured_on'] >= $since));
    return ['since' => $since, 'results' => $new, 'medications' => $meds, 'body' => $bodyChanges, 'anthro' => $anthro, 'quarantine' => quarantine_for($userId, $share)];
}

/* ── Export (RF-04) and deletion ───────────────────────────────────────── */

/** FHIR R4-inspired bundle of everything the patient has. */
function fhir_bundle(int $userId): array
{
    $u = row('SELECT * FROM users WHERE id = ?', [$userId]);
    $profile = dec($userId, $u['profile_enc']) ?? [];
    $entries = [['resource' => ['resourceType' => 'Patient', 'id' => 'p' . $userId, 'telecom' => [['system' => 'email', 'value' => $u['email']]],
        'gender' => $profile['sex'] ?? null, 'birthDate' => $profile['birth_date'] ?? null, 'name' => [['text' => $profile['name'] ?? null]]]]];
    foreach (documents_for($userId) as $d) {
        $entries[] = ['resource' => ['resourceType' => 'DocumentReference', 'id' => 'd' . $d['id'], 'status' => 'current', 'date' => $d['uploaded_at'],
            'type' => ['text' => $d['doc_type']], 'content' => [['attachment' => ['contentType' => $d['mime'], 'url' => 'documents/' . $d['id'] . doc_ext($d['mime']), 'hash' => $d['sha256']]]],
            'context' => ['period' => ['start' => $d['collected_on']]]]];
    }
    foreach (rows('SELECT * FROM results WHERE user_id = ?', [$userId]) as $r) {
        $p = dec($userId, $r['payload_enc']);
        $obs = ['resourceType' => 'Observation', 'id' => 'o' . $r['id'],
            'status' => $r['status'] === 'loaded' ? 'final' : ($r['status'] === 'corrected' ? 'entered-in-error' : 'preliminary'),
            'code' => ['text' => $p['name_printed'], 'coding' => $p['loinc']['code'] ? [['system' => 'http://loinc.org', 'code' => $p['loinc']['code']]] : []],
            'subject' => ['reference' => 'Patient/p' . $userId], 'effectiveDateTime' => $r['observed_on'],
            'derivedFrom' => $r['document_id'] ? [['reference' => 'DocumentReference/d' . $r['document_id']]] : [],
            'note' => [['text' => 'Como impresso: ' . printed_with_unit($p['value_printed'] ?? '', $p['unit_printed'] ?? '') . ' · página ' . ($p['page'] ?? '?') . ' · origem ' . $r['origin'] . ' · estado ' . $r['status']]],
            'referenceRange' => [['text' => $p['reference_printed'] ?? null]],
        ];
        if (($p['value_number'] ?? null) !== null) {
            $obs['valueQuantity'] = ['value' => $p['value_number'], 'unit' => $p['unit_printed'], 'system' => 'http://unitsofmeasure.org', 'code' => $p['unit_ucum'],
                'comparator' => ($p['operator'] ?? '') ?: null];
        } else {
            $obs['valueString'] = $p['value_text'] ?? $p['value_printed'];
        }
        $entries[] = ['resource' => $obs];
    }
    foreach (medications_for($userId) as $m) {
        foreach ($m['events'] ?: [null] as $e) {
            $entries[] = ['resource' => ['resourceType' => 'MedicationStatement', 'id' => 'm' . $m['id'] . ($e ? '-' . $e['id'] : ''),
                'status' => $e && $e['type'] === 'stop' ? 'stopped' : ($e && $e['type'] === 'pause' ? 'on-hold' : 'active'),
                'medicationCodeableConcept' => ['text' => trim(($m['p']['name'] ?? '') . ' ' . ($m['p']['strength'] ?? ''))],
                'effectiveDateTime' => $e['event_on'] ?? null, 'dosage' => [['text' => $e['p']['dose'] ?? ($m['p']['dosage'] ?? null)]],
                'note' => [['text' => 'tipo: ' . $m['kind'] . ($e ? ' · evento: ' . $e['type'] : '')]]]];
        }
    }
    foreach (body_for($userId)['bia'] as $b) {
        foreach ($b['p']['metrics'] ?? [] as $i => $mt) {
            $entries[] = ['resource' => ['resourceType' => 'Observation', 'id' => 'b' . $b['id'] . '-' . $i, 'status' => 'final', 'code' => ['text' => $mt['name']],
                'effectiveDateTime' => $b['measured_on'], 'valueQuantity' => ['value' => $mt['value'], 'unit' => $mt['unit']],
                'device' => ['display' => trim(($b['p']['device']['brand'] ?? '') . ' ' . ($b['p']['device']['model'] ?? ''))]]];
        }
    }
    foreach (rows('SELECT * FROM consents WHERE user_id = ? ORDER BY id', [$userId]) as $c) {
        $entries[] = ['resource' => ['resourceType' => 'Consent', 'id' => 'c' . $c['id'], 'status' => $c['granted'] ? 'active' : 'inactive',
            'dateTime' => $c['at'], 'category' => [['text' => $c['purpose']]], 'policyRule' => ['text' => 'versão ' . $c['text_version']]]];
    }
    return ['resourceType' => 'Bundle', 'type' => 'collection', 'timestamp' => now(), 'entry' => $entries];
}

function doc_ext(string $mime): string
{
    return ['application/pdf' => '.pdf', 'image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'][$mime] ?? '.bin';
}

/** Builds the export zip in a temp file; caller streams and deletes it. */
function export_zip(int $userId): string
{
    $file = tempnam(sys_get_temp_dir(), 'eixo-export');
    $zip = new ZipArchive();
    $zip->open($file, ZipArchive::OVERWRITE);
    $zip->addFromString('eixo-fhir-bundle.json', json_encode(fhir_bundle($userId), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    foreach (rows('SELECT id, mime, blob_name FROM documents WHERE user_id = ?', [$userId]) as $d) {
        $zip->addFromString('documents/' . $d['id'] . doc_ext($d['mime']), load_blob($userId, $d['blob_name']));
    }
    $log = [];
    foreach (rows('SELECT actor, share_id, action, object, at FROM access_log WHERE user_id = ? ORDER BY id', [$userId]) as $a) {
        $log[] = $a;
    }
    $zip->addFromString('trilha-de-acesso.json', json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $zip->close();
    audit($userId, 'patient', 'account.export');
    return $file;
}

/**
 * Deletes all health data and documents now. The user row keeps no email (hash only) so the
 * access log (no health data) can be kept for the legal minimum; the data key is destroyed.
 */
function delete_account(int $userId): void
{
    $blobs = array_column(rows('SELECT blob_name FROM documents WHERE user_id = ?', [$userId]), 'blob_name');
    audit($userId, 'patient', 'account.delete');
    db()->beginTransaction();
    try {
        foreach (['results', 'series', 'dose_events', 'medications', 'body_measurements', 'shares', 'consents'] as $t) {
            q("DELETE FROM $t WHERE user_id = ?", [$userId]);
        }
        q('DELETE FROM jobs WHERE document_id IN (SELECT id FROM documents WHERE user_id = ?)', [$userId]);
        q('DELETE FROM readings WHERE document_id IN (SELECT id FROM documents WHERE user_id = ?)', [$userId]);
        q('DELETE FROM documents WHERE user_id = ?', [$userId]);
        q("UPDATE users SET email = 'deleted:' || id || ':' || ?, password_hash = NULL, totp_secret_enc = NULL, totp_enabled = 0, recovery_codes_json = '[]', dek_wrapped = '', profile_enc = NULL, deleted_at = ? WHERE id = ?",
            [bin2hex(random_bytes(8)), now(), $userId]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    foreach ($blobs as $b) {
        delete_blob($b);
    }
}
