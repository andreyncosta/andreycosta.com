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
        'diag' => isset($args[0]) ? cmd_diag_doc((int) $args[0]) : cmd_diag(),
        'requeue' => cmd_requeue(),
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
  requeue              put failed documents back in the queue

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
    'anthropic_api_key' => '',
    'claude_model' => 'claude-opus-5-5',
    'gemini_api_key' => '',
    'gemini_model' => 'gemini-4-argon',
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
        echo "This sends $n synthetic report(s) to both AI providers (real cost, roughly 2-3 readings each). Re-run with --yes.\n";
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
        $score = score_against_truth($uid, $truth) + ['at' => now(), 'documents' => $n, 'claude' => eixo_config()['claude_model'], 'gemini' => eixo_config()['gemini_model'], 'prompt' => EIXO_PROMPT_VERSION];
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
        'Anthropic key set' => $c['anthropic_api_key'] !== '',
        'Gemini key set' => $c['gemini_api_key'] !== '',
        'pdftotext available' => is_executable((string) $c['pdftotext']),
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
    $c = eixo_config();
    echo "probe anthropic {$c['claude_model']}: ";
    try {
        $client = new Anthropic\Client(apiKey: (string) $c['anthropic_api_key'], baseUrl: $c['anthropic_base_url'] ?: null);
        $m = $client->messages->create(model: (string) $c['claude_model'], maxTokens: 16, messages: [['role' => 'user', 'content' => 'Reply with: ok']]);
        echo 'ok (' . $m->stopReason?->value . ")\n";
    } catch (Throwable $e) {
        echo get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 400) . "\n";
    }
    echo "probe gemini {$c['gemini_model']}: ";
    $ch = curl_init(rtrim((string) $c['gemini_base_url'], '/') . '/interactions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $c['gemini_api_key']],
        CURLOPT_POSTFIELDS => json_encode(['model' => $c['gemini_model'], 'store' => false, 'input' => 'Reply with: ok']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "HTTP $status " . ($raw === false ? $err : mb_substr(preg_replace('/\s+/', ' ', (string) $raw), 0, 400)) . "\n";
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

function cmd_diag_doc(int $docId): void
{
    $d = row('SELECT * FROM documents WHERE id = ?', [$docId]);
    if (!$d) {
        echo "no document $docId\n";
        return;
    }
    $bytes = load_blob((int) $d['user_id'], $d['blob_name']);
    $pages = $d['mime'] === 'application/pdf' ? pdf_pages_text($bytes) : null;
    echo "doc $docId {$d['mime']} " . round(strlen($bytes) / 1024) . ' KB, text layer: ' . ($pages === null ? 'none' : count($pages) . ' page(s)') . "\n";
    $input = ['bytes' => $bytes, 'mime' => $d['mime'], 'pages' => $pages, 'sha256' => $d['sha256']];
    foreach (['A', 'B'] as $reader) {
        $t = microtime(true);
        $r = read_document($reader, $input);
        printf("%s %s/%s %s %.0fs ok=%d %s in=%s out=%s\n  %s\n", $reader, $r['provider'], $r['model'], $r['mode'], microtime(true) - $t,
            $r['ok'] ? 1 : 0, $r['error'] ?? '-', $r['in'] ?? '-', $r['out'] ?? '-', $r['detail'] ?? '');
    }
}
