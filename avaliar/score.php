<?php
declare(strict_types=1);

function format_points(float $points): string
{
    return number_format($points, 1, ',', '');
}

function score_resume(array $input): array
{
    $text = (string) ($input['text'] ?? '');
    $posting = (string) ($input['posting'] ?? '');
    $readable = ($input['readable'] ?? false) === true;
    $title = score_title($posting);
    $weights = [
        'one_page' => 2.0,
        'linear' => 2.0,
        'objective' => 2.0,
        'keywords' => 2.0,
        'bullet' => 1.0,
        'authority' => 0.5,
        'docx' => 0.5,
    ];
    $passed = [
        'one_page' => score_one_page($input),
        'linear' => $readable && ($input['linear'] ?? false) === true,
        'objective' => $readable && score_objective($text, $title),
        'keywords' => $readable && score_keywords($text, $posting),
        'bullet' => $readable && score_bullet($text),
        'authority' => $readable && score_authority($text),
        'docx' => ($input['extension'] ?? null) === 'docx' && ($input['valid_docx'] ?? null) === true,
    ];

    $checks = [];
    $points = 0.0;
    foreach ($weights as $id => $weight) {
        $checks[] = ['id' => $id, 'passed' => $passed[$id], 'points' => $weight];
        if ($passed[$id]) {
            $points += $weight;
        }
    }

    return [
        'points' => $points,
        'title' => $title,
        'readable' => $readable,
        'checks' => $checks,
    ];
}

function score_title(string $posting): string
{
    foreach (score_lines($posting) as $line) {
        $trimmed = trim($line['text']);
        if ($trimmed !== '') {
            return $trimmed;
        }
    }
    return '';
}

function score_one_page(array $input): bool
{
    $extension = (string) ($input['extension'] ?? '');
    if ($extension === 'pdf') {
        return (int) ($input['pages'] ?? 0) === 1;
    }
    if ($extension === 'docx') {
        return score_word_count((string) ($input['text'] ?? '')) <= 700;
    }
    return false;
}

function score_word_count(string $text): int
{
    $text = trim($text);
    if ($text === '') {
        return 0;
    }
    $count = preg_match_all('/\S+/u', $text);
    return $count === false ? 0 : $count;
}

function score_objective(string $text, string $title): bool
{
    if ($title === '') {
        return false;
    }
    $lines = score_lines($text);
    $total = count($lines);
    for ($i = 0; $i < $total; $i++) {
        $heading = score_heading_offset($lines[$i]['text']);
        if ($heading === null) {
            continue;
        }
        if ($lines[$i]['offset'] + $heading >= 600) {
            continue;
        }
        if (score_has_title($lines[$i]['text'], $title)) {
            return true;
        }
        for ($j = $i + 1; $j < $total; $j++) {
            if (trim($lines[$j]['text']) === '') {
                continue;
            }
            if (score_has_title($lines[$j]['text'], $title)) {
                return true;
            }
            break;
        }
    }
    return false;
}

function score_keywords(string $text, string $posting): bool
{
    $terms = score_terms($posting);
    $total = count($terms);
    if ($total === 0) {
        return false;
    }
    $present = array_flip(score_words(score_keyword_body($text)));
    $matched = 0;
    foreach ($terms as $term) {
        if (isset($present[$term])) {
            $matched++;
        }
    }
    if ($total < 4) {
        return $matched === $total;
    }
    return $matched * 5 >= $total * 3;
}

function score_terms(string $posting): array
{
    $skipped = false;
    $rest = [];
    foreach (score_lines($posting) as $line) {
        if (!$skipped && trim($line['text']) !== '') {
            $skipped = true;
            continue;
        }
        if ($skipped) {
            $rest[] = $line['text'];
        }
    }
    $stop = score_stopwords();
    $terms = [];
    foreach (score_words(implode("\n", $rest)) as $word) {
        if (mb_strlen($word, 'UTF-8') < 4 || isset($stop[$word]) || isset($terms[$word])) {
            continue;
        }
        $terms[$word] = true;
        if (count($terms) === 12) {
            break;
        }
    }
    return array_keys($terms);
}

function score_keyword_body(string $text): string
{
    $lines = score_lines($text);
    $skip = null;
    foreach ($lines as $i => $line) {
        if (score_heading_offset($line['text']) !== null) {
            $skip = $i;
            break;
        }
    }
    $kept = [];
    foreach ($lines as $i => $line) {
        if ($skip !== null && ($i === $skip || $i === $skip + 1)) {
            continue;
        }
        $kept[] = $line['text'];
    }
    return implode("\n", $kept);
}

function score_bullet(string $text): bool
{
    $verbs = array_flip(explode(' ', 'liderei conduzi implementei reduzi aumentei criei desenvolvi gerenciei entreguei analisei led built reduced increased created developed managed delivered analyzed launched'));
    foreach (score_lines($text) as $line) {
        $content = score_bullet_content($line['text']);
        if ($content === null) {
            continue;
        }
        $tokens = preg_split('/\s+/u', trim($content), -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === false || $tokens === []) {
            continue;
        }
        $first = score_words($tokens[0]);
        if ($first === [] || !isset($verbs[$first[0]])) {
            continue;
        }
        if (score_bullet_result($line['text'])) {
            return true;
        }
    }
    return false;
}

function score_bullet_content(string $line): ?string
{
    $raw = ltrim($line);
    foreach (['•', '-', '*', 'N.'] as $marker) {
        if (str_starts_with($raw, $marker)) {
            return ltrim(substr($raw, strlen($marker)));
        }
    }
    if (preg_match('/^\d+\./u', $raw) === 1) {
        return ltrim((string) preg_replace('/^\d+\./u', '', $raw, 1));
    }
    return null;
}

function score_bullet_result(string $line): bool
{
    if (preg_match('/\d/u', $line) === 1) {
        return true;
    }
    $folded = score_fold($line);
    foreach (['aumento', 'redução', 'reducao', 'increase', 'increased', 'reduced', 'reduction'] as $effect) {
        if (str_contains($folded, score_fold($effect))) {
            return true;
        }
    }
    return false;
}

function score_authority(string $text): bool
{
    $head = mb_strtolower(mb_substr($text, 0, 800, 'UTF-8'), 'UTF-8');
    foreach (['anos de', 'years of', 'especialista', 'senior', 'sênior', 'lead', 'responsável por'] as $phrase) {
        if (str_contains($head, mb_strtolower($phrase, 'UTF-8'))) {
            return true;
        }
    }
    return false;
}

function score_has_title(string $line, string $title): bool
{
    return mb_stripos($line, $title, 0, 'UTF-8') !== false;
}

function score_heading_offset(string $line): ?int
{
    if (preg_match('/^(\s*)(Objetivo|Objective)/u', $line, $match) !== 1) {
        return null;
    }
    return mb_strlen($match[1], 'UTF-8');
}

/** @return list<array{text: string, offset: int}> */
function score_lines(string $text): array
{
    $parts = preg_split('/(\R)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return [['text' => $text, 'offset' => 0]];
    }
    $lines = [];
    $offset = 0;
    $count = count($parts);
    for ($i = 0; $i < $count; $i += 2) {
        $lines[] = ['text' => $parts[$i], 'offset' => $offset];
        $offset += mb_strlen($parts[$i], 'UTF-8');
        if ($i + 1 < $count) {
            $offset += mb_strlen($parts[$i + 1], 'UTF-8');
        }
    }
    return $lines;
}

/** @return list<string> */
function score_words(string $text): array
{
    $folded = score_fold($text);
    if (preg_match_all('/[a-z]+/', $folded, $matches) === false) {
        return [];
    }
    return $matches[0];
}

function score_fold(string $text): string
{
    $lower = mb_strtolower($text, 'UTF-8');
    return strtr($lower, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
    ]);
}

/** @return array<string, true> */
function score_stopwords(): array
{
    return array_flip([
        'de', 'da', 'do', 'das', 'dos', 'em', 'no', 'na', 'nos', 'nas',
        'um', 'uma', 'uns', 'umas', 'para', 'com', 'por', 'que', 'se', 'ou',
        'como', 'mais', 'nao', 'sobre', 'entre', 'ate', 'sua', 'seu', 'seus', 'suas',
        'voce', 'aos', 'as', 'os', 'the', 'and', 'for', 'with', 'from', 'that',
        'this', 'your', 'you', 'are', 'was', 'were', 'have', 'has', 'been', 'will',
        'into', 'over', 'under', 'than', 'then', 'our', 'their',
    ]);
}
