<?php
declare(strict_types=1);

/*
 * Synthetic lab reports with known values (RNF-10): accuracy is measured only on these,
 * never on hand-labelled real reports. Each report is a real text-layer PDF.
 */

/** Minimal one-font PDF writer (Helvetica, WinAnsi). @param list<list<string>> $pages lines per page */
function tiny_pdf(array $pages): string
{
    $objs = [];
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $kids = [];
    $n = 4;
    foreach ($pages as $lines) {
        $stream = "BT /F1 9 Tf 40 800 Td 12 TL\n";
        foreach ($lines as $line) {
            $enc = iconv('UTF-8', 'CP1252//TRANSLIT', $line);
            $stream .= '(' . strtr((string) $enc, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']) . ") '\n";
        }
        $stream .= 'ET';
        $pageObj = $n++;
        $contentObj = $n++;
        $objs[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObj . ' 0 R >>';
        $objs[$contentObj] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        $kids[] = $pageObj . ' 0 R';
    }
    $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
    ksort($objs);
    $out = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objs as $i => $body) {
        $offsets[$i] = strlen($out);
        $out .= "$i 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($out);
    $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $o) {
        $out .= sprintf("%010d 00000 n \n", $o);
    }
    return $out . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}

/** Test catalogue: name, section, unit, range text, numeric range, LOINC, property, typical value. */
function synthetic_catalogue(): array
{
    return [
        ['TSH - Hormônio Tireoestimulante', 'Hormônios', 'µUI/mL', '0,45 a 4,50', [0.45, 4.5], '3016-3', 'ACnc', 2.1],
        ['T4 Livre', 'Hormônios', 'ng/dL', '0,70 a 1,80', [0.7, 1.8], '3024-7', 'MCnc', 1.2],
        ['Hemoglobina Glicada (HbA1c)', 'Bioquímica', '%', 'Normal: inferior a 5,7', [null, 5.7], '4548-4', 'MFr', 5.4],
        ['Glicose - jejum', 'Bioquímica', 'mg/dL', '70 a 99', [70, 99], '1558-6', 'MCnc', 92],
        ['Creatinina', 'Bioquímica', 'mg/dL', 'Mulheres: 0,50 a 0,90 / Homens: 0,70 a 1,20', null, '2160-0', 'MCnc', 0.84],
        ['Ferritina', 'Hematologia', 'ng/mL', '15 a 150', [15, 150], '2276-4', 'MCnc', 88],
        ['25-Hidroxivitamina D', 'Vitaminas', 'ng/mL', 'Acima de 20 (população saudável)', [20, null], '1989-3', 'MCnc', 31.5],
        ['Colesterol Total', 'Lipídios', 'mg/dL', 'Desejável: inferior a 190', [null, 190], '2093-3', 'MCnc', 182],
        ['Triglicerídeos', 'Lipídios', 'mg/dL', 'Desejável: inferior a 150', [null, 150], '2571-8', 'MCnc', 110],
        ['TGO (AST)', 'Bioquímica', 'U/L', 'até 32', [null, 32], '1920-8', 'CCnc', 21],
        ['Vitamina B12', 'Vitaminas', 'pg/mL', '197 a 771', [197, 771], '2132-9', 'MCnc', 410],
        ['Insulina - jejum', 'Hormônios', 'µUI/mL', '2,6 a 24,9', [2.6, 24.9], '20448-7', 'ACnc', 8.3],
    ];
}

function br(float $v, int $dec): string
{
    return number_format($v, $dec, ',', '.');
}

/**
 * @return array{pdf:string,truth:list<array>,lab:string,date:string,fixture:array}
 * fixture is what a perfect reader would return (used by tests' fake provider).
 */
function synthetic_report(int $seed): array
{
    mt_srand($seed);
    $labs = ['Laboratório Sintético Alfa', 'Diagnósticos Beta Ltda', 'Centro de Análises Gama'];
    $lab = $labs[$seed % count($labs)];
    $date = gmdate('Y-m-d', strtotime('2026-01-05') + ($seed % 200) * 86400);
    $prevDate = gmdate('Y-m-d', strtotime($date) - 120 * 86400);
    $lines = [$lab, 'Paciente: Fulano de Tal Sintético    CPF: 123.456.789-09', 'Médico solicitante: Dr. Exemplo   CRM-PI 12345',
        'Data da coleta: ' . br_date($date) . '    Liberação: ' . br_date(gmdate('Y-m-d', strtotime($date) + 86400)), ''];
    $truth = [];
    $results = [];
    $cat = synthetic_catalogue();
    $section = '';
    foreach ($cat as $i => [$name, $sec, $unit, $range, $num, $loinc, $prop, $typical]) {
        if ($sec !== $section) {
            $lines[] = '';
            $lines[] = mb_strtoupper($sec);
            $section = $sec;
        }
        $dec = $typical < 10 ? 2 : ($typical < 100 ? 1 : 0);
        $v = round($typical * (0.8 + mt_rand(0, 40) / 100), $dec);
        $prev = round($typical * (0.8 + mt_rand(0, 40) / 100), $dec);
        $printed = br($v, $dec);
        $line = sprintf('%-34s %10s %-8s  Ref.: %s', $name, $printed, $unit, $range);
        // Previous result column on some lines.
        $withPrev = $i % 3 === 0;
        if ($withPrev) {
            $line .= '   Anterior (' . br_date($prevDate) . '): ' . br($prev, $dec);
        }
        $lines[] = $line;
        $page = 1;
        $ranges = $num ? [['kind' => $num[0] !== null && $num[1] !== null ? 'interval' : ($num[1] !== null ? 'upper' : 'lower'), 'condition' => null, 'label' => null, 'low' => $num[0], 'high' => $num[1]]] : [];
        $base = ['name_printed' => $name, 'section' => $sec, 'operator' => '', 'value_text' => null, 'unit_printed' => $unit, 'reference_printed' => $range, 'reference_ranges' => $ranges,
            'flag_printed' => null, 'method' => null, 'specimen' => 'Soro', 'page' => $page, 'loinc_code' => $loinc, 'loinc_property' => $prop, 'loinc_confidence' => 0.9];
        $results[] = $base + ['value_printed' => $printed, 'value_number' => $v, 'column' => 'current', 'result_date' => null, 'quote' => $line];
        $truth[] = ['name' => $name, 'operator' => '', 'value' => $v, 'text' => null, 'unit' => $unit, 'date' => $date];
        if ($withPrev) {
            $results[] = $base + ['value_printed' => br($prev, $dec), 'value_number' => $prev, 'column' => 'previous', 'result_date' => $prevDate, 'quote' => $line];
            $truth[] = ['name' => $name, 'operator' => '', 'value' => $prev, 'text' => null, 'unit' => $unit, 'date' => $prevDate];
        }
    }
    // Operator and non-numeric results.
    $lines[] = '';
    $lines[] = 'IMUNOLOGIA';
    $l1 = sprintf('%-34s %10s %-8s  Ref.: inferior a 0,10', 'Anticorpos anti-TPO (índice)', '< 0,01', 'UI/mL');
    $l2 = sprintf('%-34s %10s', 'Anti-HIV 1 e 2', 'Não reagente');
    $lines[] = $l1;
    $lines[] = $l2;
    $results[] = ['name_printed' => 'Anticorpos anti-TPO (índice)', 'section' => 'Imunologia', 'value_printed' => '< 0,01', 'operator' => '<', 'value_number' => 0.01, 'value_text' => null, 'unit_printed' => 'UI/mL',
        'reference_printed' => 'inferior a 0,10', 'reference_ranges' => [['kind' => 'upper', 'condition' => null, 'label' => null, 'low' => null, 'high' => 0.1]], 'flag_printed' => null, 'method' => null, 'specimen' => 'Soro',
        'column' => 'current', 'result_date' => null, 'page' => 1, 'quote' => $l1, 'loinc_code' => '8099-4', 'loinc_property' => 'ACnc', 'loinc_confidence' => 0.6];
    $results[] = ['name_printed' => 'Anti-HIV 1 e 2', 'section' => 'Imunologia', 'value_printed' => 'Não reagente', 'operator' => '', 'value_number' => null, 'value_text' => 'Não reagente', 'unit_printed' => null,
        'reference_printed' => null, 'reference_ranges' => [], 'flag_printed' => null, 'method' => null, 'specimen' => 'Soro',
        'column' => 'current', 'result_date' => null, 'page' => 1, 'quote' => $l2, 'loinc_code' => '7918-6', 'loinc_property' => 'ACnc', 'loinc_confidence' => 0.6];
    $truth[] = ['name' => 'Anticorpos anti-TPO (índice)', 'operator' => '<', 'value' => 0.01, 'text' => null, 'unit' => 'UI/mL', 'date' => $date];
    $truth[] = ['name' => 'Anti-HIV 1 e 2', 'operator' => '', 'value' => null, 'text' => 'Não reagente', 'unit' => null, 'date' => $date];

    $fixture = ['doc_type' => 'lab_report', 'laboratory' => $lab, 'collection_date' => $date, 'results' => $results, 'body_composition' => null, 'prescription_items' => []];
    return ['pdf' => tiny_pdf([$lines]), 'truth' => $truth, 'lab' => $lab, 'date' => $date, 'fixture' => $fixture];
}

/** Scores loaded results of one user against the truth of the reports they came from. */
function score_against_truth(int $userId, array $truthByDoc): array
{
    $total = 0;
    $loaded = 0;
    $correct = 0;
    foreach ($truthByDoc as $docId => $truth) {
        $total += count($truth);
        $got = [];
        foreach (rows("SELECT observed_on, payload_enc FROM results WHERE document_id = ? AND status = 'loaded'", [$docId]) as $r) {
            $p = dec($userId, $r['payload_enc']);
            $got[] = ['name' => norm_text($p['name_printed']), 'date' => $r['observed_on'], 'p' => $p];
        }
        $loaded += count($got);
        foreach ($truth as $t) {
            foreach ($got as $i => $g) {
                if ($g['name'] === norm_text($t['name']) && $g['date'] === $t['date']) {
                    $p = $g['p'];
                    $ok = ($p['operator'] ?? '') === $t['operator'] && norm_unit($p['unit_printed']) === norm_unit($t['unit'])
                        && ($t['value'] === null ? norm_text($p['value_text'] ?? $p['value_printed']) === norm_text($t['text']) : abs((float) $p['value_number'] - $t['value']) < 1e-9);
                    if ($ok) {
                        $correct++;
                    }
                    unset($got[$i]);
                    break;
                }
            }
        }
    }
    return ['values' => $total, 'loaded' => $loaded, 'correct' => $correct, 'wrong_loaded' => $loaded - $correct,
        'precision' => $loaded ? $correct / $loaded : 0.0, 'recall' => $total ? $correct / $total : 0.0];
}
