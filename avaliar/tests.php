<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/score.php';
require __DIR__ . '/extract.php';
require __DIR__ . '/lead.php';
require __DIR__ . '/submit.php';
require __DIR__ . '/page.php';

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

function command_on_path(string $name): bool
{
    $path = getenv('PATH');
    if (!is_string($path) || $path === '') {
        return false;
    }
    foreach (explode(PATH_SEPARATOR, $path) as $dir) {
        if ($dir === '') {
            continue;
        }
        if (is_executable(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name)) {
            return true;
        }
    }
    return false;
}

function fixture_docx(?string $documentXml): string
{
    $path = sys_get_temp_dir() . '/avaliar-docx-' . bin2hex(random_bytes(4)) . '.docx';
    $zip = new ZipArchive();
    $opened = $zip->open($path, ZipArchive::CREATE);
    if ($opened !== true) {
        throw new RuntimeException('fixture zip did not open');
    }
    if ($documentXml === null) {
        $zip->addFromString('word/other.xml', '<empty/>');
    } else {
        $zip->addFromString('word/document.xml', $documentXml);
    }
    $zip->close();
    return $path;
}

function docx_body(string $inner): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body>' . $inner . '</w:body></w:document>';
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

$paragraph = 'Analista de dados sem tabela';
$linearXml = docx_body('<w:p><w:r><w:t>' . $paragraph . '</w:t></w:r></w:p>');
ok(docx_is_linear($linearXml) === true, 'paragraph xml without a table is linear');
$runnerCalls = 0;
$runner = function (array $argv) use (&$runnerCalls): array {
    $runnerCalls++;
    return ['code' => 0, 'stdout' => '', 'stderr' => ''];
};
$linearPath = fixture_docx($linearXml);
$linearFile = extract_file($linearPath, 'docx', $runner);
ok($linearFile['readable'] === true, 'paragraph docx is readable');
ok($linearFile['valid_docx'] === true, 'paragraph docx is a valid docx');
ok($linearFile['linear'] === true, 'paragraph docx is linear');
ok($linearFile['pages'] === 0, 'docx pages stay 0');
ok(str_contains($linearFile['text'], $paragraph), 'docx text contains the paragraph');
ok(is_file($linearPath), 'extract_file does not delete the docx');
ok($runnerCalls === 0, 'docx does not call the runner');

$linesXml = docx_body('<w:p><w:r><w:t>Objetivo</w:t></w:r></w:p><w:p><w:r><w:t>Ana &amp; Souza</w:t></w:r></w:p>');
$linesPath = fixture_docx($linesXml);
$linesText = extract_file($linesPath, 'docx', $runner)['text'];
ok(str_contains($linesText, "Objetivo\n"), 'a paragraph end becomes a new line');
ok(str_contains($linesText, 'Ana & Souza'), 'docx text decodes xml entities');

$prettyXml = docx_body("\n  <w:p>\n    <w:r>\n      <w:t>Objetivo</w:t>\n    </w:r>\n  </w:p>\n");
$prettyPath = fixture_docx($prettyXml);
ok(extract_file($prettyPath, 'docx', $runner)['text'] === 'Objetivo', 'indentation between tags is not resume text');
$spacedXml = docx_body('<w:p><w:r><w:t>Ana</w:t><w:t xml:space="preserve"> </w:t><w:t>Souza</w:t></w:r></w:p>');
$spacedPath = fixture_docx($spacedXml);
ok(extract_file($spacedPath, 'docx', $runner)['text'] === 'Ana Souza', 'a preserved space between runs stays');
$boxXml = docx_body('<w:p><w:r><w:t>Antes</w:t><w:drawing><w:txbxContent><w:p><w:r><w:t>Caixa</w:t></w:r></w:p></w:txbxContent></w:drawing></w:r></w:p>');
$boxPath = fixture_docx($boxXml);
ok(extract_file($boxPath, 'docx', $runner)['text'] === "Antes\nCaixa", 'a text box paragraph is not repeated');
$emptyPath = fixture_docx('');
$emptyFile = extract_file($emptyPath, 'docx', $runner);
ok($emptyFile['readable'] === true, 'empty document.xml stays readable');
ok($emptyFile['valid_docx'] === true, 'empty document.xml stays a valid docx');
ok($emptyFile['linear'] === true, 'empty document.xml is linear');
ok($emptyFile['text'] === '', 'empty document.xml has empty text');
$brokenPath = fixture_docx('<w:t>Quebrado</w:t><not-closed');
$brokenFile = extract_file($brokenPath, 'docx', $runner);
ok($brokenFile['readable'] === true, 'broken document.xml stays readable');
ok($brokenFile['valid_docx'] === true, 'broken document.xml stays a valid docx');
ok(str_contains($brokenFile['text'], 'Quebrado'), 'broken document.xml still strips to text');

$tblXml = docx_body('<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Celula</w:t></w:r></w:p></w:tc></w:tr></w:tbl>');
ok(docx_is_linear($tblXml) === false, 'xml with w:tbl is not linear');
$tblPath = fixture_docx($tblXml);
$tblFile = extract_file($tblPath, 'docx', $runner);
ok($tblFile['linear'] === false, 'docx with w:tbl is not linear');
ok($tblFile['readable'] === true, 'docx with w:tbl stays readable');
ok($tblFile['valid_docx'] === true, 'docx with w:tbl stays valid');

$drawingXml = docx_body('<w:p><w:r><w:drawing><wp:inline/></w:drawing></w:r></w:p>');
ok(docx_is_linear($drawingXml) === false, 'xml with w:drawing is not linear');
$drawingPath = fixture_docx($drawingXml);
$drawingFile = extract_file($drawingPath, 'docx', $runner);
ok($drawingFile['linear'] === false, 'docx with w:drawing is not linear');

$colsXml = docx_body('<w:sectPr><w:cols w:num="2"/></w:sectPr>');
ok(docx_is_linear($colsXml) === false, 'w:cols with w:num 2 is not linear');
ok(str_contains($colsXml, '<w:cols w:num="2"/>'), 'cols fixture contains the pinned tag');
$colsPath = fixture_docx($colsXml);
$colsFile = extract_file($colsPath, 'docx', $runner);
ok($colsFile['linear'] === false, 'docx with two columns is not linear');
ok(docx_is_linear('<w:cols w:num="1"/>') === true, 'w:cols with w:num 1 stays linear');
ok(docx_is_linear('<w:cols w:space="708" w:num="2"/>') === false, 'w:num after another attribute is not linear');
ok(docx_is_linear('<w:cols w:num="1"/><w:cols w:num="3"/>') === false, 'a later w:cols above 1 is not linear');

$fake = sys_get_temp_dir() . '/avaliar-fake-' . bin2hex(random_bytes(4)) . '.docx';
file_put_contents($fake, "isto nao e um zip\n");
$fakeFile = extract_file($fake, 'docx', $runner);
ok($fakeFile['readable'] === false, 'plain text renamed docx is not readable');
ok($fakeFile['valid_docx'] === false, 'plain text renamed docx is not a valid docx');
ok($fakeFile['linear'] === false, 'plain text renamed docx is not linear');
ok($fakeFile['text'] === '', 'plain text renamed docx has empty text');
ok($fakeFile['pages'] === 0, 'plain text renamed docx has 0 pages');
ok(is_file($fake), 'extract_file does not delete a fake docx');

$missingEntry = fixture_docx(null);
$missingFile = extract_file($missingEntry, 'docx', $runner);
ok($missingFile['readable'] === false, 'zip without document.xml is not readable');
ok($missingFile['valid_docx'] === false, 'zip without document.xml is not a valid docx');
ok($missingFile['linear'] === false, 'zip without document.xml is not linear');
ok($missingFile['text'] === '', 'zip without document.xml has empty text');

$absent = extract_file($fake . '.absent', 'docx', $runner);
ok($absent['readable'] === false, 'missing zip is not readable');
ok($absent['valid_docx'] === false, 'missing zip is not a valid docx');
ok($absent['text'] === '', 'missing zip has empty text');
ok($absent['linear'] === false, 'missing zip is not linear');
ok($runnerCalls === 0, 'failed docx reads do not call the runner');

foreach ([$linearPath, $linesPath, $prettyPath, $spacedPath, $boxPath, $emptyPath, $brokenPath, $tblPath, $drawingPath, $colsPath, $fake, $missingEntry] as $path) {
    if (is_file($path)) {
        unlink($path);
    }
}

$columnLine = 'Esquerda' . str_repeat(' ', 8) . 'Direita';
$dateLine = 'Janeiro 2020' . str_repeat(' ', 4) . 'São Paulo';
$pdfPath = sys_get_temp_dir() . '/avaliar-pdf-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-fake\n");
$pdfCalls = [];
$pdfRunner = function (array $argv) use (&$pdfCalls): array {
    $pdfCalls[] = $argv;
    if (($argv[0] ?? '') === 'pdfinfo') {
        return ['code' => 0, 'stdout' => "Title: x\nPages:           2\nPage size: 595 x 842 pts\n", 'stderr' => ''];
    }
    return ['code' => 0, 'stdout' => 'Olá', 'stderr' => ''];
};
$pdfFile = extract_file($pdfPath, 'pdf', $pdfRunner);
ok($pdfFile['pages'] === 2, 'pdfinfo Pages 2 is the page count');
ok($pdfFile['text'] === 'Olá', 'pdf text is the pdftotext stdout');
ok($pdfFile['readable'] === true, 'pdf with text is readable');
ok($pdfFile['linear'] === true, 'pdf without a column is linear');
ok($pdfFile['valid_docx'] === false, 'pdf is not a valid docx');
ok($pdfCalls === [
    ['pdfinfo', $pdfPath],
    ['pdftotext', '-layout', $pdfPath, '-'],
], 'pdf runner is pdfinfo then pdftotext -layout');
ok(is_file($pdfPath), 'extract_file does not delete the pdf');

$missingCalls = 0;
$missingPdf = extract_file($pdfPath, 'pdf', function (array $argv) use (&$missingCalls): array {
    $missingCalls++;
    return ['code' => 127, 'stdout' => 'Olá', 'stderr' => 'missing'];
});
ok($missingPdf['readable'] === false, 'pdf runner code 127 is not readable');
ok($missingPdf['text'] === '', 'pdf runner code 127 has empty text');
ok($missingPdf['linear'] === false, 'pdf runner code 127 is not linear');
ok($missingPdf['valid_docx'] === false, 'pdf runner code 127 is not a valid docx');
ok($missingCalls === 2, 'a missing pdf command still runs both calls');

$emptyPdf = extract_file($pdfPath, 'pdf', function (array $argv): array {
    return ['code' => 0, 'stdout' => '', 'stderr' => ''];
});
ok($emptyPdf['readable'] === false, 'pdf with empty stdout is not readable');
ok($emptyPdf['text'] === '', 'pdf with empty stdout has empty text');
ok($emptyPdf['linear'] === false, 'pdf with empty stdout is not linear');

$blankPdf = extract_file($pdfPath, 'pdf', function (array $argv): array {
    return ['code' => 0, 'stdout' => " \n\t", 'stderr' => ''];
});
ok($blankPdf['readable'] === false, 'pdf with blank stdout is not readable');
ok($blankPdf['text'] === " \n\t", 'blank pdf text stays the stdout');

$formPdf = extract_file($pdfPath, 'pdf', function (array $argv): array {
    if (($argv[0] ?? '') === 'pdfinfo') {
        return ['code' => 0, 'stdout' => "Pages: 1\n", 'stderr' => ''];
    }
    return ['code' => 0, 'stdout' => "\f", 'stderr' => ''];
});
ok($formPdf['readable'] === false, 'a form feed is not extractable text');
ok($formPdf['text'] === "\f", 'a form feed stays in the stdout');
ok($formPdf['linear'] === false, 'a form feed is not linear');
ok($formPdf['pages'] === 1, 'a blank page still reports pages');

$feedPdf = extract_file($pdfPath, 'pdf', function (array $argv): array {
    if (($argv[0] ?? '') === 'pdfinfo') {
        return ['code' => 0, 'stdout' => "Pages: 1\n", 'stderr' => ''];
    }
    return ['code' => 0, 'stdout' => "Olá\n\f", 'stderr' => ''];
});
ok($feedPdf['readable'] === true, 'text before a form feed stays readable');
ok($feedPdf['text'] === "Olá\n\f", 'a trailing form feed stays in the pdf text');
ok($feedPdf['linear'] === true, 'a trailing form feed is not a column');

$noPages = extract_file($pdfPath, 'pdf', function (array $argv): array {
    if (($argv[0] ?? '') === 'pdfinfo') {
        return ['code' => 0, 'stdout' => "Title: sem paginas\n", 'stderr' => ''];
    }
    return ['code' => 0, 'stdout' => 'Olá', 'stderr' => ''];
});
ok($noPages['pages'] === 0, 'pdf without Pages is 0 pages');
ok($noPages['readable'] === true, 'pdf without Pages can still be readable');

$columnPdf = extract_file($pdfPath, 'pdf', function (array $argv) use ($columnLine): array {
    if (($argv[0] ?? '') === 'pdfinfo') {
        return ['code' => 0, 'stdout' => "Pages: 1\n", 'stderr' => ''];
    }
    return ['code' => 0, 'stdout' => implode("\n", array_fill(0, 4, $columnLine)), 'stderr' => ''];
});
ok($columnPdf['readable'] === true, 'column pdf stays readable');
ok($columnPdf['linear'] === false, 'column pdf is not linear');
ok($columnPdf['pages'] === 1, 'column pdf keeps its page count');
ok($columnPdf['valid_docx'] === false, 'column pdf is not a valid docx');

$rtfCalls = 0;
$rtf = extract_file($pdfPath, 'rtf', function (array $argv) use (&$rtfCalls): array {
    $rtfCalls++;
    return ['code' => 0, 'stdout' => 'Olá', 'stderr' => ''];
});
ok($rtf['readable'] === false, 'rtf stays unreadable');
ok($rtf['text'] === '', 'rtf has empty text');
ok($rtfCalls === 0, 'rtf does not call the runner');

ok(layout_has_column(implode("\n", array_fill(0, 4, $columnLine))) === true, 'four lines with an eight-space gap are a column');
ok(layout_has_column($dateLine . "\n" . $dateLine) === false, 'two date lines are not a column');
ok(layout_has_column(implode("\n", array_fill(0, 3, $columnLine))) === false, 'three column lines are not a column');
ok(layout_has_column(implode("\n", [
    $columnLine,
    '',
    'sem vao',
    $columnLine,
    $columnLine,
    $columnLine,
])) === true, 'column lines do not have to be consecutive');
ok(layout_has_column(implode("\r\n", array_fill(0, 4, $columnLine))) === true, 'column lines may use CRLF');
ok(layout_has_column(implode("\f", array_fill(0, 4, $columnLine))) === true, 'a form feed does not glue column lines');
ok(layout_has_column(implode("\n", [
    $columnLine,
    'X' . $columnLine,
    'XX' . $columnLine,
    $columnLine,
])) === true, 'gap starts two columns apart still count');
ok(layout_has_column(implode("\n", [
    $columnLine,
    'X' . $columnLine,
    'XX' . $columnLine,
    'XXX' . $columnLine,
])) === false, 'gap starts three columns apart do not count');
$scattered = [];
for ($i = 0; $i < 4; $i++) {
    $scattered[] = str_repeat('A', 1 + ($i * 4)) . str_repeat(' ', 8) . 'B';
}
ok(layout_has_column(implode("\n", $scattered)) === false, 'four scattered gaps are not a column');
ok(layout_has_column(implode("\n", array_fill(0, 4, 'Esquerda' . str_repeat(' ', 7) . 'Direita'))) === false, 'seven spaces are not a column gap');
ok(layout_has_column(implode("\n", array_fill(0, 4, 'Esquerda' . str_repeat(' ', 9) . 'Direita'))) === true, 'nine spaces still count as a gap');
ok(layout_has_column(implode("\n", array_fill(0, 4, 'A' . str_repeat(' ', 3) . 'B' . str_repeat(' ', 8) . 'C'))) === true, 'a short gap does not hide the eight-space gap');
ok(layout_has_column(implode("\n", array_fill(0, 4, str_repeat(' ', 8) . 'Direita'))) === false, 'leading spaces are not a column gap');
ok(layout_has_column(implode("\n", array_fill(0, 4, 'Esquerda' . str_repeat(' ', 8)))) === false, 'trailing spaces are not a column gap');
$laterGap = [];
for ($i = 0; $i < 4; $i++) {
    $left = str_repeat('L', 1 + ($i * 3));
    $mid = str_repeat('M', 1 + ((3 - $i) * 3));
    $laterGap[] = $left . str_repeat(' ', 8) . $mid . str_repeat(' ', 8) . 'C';
}
ok(layout_has_column(implode("\n", $laterGap)) === false, 'a later aligned gap does not count');
$withDate = ['Jan' . str_repeat(' ', 30) . '2020'];
for ($i = 0; $i < 4; $i++) {
    $withDate[] = $columnLine;
}
ok(layout_has_column(implode("\n", $withDate)) === true, 'one distant gap does not hide four aligned lines');
$wide = 'ããã' . str_repeat(' ', 8) . 'X';
$narrow = 'aaa' . str_repeat(' ', 8) . 'X';
ok(layout_has_column(implode("\n", [$wide, $wide, $narrow, $narrow])) === true, 'gap columns count characters, not bytes');

if (!command_on_path('pdftotext')) {
    echo "AVISO pdf ignorado\n";
} else {
    $real = extract_file($pdfPath, 'pdf');
    ok($real['readable'] === false, 'pdftotext on a non-pdf is not readable');
    ok($real['text'] === '', 'pdftotext on a non-pdf has empty text');
    ok($real['linear'] === false, 'pdftotext on a non-pdf is not linear');
    ok($real['valid_docx'] === false, 'pdftotext on a non-pdf is not a valid docx');
    ok(is_file($pdfPath), 'extract_file does not delete a non-pdf');
    ok(!is_file(substr($pdfPath, 0, -4) . '.txt'), 'the default runner does not write a sibling txt');
    $infoRun = extract_command(['pdfinfo', $pdfPath]);
    ok($infoRun['code'] > 0 && $infoRun['code'] < 127, 'pdfinfo runs and rejects a non-pdf');
    ok($infoRun['stdout'] === '', 'pdfinfo keeps errors off stdout');
    ok($infoRun['stderr'] !== '', 'pdfinfo stderr is captured');
    $textRun = extract_command(['pdftotext', '-layout', $pdfPath, '-']);
    ok($textRun['code'] > 0 && $textRun['code'] < 127, 'pdftotext runs and rejects a non-pdf');
    ok($textRun['stdout'] === '', 'pdftotext keeps errors off stdout');
    ok($textRun['stderr'] !== '', 'pdftotext stderr is captured');
}
unlink($pdfPath);

$consent = 'Aceito que Andrey Costa guarde este e-mail para contato.';
$when = '2026-11-17 09:00:00';
$body = lead_body(
    'ana.souza@email.com',
    7.5,
    ['one_page', 'docx'],
    'Analista de Dados Sênior',
    $consent,
    $when
);
ok(str_contains($body, 'ana.souza@email.com'), 'lead body contains the email');
ok(str_contains($body, '7,5'), 'lead body formats 7.5 with a comma');
ok(!str_contains($body, '7.5'), 'lead body does not use a dot for the score');
ok(str_contains($body, 'one_page'), 'lead body contains the failed id one_page');
ok(str_contains($body, 'docx'), 'lead body contains the failed id docx');
ok(str_contains($body, 'Analista de Dados Sênior'), 'lead body contains the job title');
ok(str_contains($body, $consent), 'lead body contains the consent sentence');
ok(str_contains($body, $when), 'lead body contains the timestamp');
ok(!str_contains($body, 'CURRICULO-SECRETO'), 'lead body does not contain the resume text');

$store = sys_get_temp_dir() . '/avaliar-rate-' . bin2hex(random_bytes(4));
mkdir($store, 0700);
$ip = '203.0.113.10';
$start = 1700000000;
$ten = true;
for ($i = 0; $i < 10; $i++) {
    if (rate_limit_allow($store, $ip, $start) !== true) {
        $ten = false;
    }
}
ok($ten, 'ten calls for one ip inside the hour return true');
ok(rate_limit_allow($store, $ip, $start) === false, 'the 11th call inside the hour returns false');
ok(rate_limit_allow($store, $ip, $start + 3600) === false, 'a call 3600 seconds after the oldest stays blocked');
ok(rate_limit_allow($store, $ip, $start + 3601) === true, 'a call 3601 seconds after the oldest returns true');
ok(rate_limit_allow($store, '203.0.113.11', $start) === true, 'another ip in the same hour is not blocked');

$marker = 'avaliar-outside-' . bin2hex(random_bytes(4));
$outside = dirname($store) . '/' . $marker;
$absolute = sys_get_temp_dir() . '/' . $marker;
foreach (['../' . $marker, 'nested/../../' . $marker, $absolute, '.', '..'] as $evil) {
    ok(rate_limit_allow($store, $evil, $start) === true, 'a path-like ip is allowed once');
}
ok(!file_exists($outside), 'a path-like ip does not write outside the store');
ok(!file_exists($absolute), 'an absolute ip is not used as a path');
ok(!is_dir($store . '/nested'), 'a path-like ip does not create a directory in the store');
$root = realpath($store);
$entries = 0;
foreach (scandir($store) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }
    $entries++;
    $full = $store . '/' . $entry;
    $real = realpath($full);
    ok(is_file($full), 'a rate limit entry is a file');
    ok(is_string($real) && str_starts_with($real, $root . DIRECTORY_SEPARATOR), 'a rate limit file stays inside the store');
}
ok($entries === 7, 'one file per ip inside the store');
foreach (scandir($store) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }
    unlink($store . '/' . $entry);
}
rmdir($store);

function submit_store(): string
{
    $path = sys_get_temp_dir() . '/avaliar-submit-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0700) && !is_dir($path)) {
        throw new RuntimeException('submit store was not created');
    }
    return $path;
}

function submit_count(string $path): int
{
    $count = 0;
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $count++;
    }
    return $count;
}

function submit_remove(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        unlink($path . '/' . $entry);
    }
    rmdir($path);
}

function submit_upload(string $bytes): string
{
    $path = sys_get_temp_dir() . '/avaliar-upload-' . bin2hex(random_bytes(4));
    file_put_contents($path, $bytes);
    return $path;
}

function submit_file(string $name, int $size, string $tmpName): array
{
    return ['name' => $name, 'size' => $size, 'tmp_name' => $tmpName];
}

function submit_post(array $overrides = []): array
{
    global $posting;
    $post = [
        'consent' => '1',
        'email' => 'ana.souza@email.com',
        'posting' => $posting,
    ];
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($post[$key]);
            continue;
        }
        $post[$key] = $value;
    }
    return $post;
}

/** @return array{0:list<string>, 1:mixed} */
function submit_capture(callable $run): array
{
    $warnings = [];
    set_error_handler(function (int $severity, string $message) use (&$warnings): bool {
        $warnings[] = $message;
        return true;
    });
    try {
        $result = $run();
    } finally {
        restore_error_handler();
    }
    return [$warnings, $result];
}

$validFile = submit_file('curriculo.pdf', 12, '/tmp/avaliar-does-not-exist');
$absentConsent = validate_submission(submit_post(['consent' => null]), $validFile);
ok($absentConsent['ok'] === false, 'absent consent is not ok');
ok($absentConsent['error'] === 'unchecked', 'absent consent returns unchecked');

$badEmail = validate_submission(submit_post(['email' => 'não-é-email']), $validFile);
ok($badEmail['ok'] === false, 'não-é-email is not ok');
ok($badEmail['error'] === 'bad_email', 'não-é-email returns bad_email');

$emptyPosting = validate_submission(submit_post(['posting' => '']), $validFile);
ok($emptyPosting['ok'] === false, 'empty posting is not ok');
ok($emptyPosting['error'] === 'empty_posting', 'empty posting returns empty_posting');
$blankPosting = validate_submission(submit_post(['posting' => " \n\t "]), $validFile);
ok($blankPosting['error'] === 'empty_posting', 'a trimmed empty posting returns empty_posting');

$missingName = validate_submission(submit_post(), submit_file('', 12, '/tmp/avaliar-does-not-exist'));
ok($missingName['ok'] === false, 'empty file name is not ok');
ok($missingName['error'] === 'missing_file', 'empty file name returns missing_file');

$tooLarge = validate_submission(submit_post(), submit_file('curriculo.pdf', 5242881, '/tmp/avaliar-does-not-exist'));
ok($tooLarge['ok'] === false, 'size 5242881 is not ok');
ok($tooLarge['error'] === 'too_large', 'size 5242881 returns too_large');

$exactPdf = validate_submission(submit_post(), submit_file('curriculo.pdf', 5242880, '/tmp/avaliar-does-not-exist'));
ok($exactPdf['ok'] === true, 'size 5242880 with a .pdf name passes validation');
ok($exactPdf['error'] === '', 'a valid pdf has an empty error');
$upperPdf = validate_submission(submit_post(), submit_file('Relatorio.PDF', 5242880, '/tmp/avaliar-does-not-exist'));
ok($upperPdf['ok'] === true && $upperPdf['error'] === '', '.PDF is a pdf');
$dotPdf = validate_submission(submit_post(), submit_file('.PDF', 100, '/tmp/avaliar-does-not-exist'));
ok($dotPdf['ok'] === true && $dotPdf['error'] === '', 'a name .PDF passes validation');
$upperDocx = validate_submission(submit_post(), submit_file('curriculo.DOCX', 100, '/tmp/avaliar-does-not-exist'));
ok($upperDocx['ok'] === true && $upperDocx['error'] === '', '.DOCX is a docx');

$badType = validate_submission(submit_post(), submit_file('curriculo.txt', 100, '/tmp/avaliar-does-not-exist'));
ok($badType['ok'] === false, 'curriculo.txt is not ok');
ok($badType['error'] === 'bad_type', 'curriculo.txt returns bad_type');

$firstError = validate_submission(
    ['email' => 'não-é-email', 'posting' => ''],
    submit_file('curriculo.txt', 5242881, '/tmp/avaliar-does-not-exist')
);
ok($firstError['error'] === 'unchecked', 'consent is checked before the other fields');
$emailFirst = validate_submission(
    submit_post(['email' => 'não-é-email', 'posting' => '']),
    submit_file('', 5242881, '/tmp/avaliar-does-not-exist')
);
ok($emailFirst['error'] === 'bad_email', 'email is checked before the posting and the file');
$postingFirst = validate_submission(submit_post(['posting' => '']), submit_file('', 5242881, '/tmp/avaliar-does-not-exist'));
ok($postingFirst['error'] === 'empty_posting', 'posting is checked before the file');
$sizeFirst = validate_submission(submit_post(), submit_file('curriculo.txt', 5242881, '/tmp/avaliar-does-not-exist'));
ok($sizeFirst['error'] === 'too_large', 'size is checked before the extension');

$submitConsent = 'Andrey Costa guarda o e-mail para responder esta avaliação e apaga a mensagem quando a pessoa responde para andreynacosta46@gmail.com pedindo o apagamento.';
$postingLine = 'Power métricas produto experimentos pipelines';
$guardStore = submit_store();
$guardCalls = 0;
$guardMail = 0;
$guardExtractor = function (string $path, string $extension) use (&$guardCalls): array {
    $guardCalls++;
    return ['text' => '', 'pages' => 0, 'linear' => false, 'readable' => false, 'valid_docx' => false];
};
$guardMailer = function (string $to, string $subject, string $body) use (&$guardMail): bool {
    $guardMail++;
    return true;
};
$missingUpload = sys_get_temp_dir() . '/avaliar-missing-' . bin2hex(random_bytes(4));
[$guardWarnings, $uncheckedHandle] = submit_capture(function () use ($posting, $missingUpload, $guardStore, $guardMailer, $guardExtractor): array {
    return handle_submission(
        ['email' => 'ana.souza@email.com', 'posting' => $posting],
        submit_file('curriculo.pdf', 12, $missingUpload),
        [
            'ip' => '198.51.100.10',
            'now' => 1700003000,
            'store' => $guardStore,
            'mailer' => $guardMailer,
            'extractor' => $guardExtractor,
        ]
    );
});
ok($uncheckedHandle['ok'] === false, 'absent consent handle is not ok');
ok($uncheckedHandle['error'] === 'unchecked', 'absent consent handle returns unchecked');
ok($uncheckedHandle['score'] === null, 'absent consent handle has no score');
ok($uncheckedHandle['mail_failed'] === false, 'absent consent handle does not fail mail');
ok($guardCalls === 0, 'absent consent leaves the extractor counter at 0');
ok($guardMail === 0, 'absent consent does not mail');
ok($guardWarnings === [], 'absent consent does not require the temp file to have existed');
ok(!is_file($missingUpload), 'absent consent does not create the missing upload');
ok(submit_count($guardStore) === 0, 'absent consent does not record a rate limit');
submit_remove($guardStore);

$limitStore = submit_store();
$limitUpload = submit_upload("BYTES-DO-ARQUIVO\n");
$limitCalls = 0;
$limitMail = 0;
$limitCopies = [];
$limitExtractor = function (string $path, string $extension) use (&$limitCalls, &$limitCopies, $limitUpload): array {
    $limitCalls++;
    $limitCopies[] = $path;
    ok(is_file($path), 'unreadable handle copies the upload before reading');
    ok($path !== $limitUpload, 'unreadable handle does not read tmp_name in place');
    return ['text' => '', 'pages' => 0, 'linear' => false, 'readable' => false, 'valid_docx' => false];
};
$limitMailer = function (string $to, string $subject, string $body) use (&$limitMail): bool {
    $limitMail++;
    return true;
};
$limitDeps = [
    'ip' => '198.51.100.20',
    'now' => 1700004000,
    'store' => $limitStore,
    'mailer' => $limitMailer,
    'extractor' => $limitExtractor,
];
$limitFirst = null;
for ($i = 0; $i < 10; $i++) {
    $limitResult = handle_submission(submit_post(), submit_file('curriculo.pdf', 18, $limitUpload), $limitDeps);
    if ($i === 0) {
        $limitFirst = $limitResult;
    }
}
$limitMissing = $limitUpload . '.absent';
[$limitWarnings, $limitBlocked] = submit_capture(function () use ($limitMissing, $limitDeps): array {
    return handle_submission(submit_post(), submit_file('curriculo.pdf', 18, $limitMissing), $limitDeps);
});
ok(is_array($limitFirst) && $limitFirst['ok'] === false, 'an unreadable extraction is not ok');
ok(is_array($limitFirst) && $limitFirst['error'] === 'unreadable', 'readable false returns unreadable');
ok(is_array($limitFirst) && $limitFirst['score'] === null, 'readable false does not score');
ok(is_array($limitFirst) && $limitFirst['mail_failed'] === false, 'readable false does not fail mail');
ok($limitCalls === 10, 'eleven handles call the extractor ten times');
ok($limitMail === 0, 'unreadable and rate limited handles do not mail');
ok(is_array($limitBlocked) && $limitBlocked['ok'] === false, 'the 11th handle is not ok');
ok(is_array($limitBlocked) && $limitBlocked['error'] === 'rate_limited', 'the 11th handle returns rate_limited');
ok(is_array($limitBlocked) && $limitBlocked['score'] === null, 'the 11th handle has no score');
ok(is_array($limitBlocked) && $limitBlocked['mail_failed'] === false, 'the 11th handle does not fail mail');
ok($limitWarnings === [], 'the 11th handle does not copy a missing upload');
$limitLeft = false;
foreach ($limitCopies as $limitCopy) {
    clearstatcache(true, $limitCopy);
    if (is_file($limitCopy)) {
        $limitLeft = true;
    }
}
ok($limitLeft === false, 'unreadable handles delete the temp copy');
ok(count($limitCopies) === 10, 'the 11th handle does not create a temp copy the extractor can see');
ok(is_file($limitUpload), 'rate limited handles do not delete tmp_name');
ok(file_get_contents($limitUpload) === "BYTES-DO-ARQUIVO\n", 'rate limited handles do not change tmp_name');
unlink($limitUpload);
submit_remove($limitStore);

// The rate-limit loop above stores a filesystem path in $full.
$resume = "Ana Souza\nana@email.com\nObjetivo\nAnalista de Dados Sênior\nEspecialista com anos de experiência.\n• Liderei o painel e reduzi o tempo em 30%.\nTrabalhei com power, métricas, produto e experimentos no corpo.\n";
$secret = "CURRICULO-SECRETO\n" . $resume;
$uploadBytes = "BYTES-DO-ARQUIVO\n";
$sentUpload = submit_upload($uploadBytes);
$sentStore = submit_store();
$sentCopy = '';
$sentExtension = '';
$sentMail = null;
$sentNow = 1700005000;
$sentExtractor = function (string $path, string $extension) use (&$sentCopy, &$sentExtension, $secret, $uploadBytes): array {
    $sentCopy = $path;
    $sentExtension = $extension;
    $root = realpath(sys_get_temp_dir());
    $real = realpath($path);
    ok(is_string($root) && is_string($real) && str_starts_with($real, $root . DIRECTORY_SEPARATOR), 'temp copy is inside the system temp dir');
    ok(is_file($path), 'temp copy exists while extracting');
    ok(file_get_contents($path) === $uploadBytes, 'temp copy has the upload bytes');
    return [
        'text' => $secret,
        'pages' => 1,
        'linear' => true,
        'readable' => true,
        'valid_docx' => true,
    ];
};
$sentMailer = function (string $to, string $subject, string $body) use (&$sentMail): bool {
    $sentMail = [$to, $subject, $body];
    return true;
};
$sent = handle_submission(
    submit_post(),
    submit_file('curriculo.docx', strlen($uploadBytes), $sentUpload),
    [
        'ip' => '198.51.100.30',
        'now' => $sentNow,
        'store' => $sentStore,
        'mailer' => $sentMailer,
        'extractor' => $sentExtractor,
    ]
);
ok($sent['ok'] === true, 'a sent lead is ok');
ok($sent['error'] === '', 'a sent lead has no error');
ok($sent['mail_failed'] === false, 'mail_failed is false when the mailer returns true');
ok(is_array($sent['score']) && array_key_exists('points', $sent['score']), 'score points is set');
ok(is_array($sent['score']) && $sent['score']['points'] === 10.0, 'full resume fields score 10.0');
ok(is_array($sent['score']) && $sent['score']['title'] === 'Analista de Dados Sênior', 'score title is the posting title');
ok(is_array($sent['score']) && !array_key_exists('text', $sent['score']), 'the score does not include the resume text');
ok($sentExtension === 'docx', 'extractor receives the docx extension');
ok(is_array($sentMail), 'the mailer is called on success');
$sentBody = is_array($sentMail) ? $sentMail[2] : '';
$sentSubject = is_array($sentMail) ? $sentMail[1] : '';
ok(is_array($sentMail) && $sentMail[0] === 'andreynacosta46@gmail.com', 'mail goes to andreynacosta46@gmail.com');
ok($sentSubject === 'Avaliação de currículo ana.souza@email.com', 'subject is Avaliação de currículo plus the visitor email');
ok(!str_contains($sentSubject, 'CURRICULO-SECRETO'), 'subject does not contain the resume');
ok(!str_contains($sentSubject, $postingLine), 'subject does not contain the posting');
ok(!str_contains($sentBody, 'CURRICULO-SECRETO'), 'mail body does not contain CURRICULO-SECRETO');
ok(!str_contains($sentBody, $postingLine), 'mail body does not contain the posting');
ok(!str_contains($sentBody, 'BYTES-DO-ARQUIVO'), 'mail body does not contain the upload bytes');
ok(!str_contains($sentSubject, $sentCopy) && !str_contains($sentBody, $sentCopy), 'mail does not contain the temp path');
$sentExpected = lead_body(
    'ana.souza@email.com',
    10.0,
    [],
    'Analista de Dados Sênior',
    $submitConsent,
    date('Y-m-d H:i:s', $sentNow)
);
ok($sentBody === $sentExpected, 'mail body is the lead for the full resume');
clearstatcache(true, $sentCopy);
ok($sentCopy !== '' && !is_file($sentCopy), 'the temp copy is gone after success');
ok(is_file($sentUpload), 'success does not delete tmp_name');
ok(file_get_contents($sentUpload) === $uploadBytes, 'success does not change tmp_name');
ok(!str_contains(basename($sentCopy), 'curriculo'), 'the temp copy does not use the upload name');
unlink($sentUpload);
submit_remove($sentStore);

$failUpload = submit_upload($uploadBytes);
$failStore = submit_store();
$failCopy = '';
$failExtension = '';
$failMail = null;
$failNow = 1700006000;
$failExtractor = function (string $path, string $extension) use (&$failCopy, &$failExtension, $secret): array {
    $failCopy = $path;
    $failExtension = $extension;
    return [
        'text' => $secret,
        'pages' => 1,
        'linear' => false,
        'readable' => true,
        'valid_docx' => false,
    ];
};
$failMailer = function (string $to, string $subject, string $body) use (&$failMail): bool {
    $failMail = [$to, $subject, $body];
    return false;
};
$failedMail = handle_submission(
    submit_post(),
    submit_file('Relatorio.PDF', strlen($uploadBytes), $failUpload),
    [
        'ip' => '198.51.100.31',
        'now' => $failNow,
        'store' => $failStore,
        'mailer' => $failMailer,
        'extractor' => $failExtractor,
    ]
);
ok($failedMail['ok'] === true, 'a failed mailer still returns ok');
ok($failedMail['error'] === '', 'a failed mailer has no error id');
ok($failedMail['mail_failed'] === true, 'mail_failed is true when the mailer returns false');
ok(is_array($failedMail['score']) && array_key_exists('points', $failedMail['score']), 'a failed mailer still returns the score');
ok(is_array($failedMail['score']) && $failedMail['score']['points'] === 7.5, 'pdf without linear text scores 7.5');
ok($failExtension === 'pdf', 'extractor receives pdf for a .PDF name');
ok(is_array($failMail), 'the mailer is still called when it will fail');
$failBody = is_array($failMail) ? $failMail[2] : '';
$failSubject = is_array($failMail) ? $failMail[1] : '';
ok(is_array($failMail) && $failMail[0] === 'andreynacosta46@gmail.com', 'a failed mail was addressed to andreynacosta46@gmail.com');
ok($failSubject === 'Avaliação de currículo ana.souza@email.com', 'a failed mail keeps the subject');
ok(!str_contains($failBody, 'CURRICULO-SECRETO'), 'a failed mail body does not contain CURRICULO-SECRETO');
ok(!str_contains($failSubject, 'CURRICULO-SECRETO') && !str_contains($failSubject, $postingLine), 'a failed mail subject has no resume or posting');
ok(!str_contains($failBody, $postingLine), 'a failed mail body does not contain the posting');
$failExpected = lead_body(
    'ana.souza@email.com',
    7.5,
    ['linear', 'docx'],
    'Analista de Dados Sênior',
    $submitConsent,
    date('Y-m-d H:i:s', $failNow)
);
ok($failBody === $failExpected, 'failed mail body lists linear then docx');
clearstatcache(true, $failCopy);
ok($failCopy !== '' && !is_file($failCopy), 'the temp copy is gone when mail fails');
ok(is_file($failUpload), 'a failed mailer does not delete tmp_name');
ok(file_get_contents($failUpload) === $uploadBytes, 'a failed mailer does not change tmp_name');
unlink($failUpload);
submit_remove($failStore);

$throwUpload = submit_upload($uploadBytes);
$throwStore = submit_store();
$throwCopy = '';
$throwMail = 0;
$throwExtractor = function (string $path, string $extension) use (&$throwCopy, $throwUpload): array {
    $throwCopy = $path;
    ok(is_file($path), 'temp copy exists before the extractor throws');
    ok($path !== $throwUpload, 'a thrown extractor was given a copy');
    throw new RuntimeException('CURRICULO-SECRETO');
};
$throwMailer = function (string $to, string $subject, string $body) use (&$throwMail): bool {
    $throwMail++;
    return true;
};
$thrown = handle_submission(
    submit_post(),
    submit_file('curriculo.pdf', strlen($uploadBytes), $throwUpload),
    [
        'ip' => '198.51.100.32',
        'now' => 1700007000,
        'store' => $throwStore,
        'mailer' => $throwMailer,
        'extractor' => $throwExtractor,
    ]
);
ok($thrown['ok'] === false, 'a thrown extractor is not ok');
ok($thrown['error'] === 'unreadable', 'a thrown extractor returns unreadable');
ok($thrown['score'] === null, 'a thrown extractor does not score');
ok($thrown['mail_failed'] === false, 'a thrown extractor does not fail mail');
ok($throwMail === 0, 'a thrown extractor does not mail');
ok(!str_contains((string) json_encode($thrown), 'CURRICULO-SECRETO'), 'a thrown extractor does not leak the resume');
clearstatcache(true, $throwCopy);
ok($throwCopy !== '' && !is_file($throwCopy), 'the temp copy is gone after the extractor throws');
ok(is_file($throwUpload), 'a thrown extractor does not delete tmp_name');
ok(file_get_contents($throwUpload) === $uploadBytes, 'a thrown extractor does not change tmp_name');
unlink($throwUpload);
submit_remove($throwStore);

function page_bubble_on(string $html, string $place, string $label): bool
{
    $pattern = '/data-place="' . preg_quote($place, '/') . '"(.*?)<\/div>/s';
    if (preg_match($pattern, $html, $block) !== 1) {
        return false;
    }
    $cell = '/<i class="on">' . preg_quote($label, '/') . '<\/i>/';
    return preg_match($cell, $block[1]) === 1;
}

function page_check_row(string $html, string $id): string
{
    $pattern = '/<p class="[^"]*" data-check="' . preg_quote($id, '/') . '">.*?<\/p>/s';
    if (preg_match($pattern, $html, $row) !== 1) {
        return '';
    }
    return $row[0];
}

$emptyState = [
    'error' => '',
    'score' => null,
    'mail_failed' => false,
    'values' => ['email' => '', 'posting' => ''],
];
$emptyPage = render_page($emptyState);
ok(str_contains($emptyPage, 'Copie o anúncio e cole aqui.'), 'empty page has the Portuguese posting hint');
ok(str_contains($emptyPage, 'Copy the posting and paste it here.'), 'empty page has the English posting hint');
ok(str_contains($emptyPage, 'A nota aparece depois do envio.'), 'empty page says the score appears after submit');
ok(preg_match('/<button[^>]*>.*?Avaliar.*?<\/button>/s', $emptyPage) === 1, 'the button says Avaliar');
ok(!str_contains($emptyPage, 'seed:'), 'empty page does not contain seed:');
ok(!str_contains($emptyPage, 'Direction contract'), 'empty page does not contain Direction contract');
$checkboxFound = preg_match('/<input\b[^>]*type="checkbox"[^>]*>/', $emptyPage, $checkbox);
ok($checkboxFound === 1, 'empty page has a checkbox');
ok($checkboxFound === 1 && !str_contains($checkbox[0], 'checked'), 'checkbox markup does not contain checked');
ok(!str_contains($emptyPage, 'class="on"'), 'empty page has no filled optical cell');
ok(str_contains($emptyPage, 'não simula'), 'empty page says the score não simula');
ok(str_contains($emptyPage, 'Andrey Costa'), 'empty page names Andrey Costa');
ok(str_contains($emptyPage, 'andreynacosta46@gmail.com'), 'empty page names andreynacosta46@gmail.com');
ok(str_contains($emptyPage, 'lang="pt"') && str_contains($emptyPage, 'lang="en"'), 'empty page contains both languages');
ok(str_contains($emptyPage, 'data-set-lang="pt"') && str_contains($emptyPage, 'data-set-lang="en"'), 'empty page has a language control');
ok(str_contains($emptyPage, 'name="resume"'), 'the file input is named resume');
$consentSentence = 'Andrey Costa guarda o e-mail para responder esta avaliação e apaga a mensagem quando a pessoa responde para andreynacosta46@gmail.com pedindo o apagamento.';
ok(str_contains($emptyPage, $consentSentence), 'the checkbox shows the consent sentence');

$twoPage = $base;
$twoPage['extension'] = 'pdf';
$twoPage['valid_docx'] = false;
$twoPage['pages'] = 2;
$score75 = score_resume($twoPage);
$page75 = render_page([
    'error' => '',
    'score' => $score75,
    'mail_failed' => false,
    'values' => ['email' => '', 'posting' => ''],
]);
ok(str_contains($page75, '7,5'), 'a 7.5 result shows 7,5');
ok(str_contains($page75, '<div class="num">7,5</div>'), 'the large number is 7,5');
ok(page_bubble_on($page75, 'integer', '7'), '7.5 fills the integer bubble 7');
ok(page_bubble_on($page75, 'tenth', '5'), '7.5 fills the tenth bubble 5');
ok(!page_bubble_on($page75, 'integer', '10'), '7.5 does not fill the extra 10 bubble');
$onePageRow = page_check_row($page75, 'one_page');
ok($onePageRow !== '' && preg_match('/class="[^"]*\bfill\b/', $onePageRow) === 1, 'failed one_page row includes fill');
$linearRow = page_check_row($page75, 'linear');
ok($linearRow !== '' && !str_contains($linearRow, 'fill'), 'passed linear row does not include fill');
ok(str_contains($page75, 'não simula'), 'result page says the score não simula');
ok(str_contains($page75, 'Andrey Costa'), 'result page names Andrey Costa');
ok(str_contains($page75, 'andreynacosta46@gmail.com'), 'result page names andreynacosta46@gmail.com');

$page10 = render_page([
    'error' => '',
    'score' => $perfect,
    'mail_failed' => false,
    'values' => ['email' => '', 'posting' => ''],
]);
ok(str_contains($page10, '10,0'), 'a 10.0 result shows 10,0');
ok(str_contains($page10, '<div class="num">10,0</div>'), 'the large number is 10,0');
ok(page_bubble_on($page10, 'integer', '10'), '10.0 fills the bubble 10');
for ($digit = 0; $digit <= 9; $digit++) {
    ok(!page_bubble_on($page10, 'integer', (string) $digit), "10.0 leaves integer bubble $digit empty");
}
ok(page_bubble_on($page10, 'tenth', '0'), '10.0 fills the tenth bubble 0');
foreach (array_column($perfect['checks'], 'id') as $passedId) {
    $passedRow = page_check_row($page10, $passedId);
    ok($passedRow !== '' && !str_contains($passedRow, 'fill'), "passed $passedId row does not include fill");
}

$mailPage = render_page([
    'error' => '',
    'score' => $perfect,
    'mail_failed' => true,
    'values' => ['email' => '', 'posting' => ''],
]);
ok(str_contains($mailPage, 'O contato não foi enviado.'), 'mail failure says the contact was not sent');
ok(str_contains($mailPage, 'The contact was not sent.'), 'mail failure has the English sentence');
ok(!str_contains($emptyPage, 'O contato não foi enviado.'), 'empty page does not say the contact failed');

$kept = render_page([
    'error' => 'bad_email',
    'score' => null,
    'mail_failed' => false,
    'values' => ['email' => 'ana@email.com', 'posting' => "Analista\nPower"],
]);
ok(str_contains($kept, 'value="ana@email.com"'), 'a failed post keeps the email');
ok(str_contains($kept, "Analista\nPower"), 'a failed post keeps the posting');
$escaped = render_page([
    'error' => 'bad_email',
    'score' => null,
    'mail_failed' => false,
    'values' => ['email' => 'a"<b>@x.com', 'posting' => '</textarea><script>alert(1)</script>'],
]);
ok(str_contains($escaped, 'value="a&quot;&lt;b&gt;@x.com"'), 'the email value is escaped');
ok(!str_contains($escaped, '</textarea><script>'), 'the posting cannot break out of the textarea');
ok(str_contains($escaped, '&lt;/textarea&gt;&lt;script&gt;'), 'the posting text stays visible as text');

require_once __DIR__ . '/copy.php';
$copyIds = ['unchecked', 'bad_email', 'empty_posting', 'missing_file', 'too_large', 'bad_type', 'rate_limited', 'unreadable'];
$copy = page_copy();
$copySrc = file_get_contents(__DIR__ . '/copy.php');
ok(is_string($copySrc), 'copy.php can be read');
foreach ($copyIds as $copyId) {
    $pt = $copy[$copyId]['pt'] ?? '';
    $en = $copy[$copyId]['en'] ?? '';
    ok(is_string($copySrc) && str_contains($copySrc, $copyId), "$copyId is named in copy.php");
    ok(is_string($pt) && trim($pt) !== '' && str_ends_with($pt, '.'), "$copyId has a Portuguese sentence");
    ok(is_string($en) && trim($en) !== '' && str_ends_with($en, '.'), "$copyId has an English sentence");
    $errorPage = render_page([
        'error' => $copyId,
        'score' => null,
        'mail_failed' => false,
        'values' => ['email' => '', 'posting' => ''],
    ]);
    ok(str_contains($errorPage, $pt), "page shows the Portuguese sentence for $copyId");
    ok(str_contains($errorPage, $en), "page shows the English sentence for $copyId");
}

$keptState = page_result_state(
    ['email' => 'ana@email.com', 'posting' => 'Analista'],
    ['ok' => false, 'error' => 'missing_file', 'score' => null, 'mail_failed' => false]
);
ok($keptState['values']['email'] === 'ana@email.com', 'validation failure keeps the posted email');
ok($keptState['values']['posting'] === 'Analista', 'validation failure keeps the posted posting');
ok($keptState['error'] === 'missing_file', 'validation failure keeps the error id');
ok($keptState['score'] === null, 'validation failure has no score');
$sentState = page_result_state(
    ['email' => 'ana@email.com', 'posting' => 'Analista'],
    ['ok' => true, 'error' => '', 'score' => $perfect, 'mail_failed' => true]
);
ok($sentState['mail_failed'] === true, 'a failed mail stays on the page state');
ok(is_array($sentState['score']) && $sentState['score']['points'] === 10.0, 'a failed mail still carries the score');
ok($sentState['values']['email'] === '' && $sentState['values']['posting'] === '', 'a successful score does not refill the form');
ok(page_empty_state()['score'] === null && page_empty_state()['error'] === '', 'GET uses the empty state');

$indexSrc = file_get_contents(__DIR__ . '/index.php');
ok(is_string($indexSrc) && str_contains($indexSrc, '$_FILES[\'resume\']'), 'index passes $_FILES resume');
ok(is_string($indexSrc) && str_contains($indexSrc, 'handle_submission'), 'index calls handle_submission');
ok(is_string($indexSrc) && str_contains($indexSrc, 'extract_file(...)'), 'index uses the real extract_file');
require_once __DIR__ . '/index.php';
$plainSubject = 'Avaliação de currículo ana.souza@email.com';
ok(page_mail_subject($plainSubject) === '=?UTF-8?B?' . base64_encode($plainSubject) . '?=', 'mail subject is an RFC 2047 UTF-8 encoded-word');
ok(page_mail_subject($plainSubject) !== $plainSubject, 'mail subject is not the plain subject');
$mailHeaders = page_mail_headers();
ok(str_contains($mailHeaders, 'MIME-Version: 1.0'), 'mail headers include MIME-Version');
ok(str_contains($mailHeaders, 'Content-Type: text/plain; charset=UTF-8'), 'mail headers set a UTF-8 content type');
ok(is_string($indexSrc) && preg_match(
    '/function page_mail\(string \$to, string \$subject, string \$body\): bool\s*\{\s*return mail\(\$to, page_mail_subject\(\$subject\), \$body, page_mail_headers\(\)\);\s*\}/',
    $indexSrc
) === 1, 'page_mail returns the real mail call');
ok(is_string($indexSrc) && str_contains($indexSrc, "'mailer' => page_mail(...)"), 'index passes page_mail to handle_submission');
ok(is_string($indexSrc) && str_contains($indexSrc, '/storage'), 'index stores rate limits under storage');

$css = file_get_contents(__DIR__ . '/style.css');
ok(is_string($css) && str_contains($css, '#F7F7F5'), 'the sheet color is in the stylesheet');
ok(is_string($css) && str_contains($css, '#141414'), 'the ink color is in the stylesheet');
ok(is_string($css) && str_contains($css, '#C81E1E'), 'the band color is in the stylesheet');
ok(is_string($css) && str_contains($css, '2px solid #141414'), 'panels use a 2px black border');
ok(is_string($css) && str_contains($css, 'Barlow Condensed'), 'the score number uses Barlow Condensed');
ok(is_string($css) && str_contains($css, '800px'), 'the columns change at 800px');
ok(is_string($css) && !str_contains($css, 'width: 1440px') && !str_contains($css, 'width:1440px'), 'the page is not locked to the mock width');
ok(is_string($css) && str_contains($css, '[lang="pt"]') && str_contains($css, '[lang="en"]'), 'the stylesheet switches the two languages');
ok(str_contains($emptyPage, 'fonts.googleapis.com'), 'the page loads Google Fonts');
ok(str_contains($emptyPage, 'Barlow+Condensed') && str_contains($emptyPage, 'family=Barlow'), 'the page loads Barlow and Barlow Condensed');
ok(str_contains($emptyPage, '/avaliar/style.css'), 'the page loads its own stylesheet');
ok(str_contains($emptyPage, '/assets/i18n.js'), 'the language control uses the site script');

$deny = file_get_contents(__DIR__ . '/storage/.htaccess');
ok(is_string($deny) && str_contains($deny, 'Require all denied'), 'storage denies all access');

$gate = file_get_contents(__DIR__ . '/.htaccess');
ok(is_string($gate) && str_contains($gate, 'DirectoryIndex index.php'), 'the directory index is index.php');
ok(is_string($gate) && str_contains($gate, 'Options -Indexes'), 'directory listing is off');
ok(is_string($gate) && preg_match('/<FilesMatch\b[^>]*>\s*Require all denied\s*<\/FilesMatch>/s', $gate) === 1, 'internal php uses Require all denied');
foreach (['tests', 'score', 'extract', 'lead', 'submit', 'copy', 'page'] as $hidden) {
    ok(is_string($gate) && str_contains($gate, $hidden), "$hidden is listed in the deny rule");
}
ok(is_string($gate) && !preg_match('/<FilesMatch\b[^>]*index\.php/', $gate), 'index.php is not denied');
ok(is_string($gate) && !str_contains($gate, 'style.css'), 'style.css is not denied');
ok(is_string($gate) && !str_contains($gate, 'storage'), 'storage is not denied from the parent htaccess');

$pageSrc = file_get_contents(__DIR__ . '/page.php');
ok(is_string($pageSrc) && !str_contains($pageSrc, 'score_resume'), 'the page does not rescore');
ok(is_string($indexSrc) && !str_contains($indexSrc, 'score_resume'), 'index does not rescore');

if ($failures > 0) {
    exit(1);
}
echo "OK $count\n";
