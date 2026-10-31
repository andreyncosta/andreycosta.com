<?php
declare(strict_types=1);

/*
 * Eixo core: private data directory, configuration, database, envelope encryption,
 * sessions, CSRF and the audit trail. Nothing here may write health data to logs.
 */

const EIXO_SCHEMA_VERSION = 4;
const EIXO_CONSENT_VERSION = '2026-10-20';
const EIXO_PROMPT_VERSION = 'extract-2026-10-29b';
const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

function eixo_data_dir(): string
{
    static $dir = null;
    if ($dir === null) {
        $dir = getenv('EIXO_DATA_DIR') ?: dirname(__DIR__, 3) . '/eixo-private';
        $dir = rtrim($dir, '/');
    }
    return $dir;
}

/** @return array<string,mixed> */
function eixo_config(): array
{
    static $config = null;
    if ($config === null) {
        $defaults = [
            'app_url' => 'https://andreycosta.com/eixo/',
            'controller_name' => '',
            'controller_email' => '',
            'dpo_name' => '',
            'dpo_email' => '',
            'mail_from' => '',
            // Cada leitor é 'fornecedor:modelo'. A OpenAI e a Google impõem o esquema JSON;
            // a Replicate não impõe em invólucro nenhum, e lá ele viaja no prompt.
            // Medido em 30/10/2026, sobre o laudo sintético, com o esquema real: todos os
            // candidatos acertaram 19 de 19, então preço e prazo de desligamento decidiram.
            // O gpt-5-mini desliga em 11/12/2026 e o gpt-5-nano no mesmo dia; o
            // gemini-3-flash-preview é um preview. Nenhum dos quatro abaixo é depreciado.
            // O luna é o degrau mais barato da OpenAI: US$ 0,10 / 0,50 por milhão.
            'reader_a' => 'openai:gpt-6-luna',
            // O flash-lite 3.1 é o mais barato do Google que não é depreciado, e o único
            // testado que aceita thinkingBudget 0 sem recusar a chamada.
            'reader_b' => 'google:gemini-3.1-flash-lite',
            // O desempate lê só as páginas em disputa, então o volume é pequeno e vale
            // pagar pelo modelo mais capaz: é ele que decide o que entra na série.
            'reader_tiebreak' => 'openai:gpt-6.1-terra',
            // Quem repõe a camada de texto de um laudo digitalizado ou de uma foto. Não extrai
            // nada: só transcreve, e por isso o modelo mais barato que leia página serve.
            'reader_transcriber' => 'openai:gpt-5.6-luna',
            'reader_max_tokens' => 32000,
            'reader_pages_per_call' => 5,
            'reader_timeout' => 900,
            'openai_key' => '',
            'openai_reasoning_effort' => 'low',
            'gemini_api_key' => '',
            // Raciocínio ligado é o que põe estes modelos no laço de repetição.
            'google_thinking_budget' => 0,
            'replicate_key' => '',
            'replicate_base_url' => 'https://api.replicate.com/v1',
            'max_upload_mb' => 20,
            'max_documents_per_30_days' => 40,
            'patient_idle_minutes' => 60,
            'doctor_idle_minutes' => 30,
            'pdftotext' => '/usr/bin/pdftotext',
            // O Ghostscript fatia o PDF digitalizado em grupos de páginas para a transcrição.
            // Sem ele o laudo inteiro vai numa chamada só, que é pior mas funciona.
            'gs' => '/usr/bin/gs',
            'providers' => 'live',
        ];
        $file = eixo_data_dir() . '/config.php';
        $loaded = is_file($file) ? require $file : [];
        $config = array_merge($defaults, is_array($loaded) ? $loaded : []);
        if (getenv('EIXO_PROVIDERS')) {
            $config['providers'] = getenv('EIXO_PROVIDERS');
        }
    }
    return $config;
}

function now(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function today(): string
{
    return gmdate('Y-m-d');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . eixo_data_dir() . '/eixo.sqlite', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }
    return $pdo;
}

/** @param array<int|string,mixed> $params */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** @param array<int|string,mixed> $params @return array<string,mixed>|null */
function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

/** @param array<int|string,mixed> $params @return list<array<string,mixed>> */
function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function meta_get(string $key): ?string
{
    $r = row('SELECT value FROM meta WHERE key = ?', [$key]);
    return $r['value'] ?? null;
}

function meta_set(string $key, string $value): void
{
    q('INSERT INTO meta(key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$key, $value]);
}

function migrate(): int
{
    db()->exec('CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
    $current = (int) (meta_get('schema_version') ?? 0);
    $files = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
    sort($files);
    foreach ($files as $file) {
        $version = (int) basename($file, '.sql');
        if ($version <= $current) {
            continue;
        }
        db()->beginTransaction();
        try {
            $sql = (string) file_get_contents($file);
            // 001 recreates meta; drop the bootstrap copy so the migration owns it.
            if ($version === 1) {
                db()->exec('DROP TABLE IF EXISTS meta');
            }
            db()->exec($sql);
            meta_set('schema_version', (string) $version);
            db()->commit();
            $current = $version;
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
    }
    return $current;
}

function assert_schema(): void
{
    $v = (int) (meta_get('schema_version') ?? 0);
    if ($v !== EIXO_SCHEMA_VERSION) {
        throw new RuntimeException('schema version mismatch: run php eixo/cli.php migrate');
    }
}

/* ── Envelope encryption ─────────────────────────────────────────────────────
 * A master key (private dir, 0600) wraps one random data key per user. Health
 * content is sealed with the user's data key. Deleting the user's wrapped key
 * makes any leftover ciphertext unreadable. The master key sits on the same host,
 * so this protects copies of the database and backups, not a compromised server. */

function master_key(): string
{
    static $key = null;
    if ($key === null) {
        $file = eixo_data_dir() . '/master.key';
        $raw = is_file($file) ? trim((string) file_get_contents($file)) : '';
        $key = $raw === '' ? '' : sodium_base642bin($raw, SODIUM_BASE64_VARIANT_ORIGINAL);
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('master key missing: run php eixo/cli.php init');
        }
    }
    return $key;
}

function seal(string $key, string $plain): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return sodium_bin2base64($nonce . sodium_crypto_secretbox($plain, $nonce, $key), SODIUM_BASE64_VARIANT_ORIGINAL);
}

function unseal(string $key, string $sealed): string
{
    $raw = sodium_base642bin($sealed, SODIUM_BASE64_VARIANT_ORIGINAL);
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
    if ($plain === false) {
        throw new RuntimeException('decryption failed');
    }
    return $plain;
}

function new_wrapped_dek(): string
{
    return seal(master_key(), random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
}

function user_dek(int $userId): string
{
    static $cache = [];
    if (!isset($cache[$userId])) {
        $u = row('SELECT dek_wrapped FROM users WHERE id = ? AND deleted_at IS NULL', [$userId]);
        if (!$u) {
            throw new RuntimeException('unknown user');
        }
        $cache[$userId] = unseal(master_key(), $u['dek_wrapped']);
    }
    return $cache[$userId];
}

/** @param mixed $value */
function enc(int $userId, $value): string
{
    return seal(user_dek($userId), json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

/** @return mixed */
function dec(int $userId, ?string $sealed)
{
    if ($sealed === null || $sealed === '') {
        return null;
    }
    return json_decode(unseal(user_dek($userId), $sealed), true, 512, JSON_THROW_ON_ERROR);
}

/** Keyed hash for lookups on health-derived values (series keys, lab names) without storing them. */
function keyed_hash(int $userId, string $value): string
{
    return bin2hex(sodium_crypto_generichash($value, user_dek($userId), 16));
}

function blob_path(string $name): string
{
    return eixo_data_dir() . '/blobs/' . substr($name, 0, 2) . '/' . $name;
}

function store_blob(int $userId, string $bytes): string
{
    $name = bin2hex(random_bytes(16));
    $path = blob_path($name);
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) {
        throw new RuntimeException('cannot create blob dir');
    }
    file_put_contents($path, seal(user_dek($userId), $bytes), LOCK_EX);
    chmod($path, 0600);
    return $name;
}

function load_blob(int $userId, string $name): string
{
    return unseal(user_dek($userId), (string) file_get_contents(blob_path($name)));
}

function delete_blob(string $name): void
{
    $path = blob_path($name);
    if (is_file($path)) {
        unlink($path);
    }
}

/* ── Audit trail ──────────────────────────────────────────────────────────── */

function audit(int $userId, string $actor, string $action, ?string $object = null, ?int $shareId = null): void
{
    q('INSERT INTO access_log(user_id, actor, share_id, action, object, at) VALUES (?,?,?,?,?,?)',
        [$userId, $actor, $shareId, $action, $object, now()]);
}

/* ── Consent ──────────────────────────────────────────────────────────────── */

const CONSENT_PURPOSES = ['store', 'ai', 'share', 'research'];

/** @return array<string,bool> */
function consents(int $userId): array
{
    $out = array_fill_keys(CONSENT_PURPOSES, false);
    foreach (rows('SELECT purpose, granted FROM consents WHERE user_id = ? ORDER BY id', [$userId]) as $r) {
        $out[$r['purpose']] = (bool) $r['granted'];
    }
    return $out;
}

function set_consent(int $userId, string $purpose, bool $granted): void
{
    if (!in_array($purpose, CONSENT_PURPOSES, true)) {
        throw new InvalidArgumentException('purpose');
    }
    $current = consents($userId)[$purpose];
    $hasRow = row('SELECT 1 FROM consents WHERE user_id = ? AND purpose = ?', [$userId, $purpose]) !== null;
    if ($hasRow && $current === $granted) {
        return;
    }
    q('INSERT INTO consents(user_id, purpose, granted, text_version, at) VALUES (?,?,?,?,?)',
        [$userId, $purpose, $granted ? 1 : 0, EIXO_CONSENT_VERSION, now()]);
    audit($userId, 'patient', $granted ? 'consent.grant' : 'consent.revoke', $purpose);
    if ($purpose === 'share' && !$granted) {
        // RF-03: revoking the sharing consent ends every active share at once.
        q('UPDATE shares SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL', [now(), $userId]);
    }
}

/* ── HTTP helpers ─────────────────────────────────────────────────────────── */

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route = '', array $params = []): string
{
    $base = rtrim(parse_url((string) eixo_config()['app_url'], PHP_URL_PATH) ?: '/eixo/', '/') . '/';
    if ($route !== '') {
        $params = ['r' => $route] + $params;
    }
    return $base . ($params ? '?' . http_build_query($params) : '');
}

function absolute_url(string $route = '', array $params = []): string
{
    $parts = parse_url((string) eixo_config()['app_url']);
    return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'localhost')
        . (isset($parts['port']) ? ':' . $parts['port'] : '') . url($route, $params);
}

function redirect(string $route = '', array $params = []): never
{
    header('Location: ' . url($route, $params), true, 303);
    exit;
}

function security_headers(): void
{
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; frame-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'self'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    header('Cache-Control: no-store');
    if (($_SERVER['HTTPS'] ?? '') === 'on') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $dir = eixo_data_dir() . '/sessions';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    session_save_path($dir);
    session_name('EIXOSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => url(),
        'secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '43200');
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function require_csrf(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Formulário expirado. Volte e tente de novo.');
    }
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

/** Generic error log line: an id and a code, never request bodies or extracted values. */
function log_error(string $code, ?Throwable $e = null): string
{
    $id = bin2hex(random_bytes(4));
    error_log('[eixo ' . $id . '] ' . $code . ($e ? ' ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine() : ''));
    return $id;
}

function valid_date(?string $d): bool
{
    if ($d === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return false;
    }
    [$y, $m, $day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}

function br_date(?string $iso): string
{
    if (!$iso || !valid_date(substr($iso, 0, 10))) {
        return '—';
    }
    [$y, $m, $d] = explode('-', substr($iso, 0, 10));
    return "$d/$m/$y";
}

/**
 * Um número como o laudo brasileiro o imprime, de volta a float. "1.234,5" é mil duzentos e
 * trinta e quatro e meio; "1.234", com três casas depois do ponto e nenhuma vírgula, é mil
 * duzentos e trinta e quatro, que é como o laudo escreve a contagem de leucócitos.
 */
function br_parse_number(?string $raw): ?float
{
    $r = trim((string) $raw);
    if ($r === '' || !preg_match('/^\d+(?:[.,]\d+)*$/', $r)) {
        return null;
    }
    $virgula = strrpos($r, ',');
    $ponto = strrpos($r, '.');
    if ($virgula !== false && $ponto !== false) {
        // O último separador manda: o outro é separador de milhar.
        return $virgula > $ponto
            ? (float) str_replace(['.', ','], ['', '.'], $r)
            : (float) str_replace(',', '', $r);
    }
    if ($virgula !== false) {
        return substr_count($r, ',') === 1 ? (float) str_replace(',', '.', $r) : (float) str_replace(',', '', $r);
    }
    if ($ponto !== false) {
        return preg_match('/^\d{1,3}(\.\d{3})+$/', $r) ? (float) str_replace('.', '', $r) : (float) $r;
    }
    return (float) $r;
}

function br_number(?float $v): string
{
    if ($v === null) {
        return '—';
    }
    $s = rtrim(rtrim(number_format($v, 6, ',', '.'), '0'), ',');
    return $s === '-0' ? '0' : $s;
}
