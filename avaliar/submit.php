<?php
declare(strict_types=1);

require_once __DIR__ . '/score.php';
require_once __DIR__ . '/extract.php';
require_once __DIR__ . '/lead.php';

/**
 * @return array{ok:bool, error:string}
 */
function validate_submission(array $post, array $file): array
{
    if (($post['consent'] ?? null) !== '1') {
        return ['ok' => false, 'error' => 'unchecked'];
    }
    $email = $post['email'] ?? null;
    if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'error' => 'bad_email'];
    }
    $posting = $post['posting'] ?? null;
    if (!is_string($posting) || trim($posting) === '') {
        return ['ok' => false, 'error' => 'empty_posting'];
    }
    $name = $file['name'] ?? null;
    if (!is_string($name) || $name === '') {
        return ['ok' => false, 'error' => 'missing_file'];
    }
    $size = $file['size'] ?? null;
    if (!is_int($size) || $size > 5242880) {
        return ['ok' => false, 'error' => 'too_large'];
    }
    $extension = submission_extension($name);
    if ($extension !== 'pdf' && $extension !== 'docx') {
        return ['ok' => false, 'error' => 'bad_type'];
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool, error:string, score:?array, mail_failed:bool}
 */
function handle_submission(array $post, array $file, array $deps): array
{
    $validation = validate_submission($post, $file);
    if ($validation['ok'] !== true) {
        return submission_result(false, $validation['error'], null, false);
    }

    $store = $deps['store'] ?? '';
    $ip = $deps['ip'] ?? '';
    $now = $deps['now'] ?? 0;
    if (!is_string($store) || !is_string($ip) || !is_int($now) || !rate_limit_allow($store, $ip, $now)) {
        return submission_result(false, 'rate_limited', null, false);
    }

    $name = (string) $file['name'];
    $copy = submission_copy((string) ($file['tmp_name'] ?? ''));
    if ($copy === null) {
        return submission_result(false, 'unreadable', null, false);
    }

    try {
        $extension = submission_extension($name);
        $extracted = ($deps['extractor'])($copy, $extension);
        if (!is_array($extracted) || ($extracted['readable'] ?? false) !== true) {
            return submission_result(false, 'unreadable', null, false);
        }
        $extracted['posting'] = (string) $post['posting'];
        $extracted['extension'] = $extension;
        $score = score_resume($extracted);
        unset($extracted);
        $email = (string) $post['email'];
        $body = lead_body(
            $email,
            (float) $score['points'],
            submission_failed_ids($score),
            (string) $score['title'],
            submission_consent(),
            date('Y-m-d H:i:s', $now)
        );
        $subject = 'Avaliação de currículo ' . $email;
        $sent = ($deps['mailer'])('andreynacosta46@gmail.com', $subject, $body);
        return submission_result(true, '', $score, $sent !== true);
    } catch (Throwable) {
        return submission_result(false, 'unreadable', null, false);
    } finally {
        // $copy is the random file from above. The caller's tmp_name stays.
        if (is_file($copy)) {
            unlink($copy);
        }
    }
}

function submission_extension(string $name): string
{
    $dot = strrpos($name, '.');
    if ($dot === false) {
        return '';
    }
    $extension = substr($name, $dot + 1);
    return strtr($extension, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
}

function submission_consent(): string
{
    return 'Andrey Costa guarda o e-mail para responder esta avaliação e apaga a mensagem quando a pessoa responde para andreynacosta46@gmail.com pedindo o apagamento.';
}

/** @return list<string> */
function submission_failed_ids(array $score): array
{
    $failed = [];
    $checks = $score['checks'] ?? [];
    if (!is_array($checks)) {
        return [];
    }
    foreach ($checks as $check) {
        if (!is_array($check) || ($check['passed'] ?? false) === true) {
            continue;
        }
        $failed[] = (string) ($check['id'] ?? '');
    }
    return $failed;
}

function submission_copy(string $tmpName): ?string
{
    if ($tmpName === '' || !is_file($tmpName) || !is_readable($tmpName)) {
        return null;
    }
    $copy = tempnam(sys_get_temp_dir(), 'avaliar');
    if (!is_string($copy)) {
        return null;
    }
    if (!copy($tmpName, $copy)) {
        if (is_file($copy)) {
            unlink($copy);
        }
        return null;
    }
    return $copy;
}

/** @return array{ok:bool, error:string, score:?array, mail_failed:bool} */
function submission_result(bool $ok, string $error, ?array $score, bool $mailFailed): array
{
    return [
        'ok' => $ok,
        'error' => $error,
        'score' => $score,
        'mail_failed' => $mailFailed,
    ];
}
