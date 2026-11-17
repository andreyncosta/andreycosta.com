<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/score.php';

$failures = 0;
$count = 0;

function ok(bool $cond, string $label): void
{
    global $failures, $count;
    $count++;
    if (!$cond) {
        $failures++;
        echo "FAIL $label\n";
    }
}

/** @return array<string, array{id: string, passed: bool, points: float}> */
function check_map(array $score): array
{
    $map = [];
    foreach ($score['checks'] as $check) {
        $map[$check['id']] = $check;
    }
    return $map;
}

function passed(array $score, string $id): bool
{
    $map = check_map($score);
    return isset($map[$id]) && $map[$id]['passed'] === true;
}

$posting = "Analista de Dados Sênior\nPower métricas produto experimentos pipelines\n";
$full = "Ana Souza\nana@email.com\nObjetivo\nAnalista de Dados Sênior\nEspecialista com anos de experiência.\n• Liderei o painel e reduzi o tempo em 30%.\nTrabalhei com power, métricas, produto e experimentos no corpo.\n";
$base = [
    'text' => $full,
    'pages' => 1,
    'posting' => $posting,
    'extension' => 'docx',
    'valid_docx' => true,
    'linear' => true,
    'readable' => true,
];

$perfect = score_resume($base);
ok($perfect['points'] === 10.0, 'full docx scores 10.0');
ok($perfect['title'] === 'Analista de Dados Sênior', 'title is the first posting line');
ok($perfect['readable'] === true, 'readable is returned');
ok(array_column($perfect['checks'], 'id') === ['one_page', 'linear', 'objective', 'keywords', 'bullet', 'authority', 'docx'], 'check ids in order');
ok(array_column($perfect['checks'], 'points') === [2.0, 2.0, 2.0, 2.0, 1.0, 0.5, 0.5], 'check points are 2, 2, 2, 2, 1, 0.5, 0.5');
foreach (array_column($perfect['checks'], 'id') as $id) {
    ok(passed($perfect, $id), "full docx passes $id");
}

ok(format_points(10.0) === '10,0', 'format 10.0 uses a comma');
ok(format_points(7.5) === '7,5', 'format 7.5 uses a comma');

$pdf = $base;
$pdf['extension'] = 'pdf';
$pdf['valid_docx'] = false;
ok(score_resume($pdf)['points'] === 9.5, 'pdf loses the docx half point');
$pdf['pages'] = 2;
ok(score_resume($pdf)['points'] === 7.5, 'two-page pdf loses one_page and docx');

$noObjective = $base;
$noObjective['text'] = str_replace("Objetivo\n", '', $full);
ok(score_resume($noObjective)['points'] === 8.0, 'docx without Objetivo scores 8.0');

$termsOnHeading = $base;
$termsOnHeading['text'] = "Ana Souza\nana@email.com\nObjetivo power métricas produto experimentos pipelines\nAnalista de Dados Sênior\nEspecialista com anos de experiência.\n• Liderei o painel e reduzi o tempo em 30%.\n";
$termsScore = score_resume($termsOnHeading);
ok($termsScore['points'] === 8.0, 'terms only on the Objetivo line score 8.0');
ok(passed($termsScore, 'keywords') === false, 'keywords fail when terms are only on the Objetivo line');

$weakBullet = $base;
$weakBullet['text'] = str_replace('• Liderei o painel e reduzi o tempo em 30%.', '• Participei da reunião.', $full);
ok(score_resume($weakBullet)['points'] === 9.0, 'bullet without verb and result scores 9.0');

$otherJob = $base;
$otherJob['posting'] = "Empresa X\nPower métricas produto experimentos pipelines\n";
$otherScore = score_resume($otherJob);
ok($otherScore['points'] === 8.0, 'wrong title scores 8.0');
ok(passed($otherScore, 'objective') === false, 'objective fails when the title is absent');
ok($otherScore['title'] === 'Empresa X', 'title is Empresa X');

$threeOfFive = $base;
$threeOfFive['text'] = "Ana Souza\nana@email.com\nObjetivo\nAnalista de Dados Sênior\nEspecialista com anos de experiência.\n• Liderei o painel e reduzi o tempo em 30%.\nTrabalhei com power, métricas e produto no corpo.\n";
ok(passed(score_resume($threeOfFive), 'keywords') === true, 'three of five terms pass keywords');

$twoOfFive = $base;
$twoOfFive['text'] = "Ana Souza\nana@email.com\nObjetivo\nAnalista de Dados Sênior\nEspecialista com anos de experiência.\n• Liderei o painel e reduzi o tempo em 30%.\nTrabalhei com power e métricas no corpo.\n";
ok(passed(score_resume($twoOfFive), 'keywords') === false, 'two of five terms fail keywords');

$threeTerms = "Analista de Dados Sênior\nPower métricas produto\n";
$twoOfThree = $base;
$twoOfThree['posting'] = $threeTerms;
$twoOfThree['text'] = "Ana Souza\nana@email.com\nObjetivo\nAnalista de Dados Sênior\nEspecialista com anos de experiência.\n• Liderei o painel e reduzi o tempo em 30%.\nTrabalhei com power e métricas no corpo.\n";
ok(passed(score_resume($twoOfThree), 'keywords') === false, 'two of three terms fail keywords');

$allThree = $twoOfThree;
$allThree['text'] = "Ana Souza\nana@email.com\nObjetivo\nAnalista de Dados Sênior\nEspecialista com anos de experiência.\n• Liderei o painel e reduzi o tempo em 30%.\nTrabalhei com power, métricas e produto no corpo.\n";
ok(passed(score_resume($allThree), 'keywords') === true, 'all three terms pass keywords');

$fourteen = ['alfa', 'beta', 'gama', 'delta', 'echo', 'foxt', 'golf', 'hotel', 'india', 'julio', 'kilo', 'lima', 'mike', 'nova'];
$posting14 = "Cargo Zeta\n" . implode(' ', $fourteen) . "\n";
$firstTwelve = $base;
$firstTwelve['posting'] = $posting14;
$firstTwelve['text'] = "Objetivo\nCargo Zeta\n" . implode(' ', array_slice($fourteen, 0, 12)) . "\n";
ok(passed(score_resume($firstTwelve), 'keywords') === true, 'first twelve of fourteen terms pass keywords');

$capped = $base;
$capped['posting'] = $posting14;
$capped['text'] = "Objetivo\nCargo Zeta\n" . implode(' ', array_merge(array_slice($fourteen, 0, 7), array_slice($fourteen, 12, 2))) . "\n";
ok(passed(score_resume($capped), 'keywords') === false, 'terms past the twelfth do not count');

$stopword = $base;
$stopword['posting'] = "Analista de Dados Sênior\nPower métricas produto para\n";
$stopword['text'] = "Ana Souza\nana@email.com\nObjetivo\nAnalista de Dados Sênior\nEspecialista com anos de experiência.\n• Liderei o painel e reduzi o tempo em 30%.\nTrabalhei com power, métricas e para no corpo.\n";
ok(passed(score_resume($stopword), 'keywords') === false, 'stopword para is not a keyword');

$shortWord = $base;
$shortWord['posting'] = "Analista de Dados Sênior\nPower métricas produto sol\n";
$shortWord['text'] = "Ana Souza\nana@email.com\nObjetivo\nAnalista de Dados Sênior\nEspecialista com anos de experiência.\n• Liderei o painel e reduzi o tempo em 30%.\nTrabalhei com power, métricas e sol no corpo.\n";
ok(passed(score_resume($shortWord), 'keywords') === false, 'three-letter sol is not a keyword');

$accentStop = $base;
$accentStop['posting'] = "Analista de Dados Sênior\nPower métricas produto você\n";
$accentStop['text'] = "Ana Souza\nana@email.com\nObjetivo\nAnalista de Dados Sênior\nEspecialista com anos de experiência.\n• Liderei o painel e reduzi o tempo em 30%.\nTrabalhei com power, métricas e você no corpo.\n";
ok(passed(score_resume($accentStop), 'keywords') === false, 'stopword você is not a keyword');

$lowerTitle = $base;
$lowerTitle['text'] = str_replace('Analista de Dados Sênior', 'analista de dados sênior', $full);
ok(passed(score_resume($lowerTitle), 'objective') === true, 'title match is case-insensitive');

$noAccent = $base;
$noAccent['text'] = str_replace('Sênior', 'Senior', $full);
$noAccentScore = score_resume($noAccent);
ok(passed($noAccentScore, 'objective') === false, 'Sênior does not match Senior');

$at600 = $base;
$prefix600 = str_repeat('a', 599) . "\n";
ok(strlen($prefix600) === 600, 'ascii heading fixture is 600 characters');
$at600['text'] = $prefix600 . "Objetivo\nAnalista de Dados Sênior\nEspecialista com anos de experiência.\n";
ok(substr($at600['text'], 600, 8) === 'Objetivo', 'heading begins at character 600');
ok(passed(score_resume($at600), 'objective') === false, 'heading at character 600 fails objective');

$earlyAccent = $base;
$prefixAccent = str_repeat('á', 400) . "\n";
ok(mb_strlen($prefixAccent, 'UTF-8') === 401, 'accented prefix is 401 characters');
ok(strlen($prefixAccent) > 600, 'accented prefix is more than 600 bytes');
$earlyAccent['text'] = $prefixAccent . "Objetivo\nAnalista de Dados Sênior\nEspecialista com anos de experiência.\n";
ok(passed(score_resume($earlyAccent), 'objective') === true, 'heading before character 600 passes');

$authorityLate = $base;
$authorityLate['text'] = str_repeat('x', 800) . 'anos de experiencia';
ok(strpos($authorityLate['text'], 'anos de') === 800, 'anos de begins at character 800');
ok(passed(score_resume($authorityLate), 'authority') === false, 'anos de after character 800 fails authority');

$authorityChars = $base;
$authorityChars['text'] = str_repeat('á', 500) . 'anos de experiencia';
ok(mb_strpos($authorityChars['text'], 'anos de', 0, 'UTF-8') === 500, 'anos de begins at character 500');
ok(passed(score_resume($authorityChars), 'authority') === true, 'anos de inside 800 characters passes authority');

$words701 = $base;
$words701['text'] = implode(' ', array_fill(0, 701, 'palavra'));
ok(passed(score_resume($words701), 'one_page') === false, '701-word docx fails one_page');

$words700 = $base;
$words700['text'] = implode(' ', array_fill(0, 700, 'palavra'));
ok(passed(score_resume($words700), 'one_page') === true, '700-word docx passes one_page');

$unreadable = $base;
$unreadable['readable'] = false;
$unreadable['extension'] = 'pdf';
$unreadable['valid_docx'] = false;
$unreadable['pages'] = 1;
$unreadable['linear'] = true;
$unreadableScore = score_resume($unreadable);
ok($unreadableScore['points'] === 2.0, 'unreadable one-page pdf scores 2.0');
ok($unreadableScore['readable'] === false, 'unreadable flag is returned');
foreach (['linear', 'objective', 'keywords', 'bullet', 'authority'] as $id) {
    ok(passed($unreadableScore, $id) === false, "$id fails when the file is unreadable");
}

$nonlinear = $base;
$nonlinear['linear'] = false;
$nonlinearScore = score_resume($nonlinear);
ok($nonlinearScore['points'] === 8.0, 'nonlinear text scores 8.0');
ok(passed($nonlinearScore, 'linear') === false, 'linear fails when the flag is false');

$invalidDocx = $base;
$invalidDocx['valid_docx'] = false;
$invalidScore = score_resume($invalidDocx);
ok($invalidScore['points'] === 9.5, 'invalid docx scores 9.5');
ok(passed($invalidScore, 'docx') === false, 'docx fails when the file is invalid');
ok(check_map($invalidScore)['docx']['points'] === 0.5, 'docx check keeps its weight');

$titleOnly = $base;
$titleOnly['posting'] = "Analista de Dados Sênior\n";
$titleOnlyScore = score_resume($titleOnly);
ok(passed($titleOnlyScore, 'keywords') === true, 'a title-only posting passes keywords');
ok($titleOnlyScore['points'] === 10.0, 'a title-only posting still scores 10.0');

$again = score_resume($base);
ok($again['points'] === $perfect['points'], 'same input returns the same points');

if ($failures > 0) {
    exit(1);
}
echo "OK $count\n";
