<?php
declare(strict_types=1);

require_once __DIR__ . '/submit.php';
require_once __DIR__ . '/copy.php';

function page_empty_state(): array
{
    return [
        'error' => '',
        'score' => null,
        'mail_failed' => false,
        'values' => ['email' => '', 'posting' => ''],
    ];
}

function page_result_state(array $post, array $result): array
{
    $ok = ($result['ok'] ?? false) === true;
    $email = $post['email'] ?? '';
    $posting = $post['posting'] ?? '';
    $score = $result['score'] ?? null;
    $error = $result['error'] ?? '';
    return [
        'error' => is_string($error) ? $error : '',
        'score' => is_array($score) ? $score : null,
        'mail_failed' => ($result['mail_failed'] ?? false) === true,
        'values' => [
            'email' => $ok || !is_string($email) ? '' : $email,
            'posting' => $ok || !is_string($posting) ? '' : $posting,
        ],
    ];
}

function render_page(array $state): string
{
    $text = page_text();
    $error = $state['error'] ?? '';
    $score = $state['score'] ?? null;
    $values = $state['values'] ?? [];
    $email = is_array($values) && is_string($values['email'] ?? null) ? $values['email'] : '';
    $posting = is_array($values) && is_string($values['posting'] ?? null) ? $values['posting'] : '';
    $errorId = is_string($error) ? $error : '';
    $score = is_array($score) ? $score : null;
    $mailFailed = ($state['mail_failed'] ?? false) === true;

    $formatted = null;
    $whole = null;
    $tenth = null;
    if ($score !== null) {
        $points = $score['points'] ?? 0;
        $formatted = format_points(is_int($points) || is_float($points) ? (float) $points : 0.0);
        $parts = explode(',', $formatted, 2);
        $whole = $parts[0];
        $tenth = $parts[1] ?? '';
    }

    $integerOn = null;
    if ($whole === '10' || (is_string($whole) && preg_match('/\A[0-9]\z/', $whole) === 1)) {
        $integerOn = $whole;
    }
    $tenthOn = is_string($tenth) && preg_match('/\A[0-9]\z/', $tenth) === 1 ? $tenth : null;
    $integerLabels = [];
    for ($i = 0; $i <= 9; $i++) {
        $integerLabels[] = (string) $i;
    }
    $integerLabels[] = '10';
    $tenthLabels = [];
    for ($i = 0; $i <= 9; $i++) {
        $tenthLabels[] = (string) $i;
    }

    if ($formatted === null) {
        $head = '<p class="wait" lang="pt">' . page_h($text['wait']['pt']) . '</p>'
            . '<p class="wait" lang="en">' . page_h($text['wait']['en']) . '</p>';
    } else {
        $head = '<div class="num">' . page_h($formatted) . '</div>'
            . '<p class="of">' . page_pair($text['of']['pt'], $text['of']['en']) . '</p>';
    }

    $notice = '';
    if ($mailFailed) {
        $notice = '<p class="error" role="alert">' . page_pair($text['mail_failed']['pt'], $text['mail_failed']['en']) . '</p>';
    }

    $about = '<section class="about">'
        . '<div lang="pt">' . page_paragraphs($text['about']['pt']) . '</div>'
        . '<div lang="en">' . page_paragraphs($text['about']['en']) . '</div>'
        . '</section>';

    return '<!DOCTYPE html>'
        . '<html lang="pt-BR" data-lang="pt" data-pt-title="' . page_h($text['title']['pt']) . '" data-en-title="' . page_h($text['title']['en']) . '">'
        . '<head>'
        . '<meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . page_h($text['title']['pt']) . '</title>'
        . '<link rel="icon" href="/assets/favicon.svg">'
        . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600&amp;family=Barlow:wght@400;600;700&amp;display=swap">'
        . '<link rel="stylesheet" href="/avaliar/style.css">'
        . '<script src="/assets/i18n.js"></script>'
        . '</head>'
        . '<body>'
        . '<header class="band">'
        . '<h1>' . page_pair($text['title']['pt'], $text['title']['en']) . '</h1>'
        . '<div class="band-side"><span>Andrey Costa</span>'
        . '<div class="langs" role="group" aria-label="Idioma" data-i18n-attr="aria-label" data-pt-aria-label="Idioma" data-en-aria-label="Language">'
        . '<button type="button" data-set-lang="pt" aria-pressed="true">PT</button>'
        . '<button type="button" data-set-lang="en" aria-pressed="false">EN</button>'
        . '</div></div>'
        . '</header>'
        . '<main>'
        . '<div class="sheet">'
        . '<form method="post" enctype="multipart/form-data" novalidate>'
        . page_field('1', 'email', $text['email'], '<input id="email" name="email" type="email" inputmode="email" autocomplete="email" value="' . page_h($email) . '">')
        . page_field('2', 'resume', $text['file'], '<input id="resume" name="resume" type="file" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">')
        . page_field('3', 'posting', $text['posting'], '<p class="hint" lang="pt">' . page_h($text['hint']['pt']) . '</p>'
            . '<p class="hint" lang="en">' . page_h($text['hint']['en']) . '</p>'
            . '<p class="hint" lang="pt">' . page_h($text['first_line']['pt']) . '</p>'
            . '<p class="hint" lang="en">' . page_h($text['first_line']['en']) . '</p>'
            . '<textarea id="posting" name="posting">' . page_h($posting) . '</textarea>')
        . '<label class="consent"><input type="checkbox" name="consent" value="1"><span>'
        . page_pair(submission_consent(), $text['consent_en'])
        . '</span></label>'
        . page_error($errorId)
        . '<button type="submit">' . page_pair($text['button']['pt'], $text['button']['en']) . '</button>'
        . '</form>'
        . '<aside class="score">'
        . $notice
        . '<div class="who">' . page_pair($text['score']['pt'], $text['score']['en']) . '</div>'
        . $head
        . page_digits('integer', $text['integer']['pt'], $text['integer']['en'], $integerLabels, $integerOn)
        . page_digits('tenth', $text['tenth']['pt'], $text['tenth']['en'], $tenthLabels, $tenthOn)
        . page_checks($score)
        . '</aside>'
        . '</div>'
        . $about
        . '</main>'
        . '</body></html>';
}

function page_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array{pt: string, en: string} $label */
function page_field(string $number, string $id, array $label, string $control): string
{
    return '<div class="row"><div class="box">' . page_h($number) . '</div><div>'
        . '<label class="lbl" for="' . page_h($id) . '">' . page_pair($label['pt'], $label['en']) . '</label>'
        . $control
        . '</div></div>';
}

function page_pair(string $pt, string $en): string
{
    return '<span lang="pt">' . page_h($pt) . '</span><span lang="en">' . page_h($en) . '</span>';
}

/** @param list<string> $lines */
function page_paragraphs(array $lines): string
{
    $html = '';
    foreach ($lines as $line) {
        $html .= '<p>' . page_h($line) . '</p>';
    }
    return $html;
}

function page_error(string $id): string
{
    if ($id === '') {
        return '';
    }
    $copy = page_copy();
    $text = $copy[$id] ?? page_text()['fallback'];
    return '<p class="error" role="alert">' . page_pair($text['pt'], $text['en']) . '</p>';
}

/** @param list<string> $labels */
function page_digits(string $place, string $pt, string $en, array $labels, ?string $active): string
{
    $html = '<div class="digits" data-place="' . page_h($place) . '">'
        . '<b lang="pt">' . page_h($pt) . '</b><b lang="en">' . page_h($en) . '</b>';
    foreach ($labels as $label) {
        $class = $active !== null && $active === $label ? ' class="on"' : '';
        $html .= '<i' . $class . '>' . page_h($label) . '</i>';
    }
    return $html . '</div>';
}

function page_checks(?array $score): string
{
    if ($score === null) {
        return '';
    }
    $checks = $score['checks'] ?? [];
    if (!is_array($checks)) {
        return '';
    }
    $copy = page_text()['checks'];
    $rows = '';
    foreach ($checks as $check) {
        if (!is_array($check)) {
            continue;
        }
        $id = $check['id'] ?? '';
        if (!is_string($id)) {
            $id = '';
        }
        $failed = ($check['passed'] ?? false) !== true;
        $text = $copy[$id] ?? ['pt' => $id, 'en' => $id];
        $rows .= '<p class="' . ($failed ? 'check fill' : 'check') . '" data-check="' . page_h($id) . '">'
            . '<i class="' . ($failed ? 'mark fill' : 'mark') . '"></i>'
            . page_pair($text['pt'], $text['en'])
            . '</p>';
    }
    if ($rows === '') {
        return '';
    }
    $legend = page_text()['legend'];
    return '<div class="checks">' . $rows
        . '<p class="legend">' . page_pair($legend['pt'], $legend['en']) . '</p>'
        . '</div>';
}
