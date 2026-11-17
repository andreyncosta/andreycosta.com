<?php
declare(strict_types=1);

function page_mail_subject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

function page_mail_headers(): string
{
    return "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
}

function page_mail(string $to, string $subject, string $body): bool
{
    return mail($to, page_mail_subject($subject), $body, page_mail_headers());
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== realpath(__FILE__)) {
    return;
}

require_once __DIR__ . '/page.php';

$state = page_empty_state();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $post = $_POST;
    $file = $_FILES['resume'] ?? [];
    if (!is_array($file)) {
        $file = [];
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $result = handle_submission($post, $file, [
        'ip' => is_string($ip) ? $ip : '',
        'now' => time(),
        'store' => __DIR__ . '/storage',
        'mailer' => page_mail(...),
        'extractor' => extract_file(...),
    ]);
    $state = page_result_state($post, $result);
}

header('Content-Type: text/html; charset=UTF-8');
echo render_page($state);
