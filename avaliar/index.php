<?php
declare(strict_types=1);

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
        'mailer' => mail(...),
        'extractor' => extract_file(...),
    ]);
    $state = page_result_state($post, $result);
}

header('Content-Type: text/html; charset=UTF-8');
echo render_page($state);
