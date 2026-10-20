<?php
declare(strict_types=1);

/* Accounts by invite, password + mandatory TOTP, recovery codes, rate limiting,
 * patient and doctor sessions, and share links with one-time codes. */

const INVITE_HOURS = 48;
const LOGIN_WINDOW_MINUTES = 15;
const LOGIN_MAX_ATTEMPTS = 5;
const SHARE_CODE_MINUTES = 30;

function token(): string
{
    return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
}

function token_hash(string $token): string
{
    return hash('sha256', $token);
}

function normalize_email(string $email): string
{
    return mb_strtolower(trim($email));
}

function hash_password(string $password): string
{
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    return password_hash($password, $algo);
}

/** Hash checked when the e-mail is unknown, so both paths cost the same. */
function dummy_password_hash(): string
{
    static $h = null;
    return $h ??= hash_password(random_bytes(16));
}

function password_problem(string $password): ?string
{
    if (mb_strlen($password) < 12) {
        return 'Use ao menos 12 caracteres.';
    }
    if (preg_match('/^(.)\1+$/u', $password)) {
        return 'Escolha uma senha menos previsível.';
    }
    return null;
}

/* ── TOTP (RFC 6238, SHA-1, 6 digits, 30 s) ─────────────────────────────── */

function base32_encode(string $bin): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32))) as $c) {
        $bits .= str_pad(decbin(strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_code(string $secretB32, int $step): string
{
    $hmac = hash_hmac('sha1', pack('J', $step), base32_decode($secretB32), true);
    $offset = ord($hmac[19]) & 0x0f;
    $bin = ((ord($hmac[$offset]) & 0x7f) << 24) | (ord($hmac[$offset + 1]) << 16) | (ord($hmac[$offset + 2]) << 8) | ord($hmac[$offset + 3]);
    return str_pad((string) ($bin % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Returns the matched time step, or null. Accepts ±1 step of clock drift; never a step already used. */
function totp_verify(string $secretB32, string $code, int $lastStep, ?int $now = null): ?int
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) {
        return null;
    }
    $step = intdiv($now ?? time(), 30);
    foreach ([0, -1, 1] as $d) {
        $s = $step + $d;
        if ($s > $lastStep && hash_equals(totp_code($secretB32, $s), $code)) {
            return $s;
        }
    }
    return null;
}

function totp_uri(string $secretB32, string $email): string
{
    return 'otpauth://totp/' . rawurlencode('Eixo:' . $email) . '?secret=' . $secretB32 . '&issuer=Eixo&digits=6&period=30';
}

/** @return array{0:list<string>,1:string} plain codes and their JSON of hashes */
function new_recovery_codes(): array
{
    $plain = [];
    for ($i = 0; $i < 10; $i++) {
        $plain[] = implode('-', str_split(strtolower(base32_encode(random_bytes(5))), 4));
    }
    return [$plain, json_encode(array_map(fn ($c) => password_hash($c, PASSWORD_DEFAULT), $plain))];
}

function use_recovery_code(int $userId, string $code): bool
{
    $u = row('SELECT recovery_codes_json FROM users WHERE id = ?', [$userId]);
    $hashes = json_decode($u['recovery_codes_json'] ?? '[]', true) ?: [];
    $code = strtolower(trim($code));
    foreach ($hashes as $i => $hash) {
        if (password_verify($code, $hash)) {
            unset($hashes[$i]);
            q('UPDATE users SET recovery_codes_json = ? WHERE id = ?', [json_encode(array_values($hashes)), $userId]);
            audit($userId, 'patient', 'auth.recovery_code_used');
            return true;
        }
    }
    return false;
}

/* ── Rate limiting ─────────────────────────────────────────────────────── */

function rate_key(string $kind, string $value): string
{
    return hash('sha256', $kind . '|' . $value);
}

function too_many_attempts(string ...$keys): bool
{
    $since = gmdate('Y-m-d\TH:i:s\Z', time() - LOGIN_WINDOW_MINUTES * 60);
    foreach ($keys as $k) {
        $n = (int) row('SELECT COUNT(*) n FROM login_attempts WHERE key_hash = ? AND at > ?', [$k, $since])['n'];
        if ($n >= LOGIN_MAX_ATTEMPTS) {
            return true;
        }
    }
    return false;
}

function record_attempt(string ...$keys): void
{
    foreach ($keys as $k) {
        q('INSERT INTO login_attempts(key_hash, at) VALUES (?, ?)', [$k, now()]);
    }
}

function clear_attempts(string ...$keys): void
{
    foreach ($keys as $k) {
        q('DELETE FROM login_attempts WHERE key_hash = ?', [$k]);
    }
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

/* ── Invites and accounts ────────────────────────────────────────────────── */

/** Creates (or re-issues) a sign-up or password-reset invite; returns the link. Admin-only, via CLI. */
function create_invite(string $email): string
{
    $email = normalize_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('invalid email');
    }
    $t = token();
    q('UPDATE invites SET used_at = ? WHERE email = ? AND used_at IS NULL', [now(), $email]);
    q('INSERT INTO invites(email, token_hash, expires_at, created_at) VALUES (?,?,?,?)',
        [$email, token_hash($t), gmdate('Y-m-d\TH:i:s\Z', time() + INVITE_HOURS * 3600), now()]);
    return absolute_url('invite', ['t' => $t]);
}

/** @return array<string,mixed>|null */
function find_invite(string $t): ?array
{
    return row('SELECT * FROM invites WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?', [token_hash($t), now()]);
}

function create_or_reset_user(string $email, string $password, string $totpSecret): int
{
    $email = normalize_email($email);
    $existing = row('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
    if ($existing) {
        $id = (int) $existing['id'];
        q('UPDATE users SET password_hash = ?, totp_secret_enc = ?, totp_enabled = 0, totp_last_step = 0, email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?',
            [hash_password($password), seal(master_key(), $totpSecret), now(), $id]);
        audit($id, 'patient', 'auth.reset');
    } else {
        q('INSERT INTO users(email, email_verified_at, password_hash, totp_secret_enc, dek_wrapped, created_at) VALUES (?,?,?,?,?,?)',
            [$email, now(), hash_password($password), seal(master_key(), $totpSecret), new_wrapped_dek(), now()]);
        $id = (int) db()->lastInsertId();
        audit($id, 'patient', 'account.create');
    }
    return $id;
}

function user_totp_secret(array $user): string
{
    return unseal(master_key(), (string) $user['totp_secret_enc']);
}

/* ── Patient session ─────────────────────────────────────────────────────── */

function login_patient(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION = ['uid' => $userId, 'started' => time(), 'seen' => time(), 'csrf' => bin2hex(random_bytes(32))];
    audit($userId, 'patient', 'auth.login');
}

/** @return array<string,mixed>|null */
function current_patient(): ?array
{
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) {
        return null;
    }
    $idle = (int) eixo_config()['patient_idle_minutes'] * 60;
    if (time() - (int) ($_SESSION['seen'] ?? 0) > $idle || time() - (int) ($_SESSION['started'] ?? 0) > 43200) {
        logout();
        return null;
    }
    $_SESSION['seen'] = time();
    $u = row('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL AND totp_enabled = 1', [$uid]);
    if (!$u) {
        logout();
    }
    return $u;
}

function logout(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/* ── Shares (doctor access) ──────────────────────────────────────────────── */

/** @return array{token:string,code:string,id:int} */
function create_share(int $userId, string $recipient, string $scope, ?string $from, ?string $to, int $days): array
{
    if (!consents($userId)['share']) {
        throw new RuntimeException('share consent missing');
    }
    $t = token();
    $code = new_share_code();
    q('INSERT INTO shares(user_id, token_hash, code_hash, code_expires_at, scope, scope_from, scope_to, payload_enc, expires_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)', [
        $userId, token_hash($t), password_hash($code, PASSWORD_DEFAULT), gmdate('Y-m-d\TH:i:s\Z', time() + SHARE_CODE_MINUTES * 60),
        $scope, $scope === 'period' ? $from : null, $scope === 'period' ? $to : null,
        enc($userId, ['recipient' => $recipient]), gmdate('Y-m-d\TH:i:s\Z', time() + max(1, min(365, $days)) * 86400), now(),
    ]);
    $id = (int) db()->lastInsertId();
    audit($userId, 'patient', 'share.create', 'share:' . $id, $id);
    return ['token' => $t, 'code' => $code, 'id' => $id];
}

function new_share_code(): string
{
    return str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
}

function reissue_share_code(int $userId, int $shareId): ?string
{
    $s = row('SELECT id FROM shares WHERE id = ? AND user_id = ? AND revoked_at IS NULL AND expires_at > ?', [$shareId, $userId, now()]);
    if (!$s) {
        return null;
    }
    $code = new_share_code();
    q('UPDATE shares SET code_hash = ?, code_expires_at = ? WHERE id = ?',
        [password_hash($code, PASSWORD_DEFAULT), gmdate('Y-m-d\TH:i:s\Z', time() + SHARE_CODE_MINUTES * 60), $shareId]);
    audit($userId, 'patient', 'share.new_code', 'share:' . $shareId, $shareId);
    return $code;
}

/** @return array<string,mixed>|null an active share (not revoked, not expired, owner consenting) */
function active_share(int $shareId): ?array
{
    $s = row('SELECT s.* FROM shares s JOIN users u ON u.id = s.user_id WHERE s.id = ? AND s.revoked_at IS NULL AND s.expires_at > ? AND u.deleted_at IS NULL', [$shareId, now()]);
    if (!$s || !consents((int) $s['user_id'])['share']) {
        return null;
    }
    return $s;
}

/** One-time code check: a code works once and only until it expires. */
function redeem_share_code(string $t, string $code): ?array
{
    $s = row('SELECT * FROM shares WHERE token_hash = ?', [token_hash($t)]);
    if (!$s || !active_share((int) $s['id'])) {
        return null;
    }
    if (!$s['code_hash'] || ($s['code_expires_at'] ?? '') < now() || !password_verify(preg_replace('/\D/', '', $code), $s['code_hash'])) {
        return null;
    }
    q('UPDATE shares SET code_hash = NULL, code_expires_at = NULL WHERE id = ?', [$s['id']]);
    return $s;
}

function doctor_login(int $shareId): void
{
    if (empty($_SESSION['doctor'])) {
        session_regenerate_id(true);
    }
    $_SESSION['doctor'][$shareId] = time();
    $_SESSION['doctor_seen'] = time();
}

/** @return list<array<string,mixed>> shares this browser verified, still active and not idle */
function doctor_shares(): array
{
    $idle = (int) eixo_config()['doctor_idle_minutes'] * 60;
    if (empty($_SESSION['doctor']) || time() - (int) ($_SESSION['doctor_seen'] ?? 0) > $idle) {
        unset($_SESSION['doctor'], $_SESSION['doctor_seen']);
        return [];
    }
    $_SESSION['doctor_seen'] = time();
    $out = [];
    foreach (array_keys($_SESSION['doctor']) as $id) {
        $s = active_share((int) $id);
        if ($s) {
            $out[] = $s;
        } else {
            unset($_SESSION['doctor'][$id]);
        }
    }
    return $out;
}

function doctor_share(int $shareId): ?array
{
    foreach (doctor_shares() as $s) {
        if ((int) $s['id'] === $shareId) {
            return $s;
        }
    }
    return null;
}

/** Whether a date falls inside a share's scope. */
function in_share_scope(array $share, ?string $date): bool
{
    if ($share['scope'] === 'all') {
        return true;
    }
    if (!$date) {
        return false;
    }
    $d = substr($date, 0, 10);
    return (!$share['scope_from'] || $d >= $share['scope_from']) && (!$share['scope_to'] || $d <= $share['scope_to']);
}
