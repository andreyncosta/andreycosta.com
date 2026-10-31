<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/src/bootstrap.php';

$cmd = $argv[1] ?? 'help';
$args = array_slice($argv, 2);
$flag = fn (string $f) => in_array($f, $args, true);

try {
    match ($cmd) {
        'init' => cmd_init(),
        'setup' => cmd_setup(),
        'migrate' => print('schema version ' . migrate() . "\n"),
        'invite' => cmd_invite($args[0] ?? '', $flag('--send')),
        'work' => print('processed ' . run_queue((int) ($args[0] ?? 50)) . " job(s)\n"),
        'eval' => cmd_eval((int) ($args[0] ?? 3), $flag('--yes')),
        'check' => cmd_check(),
        'backup' => cmd_backup(),
        'purge' => cmd_purge(),
        'stats' => cmd_stats(),
        'diag' => isset($args[0]) ? cmd_diag_doc((int) $args[0], $flag('--text')) : cmd_diag(),
        'models' => cmd_models(),
        'config' => array_map(fn ($pair) => cmd_config($pair[0], $pair[1] ?? ''), array_chunk($args ?: [''], 2)),
        'requeue' => cmd_requeue(),
        'reextract' => cmd_reextract((int) ($args[0] ?? 0), $flag('--yes')),
        default => print(<<<TXT
Eixo CLI
  setup                server bootstrap: environment report, composer install, init, check
  init                 create the private data dir, master key and config template
  migrate              apply database migrations
  invite EMAIL [--send]  create a sign-up / reset link (48 h); --send e-mails it
  work [SECONDS]       process the extraction queue (cron: every minute)
  eval [N] --yes       run N synthetic reports through the real AI readers and record accuracy
  check                integrity, configuration and permissions check
  backup               consistent SQLite backup with SHA-256
  purge                delete access logs and login attempts past retention
  stats                usage and AI token counts (no health data)
  diag [DOC]           recent document/reading error codes and a minimal live call to each AI provider
                       with DOC: re-run both readings on that document and print the provider error text
                       with DOC --text: only report the PDF text layer (no AI call, no cost)
  models               a minimal live call to each reader, to see that the key and the model answer
  config KEY VALUE...  set a model in config.php (never touches keys or secrets)
  requeue              put failed documents back in the queue
  reextract DOC --yes  undo what the extraction wrote for one document and read it again
                       (patient corrections are kept; without --yes it only reports)

TXT),
    };
} catch (Throwable $e) {
    fwrite(STDERR, 'error: ' . $e->getMessage() . "\n");
    exit(1);
}

/** One command for a fresh server (Hostinger cron commands are limited to 255 characters). */
function cmd_setup(): void
{
    echo 'PHP ' . PHP_VERSION . ' · ' . PHP_BINARY . "\n";
    foreach (['sodium', 'pdo_sqlite', 'curl', 'zip', 'fileinfo', 'mbstring'] as $ext) {
        echo '  ext ' . $ext . ': ' . (extension_loaded($ext) ? 'ok' : 'MISSING') . "\n";
    }
    // No shell from PHP on shared hosting: run `composer install` in the cron command itself.
    echo '  vendor: ' . (is_file(__DIR__ . '/vendor/autoload.php') ? 'ok' : 'MISSING (run composer install --no-dev -o)') . "\n";
    echo '  data dir: ' . eixo_data_dir() . "\n";
    cmd_init();
    chmod(eixo_data_dir(), 0700);
    cmd_check();
}

function cmd_init(): void
{
    $dir = eixo_data_dir();
    foreach (['', '/blobs', '/sessions', '/backups'] as $sub) {
        if (!is_dir($dir . $sub)) {
            mkdir($dir . $sub, 0700, true);
        }
    }
    $key = $dir . '/master.key';
    if (!is_file($key)) {
        file_put_contents($key, sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_ORIGINAL) . "\n");
        chmod($key, 0600);
        echo "master key created: $key\n  Keep an offline copy: without it, no document or value can be decrypted.\n";
    }
    $cfg = $dir . '/config.php';
    if (!is_file($cfg)) {
        file_put_contents($cfg, <<<'PHP'
<?php
// Eixo private configuration. Never commit this file.
return [
    'app_url' => 'https://andreycosta.com/eixo/',
    'controller_name' => '',      // who decides on the processing (LGPD controller)
    'controller_email' => '',
    'dpo_name' => '',             // encarregado
    'dpo_email' => '',
    'mail_from' => '',            // sender for `invite --send`
    'openai_key' => '',           // platform.openai.com/api-keys
    'gemini_api_key' => '',       // aistudio.google.com/apikey
    'replicate_key' => '',        // só para leitores escritos como replicate:dono/modelo
    'reader_a' => 'openai:gpt-5-mini',               // reader A, on the page text
    'reader_b' => 'google:gemini-3-flash-preview',   // reader B, another company
    'reader_tiebreak' => 'openai:gpt-5-nano',        // only when A and B disagree
    'max_upload_mb' => 20,
    'max_documents_per_30_days' => 40,
];
PHP);
        chmod($cfg, 0600);
        echo "config template: $cfg\n";
    }
    echo 'schema version ' . migrate() . "\n";
}

function cmd_invite(string $email, bool $send): void
{
    $link = create_invite($email);
    echo "Invite for $email (valid 48 h, single use):\n$link\n";
    if ($send) {
        $from = (string) eixo_config()['mail_from'];
        $ok = $from !== '' && mail($email, 'Seu acesso ao Eixo',
            "Olá,\n\nUse o link abaixo para criar seu acesso ao Eixo. Ele vale por 48 horas e funciona uma vez.\n\n$link\n\nSe você não esperava este e-mail, ignore-o.\n",
            ['From' => $from, 'Content-Type' => 'text/plain; charset=UTF-8']);
        echo $ok ? "e-mail sent\n" : "e-mail NOT sent (set mail_from in config.php or send the link yourself)\n";
    }
}

function cmd_eval(int $n, bool $yes): void
{
    if (!$yes) {
        echo "This sends $n synthetic report(s) to the configured readers (real cost, roughly 2-3 readings each). Re-run with --yes.\n";
        return;
    }
    $email = 'eval+' . time() . '@eixo.invalid';
    q('INSERT INTO users(email, email_verified_at, dek_wrapped, totp_enabled, created_at) VALUES (?,?,?,0,?)', [$email, now(), new_wrapped_dek(), now()]);
    $uid = (int) db()->lastInsertId();
    set_consent($uid, 'store', true);
    set_consent($uid, 'ai', true);
    $truth = [];
    try {
        for ($i = 0; $i < $n; $i++) {
            $rep = synthetic_report(random_int(1, 1000000));
            $blob = store_blob($uid, $rep['pdf']);
            q('INSERT INTO documents(user_id, sha256, mime, size, blob_name, status, uploaded_at) VALUES (?,?,?,?,?,?,?)', [$uid, hash('sha256', $rep['pdf']), 'application/pdf', strlen($rep['pdf']), $blob, 'queued', now()]);
            $docId = (int) db()->lastInsertId();
            process_document($docId);
            $truth[$docId] = $rep['truth'];
            echo '.';
        }
        $score = score_against_truth($uid, $truth) + ['at' => now(), 'documents' => $n, 'reader_a' => reader_model('A'), 'reader_b' => reader_model('B'), 'prompt' => EIXO_PROMPT_VERSION];
        meta_set('eval_last', json_encode($score));
        printf("\n%d values, %d loaded, %d correct, %d WRONG loaded · precision %.2f%% · recall %.2f%%\n",
            $score['values'], $score['loaded'], $score['correct'], $score['wrong_loaded'], $score['precision'] * 100, $score['recall'] * 100);
        echo $score['wrong_loaded'] === 0 && $score['precision'] >= 0.98 ? "meets RNF-10 on this sample\n" : "BELOW the RNF-10 target: do not open to new users before investigating\n";
    } finally {
        delete_account($uid);
    }
}

function cmd_check(): void
{
    $dir = eixo_data_dir();
    $c = eixo_config();
    $checks = [
        'data dir outside the web root' => !str_starts_with(realpath($dir) ?: $dir, realpath(dirname(__DIR__)) ?: dirname(__DIR__)),
        'data dir permissions 0700' => (fileperms($dir) & 0777) === 0700,
        'master key present (0600)' => is_file("$dir/master.key") && (fileperms("$dir/master.key") & 0777) === 0600,
        'config.php present' => is_file("$dir/config.php"),
        'schema up to date' => (int) (meta_get('schema_version') ?? 0) === EIXO_SCHEMA_VERSION,
        'sqlite integrity' => row('PRAGMA integrity_check')['integrity_check'] === 'ok',
        'composer vendor installed' => is_file(__DIR__ . '/vendor/autoload.php'),
        'reader keys set' => reader_keys_missing() === [],
        'pdftotext available' => is_executable((string) $c['pdftotext']),
        'ghostscript available (splits scans)' => is_executable((string) $c['gs']),
        'transcriber configured' => str_contains((string) $c['reader_transcriber'], ':'),
        'controller and DPO named' => $c['controller_name'] !== '' && $c['dpo_name'] !== '',
        'synthetic eval recorded' => meta_get('eval_last') !== null,
    ];
    $fail = 0;
    foreach ($checks as $label => $ok) {
        echo ($ok ? '  ok   ' : '  FAIL ') . $label . "\n";
        $fail += $ok ? 0 : 1;
    }
    $stuck = (int) row("SELECT COUNT(*) n FROM jobs WHERE status = 'pending' AND run_after < ?", [gmdate('Y-m-d\TH:i:s\Z', time() - 600)])['n'];
    echo "  pending jobs older than 10 min: $stuck" . ($stuck ? ' (is the cron running?)' : '') . "\n";
    exit($fail ? 1 : 0);
}

function cmd_backup(): void
{
    $file = eixo_data_dir() . '/backups/eixo-' . gmdate('Ymd-His') . '.sqlite';
    db()->exec('VACUUM INTO ' . db()->quote($file));
    chmod($file, 0600);
    $check = (new PDO('sqlite:' . $file))->query('PRAGMA integrity_check')->fetchColumn();
    file_put_contents($file . '.sha256', hash_file('sha256', $file) . '  ' . basename($file) . "\n");
    echo "$file ($check)\n  Documents live in blobs/ (encrypted); back up that folder and master.key too, separately.\n";
}

function cmd_purge(): void
{
    $cut = gmdate('Y-m-d\TH:i:s\Z', strtotime('-6 months'));
    $a = q('DELETE FROM access_log WHERE at < ?', [$cut])->rowCount();
    $b = q('DELETE FROM login_attempts WHERE at < ?', [gmdate('Y-m-d\TH:i:s\Z', time() - 86400)])->rowCount();
    $c = q('DELETE FROM invites WHERE expires_at < ?', [$cut])->rowCount();
    echo "purged $a access log rows, $b login attempts, $c old invites\n";
}

function cmd_stats(): void
{
    $u = row("SELECT COUNT(*) n FROM users WHERE deleted_at IS NULL AND totp_enabled = 1")['n'];
    $d = row("SELECT COUNT(*) n FROM documents")['n'];
    echo "active users: $u · documents: $d\n";
    foreach (rows("SELECT d.status, COUNT(*) n FROM documents d GROUP BY d.status") as $r) {
        echo "  {$r['status']}: {$r['n']}\n";
    }
    foreach (rows("SELECT provider, model, COUNT(*) n, SUM(ok) ok, SUM(input_tokens) i, SUM(output_tokens) o FROM readings WHERE created_at > ? GROUP BY provider, model", [gmdate('Y-m-d', strtotime('-30 days'))]) as $r) {
        echo "  30d {$r['provider']} {$r['model']}: {$r['n']} readings ({$r['ok']} ok), {$r['i']} in / {$r['o']} out tokens\n";
    }
    $q = row("SELECT SUM(status = 'quarantine') q, COUNT(*) n FROM results WHERE origin = 'extraction'");
    echo '  quarantine share: ' . ($q['n'] ? round($q['q'] / $q['n'] * 100, 1) : 0) . "%\n";
}

/** Error codes only (no health data), plus a one-word live call to each provider showing the raw API error. */
function cmd_diag(): void
{
    foreach (rows("SELECT id, status, error_code, mime, uploaded_at FROM documents ORDER BY id DESC LIMIT 10") as $d) {
        echo "doc {$d['id']} {$d['mime']} {$d['status']} " . ($d['error_code'] ?? '-') . " {$d['uploaded_at']}\n";
        foreach (rows('SELECT reader, provider, model, input_mode, ok, error_code, input_tokens, output_tokens FROM readings WHERE document_id = ? ORDER BY id', [$d['id']]) as $r) {
            echo "  {$r['reader']} {$r['provider']}/{$r['model']} {$r['input_mode']} ok={$r['ok']} " . ($r['error_code'] ?? '-') . " in={$r['input_tokens']} out={$r['output_tokens']}\n";
        }
        foreach (rows('SELECT status, attempts, last_error FROM jobs WHERE document_id = ? ORDER BY id', [$d['id']]) as $j) {
            echo "  job {$j['status']} attempts={$j['attempts']} " . ($j['last_error'] ?? '-') . "\n";
        }
    }
    foreach (['A', 'B', 'C'] as $reader) {
        $model = reader_model($reader);
        $t = microtime(true);
        [$fornecedor, $modelo] = reader_spec($reader);
        $r = model_predict($fornecedor, $modelo, 'Reply with exactly: ok');
        printf("probe %s %s: %.0fs %s %s in=%s out=%s\n", $reader, $model, microtime(true) - $t,
            $r['ok'] ? 'ok' : ($r['error'] ?? 'failed'),
            provider_detail((string) ($r['ok'] ? $r['text'] : ($r['detail'] ?? ''))),
            $r['in'] ?? '-', $r['out'] ?? '-');
    }
}

/**
 * Everything the extraction wrote for one document, undone, so it can be read again. What
 * the patient corrected by hand is never touched, and a series left without any result goes
 * with it, so the chart does not keep an empty line. Without --yes it only says what it would do.
 */
function cmd_reextract(int $docId, bool $yes): void
{
    $doc = row('SELECT id, user_id, status FROM documents WHERE id = ?', [$docId]);
    if (!$doc) {
        echo "no document $docId\n";
        return;
    }
    $count = fn (string $sql) => (int) row($sql, [$docId])['c'];
    $extracted = $count("SELECT COUNT(*) c FROM results WHERE document_id = ? AND origin = 'extraction'");
    $corrections = $count("SELECT COUNT(*) c FROM results WHERE document_id = ? AND origin = 'patient_correction'");
    $readings = $count('SELECT COUNT(*) c FROM readings WHERE document_id = ?');
    if (!$yes) {
        echo "document $docId ({$doc['status']}): would delete $extracted extracted result(s) and $readings reading(s),"
            . " keep $corrections patient correction(s), and queue it again. Re-run with --yes.\n";
        return;
    }
    $touched = array_column(rows('SELECT DISTINCT series_id FROM results WHERE document_id = ? AND series_id IS NOT NULL', [$docId]), 'series_id');
    q("DELETE FROM results WHERE document_id = ? AND origin = 'extraction'", [$docId]);
    q('DELETE FROM readings WHERE document_id = ?', [$docId]);
    forget_group_readings($docId);
    $emptied = 0;
    foreach ($touched as $seriesId) {
        if (!row('SELECT 1 AS present FROM results WHERE series_id = ? LIMIT 1', [$seriesId])) {
            q('DELETE FROM series WHERE id = ?', [$seriesId]);
            $emptied++;
        }
    }
    q("DELETE FROM jobs WHERE document_id = ? AND status IN ('failed','pending','running')", [$docId]);
    q("UPDATE documents SET status = 'queued', error_code = NULL, processed_at = NULL WHERE id = ?", [$docId]);
    enqueue_document($docId);
    audit((int) $doc['user_id'], 'system', 'document.reextract', 'document:' . $docId);
    echo "document $docId: $extracted result(s) and $readings reading(s) removed, $emptied empty series removed,"
        . " $corrections correction(s) kept, queued again\n";
}

function cmd_requeue(): void
{
    $n = 0;
    foreach (rows("SELECT id FROM documents WHERE status = 'failed'") as $d) {
        q("DELETE FROM jobs WHERE document_id = ? AND status IN ('failed','pending')", [$d['id']]);
        enqueue_document((int) $d['id']);
        $n++;
    }
    echo "requeued $n document(s)\n";
}

function cmd_diag_doc(int $docId, bool $textOnly = false): void
{
    $d = row('SELECT * FROM documents WHERE id = ?', [$docId]);
    if (!$d) {
        echo "no document $docId\n";
        return;
    }
    $bytes = load_blob((int) $d['user_id'], $d['blob_name']);
    $pages = $d['mime'] === 'application/pdf' ? pdf_pages_text($bytes) : null;
    echo "doc $docId {$d['mime']} " . round(strlen($bytes) / 1024) . ' KB, text layer: ' . ($pages === null ? 'none' : count($pages) . ' page(s)') . "\n";
    if ($pages !== null) {
        echo '  characters per page: ' . implode(' ', array_map('mb_strlen', $pages)) . "\n";
    }
    if ($textOnly) {
        return;
    }
    $input = ['bytes' => $bytes, 'mime' => $d['mime'], 'pages' => $pages, 'sha256' => $d['sha256']];
    foreach (['A', 'B'] as $reader) {
        $t = microtime(true);
        $r = read_document($reader, $input);
        printf("%s %s/%s %s %.0fs ok=%d %s in=%s out=%s\n  %s\n", $reader, $r['provider'], $r['model'], $r['mode'], microtime(true) - $t,
            $r['ok'] ? 1 : 0, $r['error'] ?? '-', $r['in'] ?? '-', $r['out'] ?? '-', $r['detail'] ?? '');
    }
}

function cmd_models(): void
{
    foreach (reader_keys_missing() as $falta) {
        echo "chave ausente: $falta\n";
    }
    foreach (['A', 'B', 'C'] as $reader) {
        [$fornecedor, $modelo] = reader_spec($reader);
        $t = microtime(true);
        $r = model_predict($fornecedor, $modelo, 'Responda apenas com a palavra ok.');
        printf("%s %s:%s %.0fs %s %s\n", $reader, $fornecedor, $modelo, microtime(true) - $t,
            $r['ok'] ? 'ok' : ($r['error'] ?? 'falhou'),
            mb_substr(provider_detail((string) ($r['ok'] ? $r['text'] : ($r['detail'] ?? ''))), 0, 120));
    }
}

/** Edits only the model/effort lines of config.php, so the server can be tuned from a cron without opening the file. */
function cmd_config(string $key, string $value): void
{
    $allowed = ['reader_a', 'reader_b', 'reader_tiebreak'];
    if (!in_array($key, $allowed, true) || !preg_match('#^(openai|google|replicate):[a-z0-9.\-/]{1,80}$#', $value)) {
        echo 'usage: config ' . implode('|', $allowed) . " VALUE\n";
        return;
    }
    $file = eixo_data_dir() . '/config.php';
    $src = (string) file_get_contents($file);
    $line = "'$key' => '$value',";
    $pattern = '/\'' . preg_quote($key, '/') . '\'\s*=>\s*\'[^\']*\',/';
    $new = preg_match($pattern, $src) ? preg_replace($pattern, $line, $src, 1) : preg_replace('/\];\s*$/', "    $line\n];\n", $src, 1);
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, $new);
    chmod($tmp, 0600);
    $check = include $tmp;
    if (!is_array($check) || ($check[$key] ?? null) !== $value) {
        unlink($tmp);
        echo "not changed: the edited file did not parse\n";
        return;
    }
    rename($tmp, $file);
    echo "$key = $value\n";
}
