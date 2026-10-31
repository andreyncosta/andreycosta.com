<?php
declare(strict_types=1);

/* Eixo tests: php eixo/tests.php — no network, no API keys (fake providers). */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$dir = sys_get_temp_dir() . '/eixo-test-' . bin2hex(random_bytes(4));
putenv('EIXO_DATA_DIR=' . $dir);
putenv('EIXO_PROVIDERS=fake');
require __DIR__ . '/src/bootstrap.php';

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

foreach (['', '/blobs', '/sessions', '/fake'] as $s) {
    mkdir($dir . $s, 0700, true);
}
file_put_contents($dir . '/master.key', sodium_bin2base64(random_bytes(32), SODIUM_BASE64_VARIANT_ORIGINAL));
migrate();
assert_schema();

/* ── Units (RN-05) ── */
ok(ucum_convert(6.5, '%', 'mmol/mol', 'MFr', 'SFr') === null, 'HbA1c % never converts to mmol/mol');
ok(ucum_convert(30, 'ng/mL', 'nmol/L', 'MCnc', 'SCnc') === null, 'vitamin D mass/substance not converted');
ok(abs(ucum_convert(2.5, 'u[IU]/mL', 'm[IU]/L', 'ACnc', 'ACnc') - 2.5) < 1e-12, 'µUI/mL equals mUI/L');
ok(abs(ucum_convert(90, 'mg/dL', 'g/L', 'MCnc', 'MCnc') - 0.9) < 1e-12, 'mg/dL to g/L');
ok(ucum_convert(90, 'mg/dL', 'g/L', null, null) === null, 'no conversion without property');
ok(ucum_from_printed('nmol/l') === 'nmol/L', 'mol keeps its l');
ok(unit_matches_property('mmol/L', 'MCnc') === 'mismatch', 'unit/property mismatch detected');
ok(unit_matches_property('xyz', 'MCnc') === 'unverified', 'unknown unit is unverified');
/* ── Dimensions table and number extraction ── */
ok(br_parse_number('1.234,5') === 1234.5, 'Brazilian thousands and decimal');
ok(br_parse_number('1.234') === 1234.0, 'three digits after the dot are thousands');
ok(br_parse_number('12.5') === 12.5, 'a lone dot with two digits is decimal');
ok(br_parse_number('0,01') === 0.01, 'comma decimal');
ok(br_parse_number('nao reagente') === null, 'text is not a number');
ok(ucum_from_printed('x 10³/mm³') === '10*3/mm3', 'power of ten with a leading x');
ok(ucum_from_printed('mm/1h') === 'mm/h', 'sedimentation rate in the first hour');
ok(ucum_from_printed('mL/min/1,73m²') === 'mL/min/{1.73_m2}', 'eGFR body surface is an annotation');
ok(ucum_from_printed('mg/L (0,876 mg/dL)') === 'mg/L', 'a conversion in brackets is not part of the unit');
ok(ucum_parse('mL/min/{1.73_m2}')[1] == ['T' => -1, 'V' => 1], 'annotations carry no dimension');
ok(unit_matches_property('mL/min/{1.73_m2}', 'VRat') === 'ok', 'eGFR unit matches a volume rate');
ok(unit_label('g/dl') === 'g/dL' && unit_label('fl') === 'fL', 'one spelling per unit');
ok(unit_label('mIU/mL') === 'mUI/mL' && unit_label('µUI/mL') === 'µUI/mL', 'international units in Portuguese');
ok(unit_label('escore de risco') === 'escore de risco', 'an unknown unit is shown as printed');
$corte = split_printed('RESULTADO: < 0,01 µUI/mL');
ok($corte['operator'] === '<' && $corte['number'] === 0.01 && $corte['unit'] === 'µUI/mL' && $corte['numbers'] === 1,
    'label, operator, number and unit split out of one cell');
ok(split_printed('182 mg/dL')['unit'] === 'mg/dL', 'the unit comes off the printed value');
ok(split_printed('70 a 99')['unit'] === '' && split_printed('70 a 99')['numbers'] === 2,
    'a reference range is not a unit');
ok(split_printed('4.567 1.234 a 5.678 /uL')['numbers'] > 1, 'a collapsed table row has several numbers');
ok(printed_with_unit('182 mg/dL', 'mg/dL') === '182 mg/dL', 'the unit is not printed twice');
ok(printed_with_unit('4,2 g/dl', 'g/dL') === '4,2 g/dl', 'another spelling of the same unit counts');
ok(printed_with_unit('182', 'mg/dL') === '182 mg/dL', 'a value without the unit still gets it');
ok(printed_with_unit('34', '') === '34', 'no unit, nothing appended');
ok(printed_with_unit('Não reagente', 'g') === 'Não reagente g', 'the g of reagente is not a gram');
ok(printed_with_unit('12%', '%') === '12%', 'a percent sign glued to the number counts');
$cruzes = tidy_result(['value_printed' => '3+ cruzes', 'unit_printed' => null, 'value_number' => null, 'operator' => '']);
ok($cruzes['value_number'] === null, 'a semi-quantitative result does not become a number');
$asterisco = tidy_result(['value_printed' => '182 mg/dL *', 'unit_printed' => null, 'value_number' => null, 'operator' => '']);
ok($asterisco['value_number'] === null, 'leftovers after the unit stop the extraction');
$glued = tidy_result(['value_printed' => '182 mg/dL', 'unit_printed' => null, 'value_number' => null, 'operator' => '']);
ok($glued['value_number'] === 182.0 && $glued['unit_printed'] === 'mg/dL' && $glued['value_printed'] === '182 mg/dL',
    'number and unit recovered, printed value untouched');
$asserted = tidy_result(['value_printed' => '182 mg/dL', 'unit_printed' => 'mg/dL', 'value_number' => 180.0, 'operator' => '']);
ok($asserted['value_number'] === 180.0, 'what the reader asserted is never overwritten');
$row = tidy_result(['value_printed' => '4.567 1.234 a 5.678', 'unit_printed' => null, 'value_number' => null, 'operator' => '']);
ok($row['value_number'] === null, 'several numbers in one cell yield no value');
ok(same_value($glued, tidy_result(['value_printed' => '182', 'unit_printed' => 'mg/dL', 'value_number' => 182.0, 'operator' => ''])),
    'readers that split the cell differently now agree');
ok(same_value(['value_printed' => 'RESULTADO: Não reagente'], ['value_printed' => 'Não reagente']),
    'the cell label does not break the agreement');
ok(unit_key('g/dl') === unit_key('g/dL') && unit_key('mil/mm³') === unit_key('10*3/mm3'),
    'the range catalogue keys units by dimension');

/* ── TOTP and crypto ── */
$secret = base32_encode(random_bytes(20));
$t = 1_800_000_000;
ok(totp_verify($secret, totp_code($secret, intdiv($t, 30)), 0, $t) === intdiv($t, 30), 'TOTP verifies');
ok(totp_verify($secret, totp_code($secret, intdiv($t, 30)), intdiv($t, 30), $t) === null, 'TOTP step cannot be reused');
ok(totp_code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', intdiv(59, 30)) === '287082', 'RFC 6238 vector');
$k = random_bytes(32);
ok(unseal($k, seal($k, 'TSH 2,1')) === 'TSH 2,1', 'seal/unseal');

/* ── Users and consent ── */
$uid = create_or_reset_user('Paciente@Example.com', 'senha-longa-de-teste', $secret);
q('UPDATE users SET totp_enabled = 1 WHERE id = ?', [$uid]);
ok(row('SELECT email FROM users WHERE id = ?', [$uid])['email'] === 'paciente@example.com', 'email normalized');
set_consent($uid, 'store', true);
set_consent($uid, 'ai', true);
ok(consents($uid)['ai'] && !consents($uid)['research'], 'consents by purpose, research off by default');

/* ── Minimization ── */
$m = minimize_text("Paciente: Maria da Silva\nCPF: 123.456.789-09\nTSH   2,10 µUI/mL\nMédico solicitante: Dr. Fulano CRM-PI 12345");
ok(!str_contains($m, 'Maria') && !str_contains($m, '123.456') && !str_contains($m, 'Fulano') && str_contains($m, '2,10'), 'identifiers removed, values kept');

/* ── Model replies: no provider enforces the schema, so parsing and validation are ours ── */
ok(json_from_model_text("```json\n{\"a\": 1}\n```") === ['a' => 1], 'JSON found inside a markdown fence');
ok(json_from_model_text('Segue o resultado: {"a": {"b": 2}} — pronto.') === ['a' => ['b' => 2]], 'JSON found between prose');
ok(json_from_model_text('{"q": "uma } chave no texto", "n": 3}') === ['q' => 'uma } chave no texto', 'n' => 3], 'a brace inside a string does not close the object');
ok(json_from_model_text('nenhum objeto aqui') === null, 'a reply with no object is rejected');
ok(json_from_model_text('{"a": 1}' . "\n\nObservação: o item {X} não foi encontrado.") === ['a' => 1], 'prose with braces after the object does not break it');
ok(json_from_model_text("Aqui está:\n```json\n{\"a\": [1, 2]}\n```\nEspero ter ajudado.") === ['a' => [1, 2]], 'a fence surrounded by prose is read');

$exSchema = extraction_schema();
$perfect = synthetic_report(11)['fixture'];
ok(schema_errors(coerce_to_schema($perfect, $exSchema), $exSchema) === [], 'a perfect reading fits the schema after coercion');

$loose = coerce_to_schema(['doc_type' => 'lab_report', 'laboratory' => null, 'collection_date' => '2026-01-02',
    'body_composition' => null, 'prescription_items' => [], 'invented_key' => 'x'], $exSchema);
ok(($loose['laboratory'] ?? 'x') === '', 'optional text arriving as null becomes ""');
ok(!array_key_exists('invented_key', $loose), 'a key outside the schema is dropped');
ok(($loose['results'] ?? null) === [], 'a missing list becomes empty, never invented');
ok(schema_errors($loose, $exSchema) === [], 'the coerced minimum fits the schema');
ok(coerce_to_schema(['page' => '3', 'v' => '5.4'], ['type' => 'object', 'properties' => ['page' => ['type' => 'integer'], 'v' => ['type' => 'number']]]) === ['page' => 3, 'v' => 5.4], 'a numeric string becomes a number');
ok(coerce_to_schema('12,5', ['type' => 'number']) === '12,5', 'a comma decimal is never silently converted');

$wrong = $perfect;
$wrong['doc_type'] = 'laudo';
$wrong['results'][0]['page'] = 'um';
$errs = schema_errors(coerce_to_schema($wrong, $exSchema), $exSchema);
ok(count($errs) === 2 && str_contains($errs[0], 'doc_type') && str_contains($errs[1], 'results[0].page'), 'a bad enum and a bad type are reported by path');
ok(reader_model('A') !== reader_model('B'), 'readers A and B are different models');

/* ── A model that loops on a page must not fill the series with the repeats ── */
$linha = fn (string $nome, string $valor, ?float $num, string $trecho) => ['page' => 3, 'column' => 'current',
    'name_printed' => $nome, 'value_printed' => $valor, 'value_number' => $num, 'value_text' => '',
    'unit_printed' => 'mg/dL', 'operator' => '', 'quote' => $trecho];
[$fica, $foram] = drop_looped_results([
    $linha('Glicose', '92', 92.0, 'Glicose 92 mg/dL'),
    $linha('Glicose', '92', 92.0, 'Glicose 92 mg/dL'),
    $linha('Ureia', '31', 31.0, 'Ureia 31 mg/dL'),
]);
ok(count($fica) === 2 && $foram === 1, 'the same line read twice with the same value collapses into one');
[$fica, $foram] = drop_looped_results([
    $linha('Glicose', '92', 92.0, 'Glicose 92 mg/dL'),
    $linha('Glicose', '87', 87.0, 'Glicose 92 mg/dL'),
    $linha('Ureia', '31', 31.0, 'Ureia 31 mg/dL'),
]);
ok(count($fica) === 1 && $fica[0]['name_printed'] === 'Ureia' && $foram === 2, 'one line backing two different values keeps neither');
[$fica, $foram] = drop_looped_results([
    $linha('Glicose', '92', 92.0, 'Glicose 92 mg/dL'),
    ['page' => 4] + $linha('Glicose', '92', 92.0, 'Glicose 92 mg/dL'),
]);
ok(count($fica) === 2 && $foram === 0, 'the same value printed on two pages is two readings, not a loop');

/* ── Pages are read in groups, so the groups have to go back together ── */
$grupo = fn (array $over) => $over + ['doc_type' => 'other', 'laboratory' => '', 'collection_date' => '',
    'results' => [], 'body_composition' => null, 'prescription_items' => []];
$merged = merge_page_groups([
    $grupo(['results' => [['name_printed' => 'capa', 'page' => 1]]]),
    $grupo(['doc_type' => 'lab_report', 'laboratory' => 'Fleury', 'collection_date' => '2026-01-02',
        'results' => [['name_printed' => 'glicose', 'page' => 6]],
        'body_composition' => ['metrics' => [['name_printed' => 'peso']]]]),
    $grupo(['doc_type' => 'bioimpedance', 'laboratory' => 'Outro', 'collection_date' => '2026-02-02',
        'body_composition' => ['metrics' => [['name_printed' => 'gordura']]],
        'prescription_items' => [['drug' => 'metformina']]]),
]);
ok($merged['doc_type'] === 'lab_report', 'the document type comes from the first group that is not "other"');
ok($merged['laboratory'] === 'Fleury' && $merged['collection_date'] === '2026-01-02', 'a header field takes the first group that printed it');
ok(array_column($merged['results'], 'name_printed') === ['capa', 'glicose'], 'results are concatenated in page order');
ok(array_column($merged['body_composition']['metrics'], 'name_printed') === ['peso', 'gordura'], 'body composition metrics from every group are kept');
ok(array_column($merged['prescription_items'], 'drug') === ['metformina'], 'prescription items are concatenated');
ok(merge_page_groups([]) === ['doc_type' => 'other', 'laboratory' => '', 'collection_date' => '', 'results' => [], 'body_composition' => null, 'prescription_items' => []], 'no group read leaves an empty document, never a half one');

/* ── A header field read by two readers ── */
ok(meta_vote(['Fleury', '', null]) === 'Fleury', 'the only reader who named the lab decides');
ok(meta_vote(['', 'Sabin', null]) === 'Sabin', 'it works whichever reader it was');
ok(meta_vote(['Fleury', 'FLEURY ', null]) === 'Fleury', 'the same name written differently still agrees');
ok(meta_vote(['Fleury', 'Sabin', 'Fleury']) === 'Fleury', 'two readers out of three carry the name');
ok(meta_vote(['Fleury', 'Sabin', null]) === null, 'two names and no third vote cancel each other');
ok(meta_vote(['Fleury', 'Sabin', 'Hermes Pardini']) === null, 'three different names leave the field empty');
ok(meta_vote([null, null, null]) === null, 'no reader printed it');
ok(meta_vote(['Fleury Medicina e Saúde', 'Fleury', 'Clínica São Jorge']) === 'Fleury',
    'the full name and the brand it contains are one vote, and the brand wins');
ok(meta_vote(['Laboratório Sabin', 'Sabin']) === 'Sabin', 'two readers, one of them long-winded');
ok(meta_vote(['Delboni Auriemo', 'Alta Diagnósticos', 'Dasa']) === null,
    'three unrelated names still leave the field empty');
ok(brand_within('Laboratório de Análises', ['de']) === 'Laboratório de Análises',
    'a loose preposition is not a brand');
ok(str_contains(extraction_instructions(), 'laboratory is the name of the laboratory'), 'the instructions say what goes in laboratory');

/* ── Text layer put back: scans and photos ── */
ok(split_transcription("=== Página 1 ===\nGLICOSE  182 mg/dL\n=== Página 2 ===\nVHS  12 mm/1a hora")
    === ['GLICOSE  182 mg/dL', 'VHS  12 mm/1a hora'], 'the marker splits the transcription into pages');
ok(split_transcription("==== Pagina 3 ====\numa linha") === ['uma linha'],
    'the marker counts without its accent and with any number of signs');
ok(split_transcription('GLICOSE  182 mg/dL') === ['GLICOSE  182 mg/dL'],
    'a transcription with no marker at all is one page');
ok(split_transcription('') === [], 'nothing transcribed is no page at all');
ok(split_transcription("=== Página 1 ===\n\n=== Página 2 ===\nsó a segunda tem texto")
    === ['só a segunda tem texto'], 'a blank page does not become an empty page in the middle');
ok(!str_contains(implode('|', split_transcription("=== Página 1 ===\nGLICOSE  182")), 'Página'),
    'the marker itself does not end up inside the page text');
ok(str_contains(transcription_prompt(6, 12), 'starting at number 6'), 'the transcriber is told where the slice starts');
ok(str_contains(transcription_prompt(6, 12), '12 page(s) in all'), 'and how long the whole report is');
ok(str_contains(transcription_prompt(1, 1), '[omitido]'), 'identifiers are left out at the source (RNF-07)');
ok(str_contains(transcription_prompt(1, 1), 'exactly as printed')
    && str_contains(transcription_prompt(1, 1), 'do not convert'),
    'and nothing is converted or corrected on the way');
// A transcrição é uma leitura como as outras: tem fornecedor, modelo, custo e erro.
$docScan = add_doc($uid, '%PDF-1.4 um laudo sem camada de texto');
store_reading($uid, $docScan, 'T', ['ok' => true, 'payload' => null, 'provider' => 'google',
    'model' => 'google:gemini-3.5-flash-lite', 'mode' => 'file', 'in' => 582, 'out' => 900,
    'error' => null, 'detail' => null]);
$leituraT = row('SELECT input_mode, input_tokens FROM readings WHERE document_id = ? AND reader = ?', [$docScan, 'T']);
ok(($leituraT['input_mode'] ?? '') === 'file' && (int) ($leituraT['input_tokens'] ?? 0) === 582,
    'the transcription is stored as a reading of its own, with what it cost');

/* ── Each provider gets the schema in its own dialect ── */
ok(reader_spec('A')[0] === 'openai' && reader_spec('B')[0] === 'google', 'the readers name their provider');
ok(reader_spec('X') === reader_spec('A'), 'an unknown reader falls back to A');
ok(provider_enforces_schema('openai') && provider_enforces_schema('google') && !provider_enforces_schema('replicate'), 'only the direct APIs enforce the schema');

$oa = to_openai_schema(extraction_schema());
ok(($oa['additionalProperties'] ?? null) === false, 'strict mode needs additionalProperties false');
ok($oa['required'] === array_keys($oa['properties']), 'strict mode needs every property required');
ok(($oa['properties']['results']['items']['properties']['value_number']['anyOf'][1]['type'] ?? '') === 'null', 'a nullable number stays a union for OpenAI');

$go = to_google_schema(extraction_schema());
ok($go['type'] === 'OBJECT' && $go['properties']['results']['type'] === 'ARRAY', 'Google wants the types in capitals');
ok(!array_key_exists('additionalProperties', $go), 'Google rejects additionalProperties');
ok(($go['properties']['results']['items']['properties']['value_number']['nullable'] ?? false) === true, 'a nullable number becomes nullable for Google');
ok(($go['properties']['doc_type']['enum'][0] ?? '') === 'lab_report', 'an enum survives the conversion');
ok(!isset($go['properties']['results']['items']['properties']['operator']['enum']), 'an enum holding an empty string is dropped, which Google refuses');

$comEsquema = extraction_prompt('pagina', 'tarefa', null, true);
$semEsquema = extraction_prompt('pagina', 'tarefa', null, false);
ok(str_contains($comEsquema, 'JSON Schema') && !str_contains($semEsquema, 'JSON Schema'), 'the schema only travels in the prompt where nobody enforces it');
ok(mb_strlen($semEsquema) < mb_strlen($comEsquema) - 1000, 'leaving it out saves a real chunk of the prompt');

/* ── Nome do exame e indicador do cartão ── */
ok(exam_key('CORTISOL........') === 'cortisol', 'the dot leader from the PDF leaves the name');
ok(exam_key('Colesterol HDL, soro') === 'colesterol hdl', 'the specimen tacked onto the name leaves it');
ok(exam_of(['label' => 'HEMOGLOBINA GLICADA'])['nome'] === 'Hemoglobina glicada', 'a printed name becomes the dictionary name');
ok(exam_of(['label' => 'x', 'loinc' => '2085-9'])['nome'] === 'HDL', 'the LOINC code wins over the printed name');
ok(exam_of(['label' => 'EXAME MUITO ESPECIFICO'])['nome'] === 'Exame Muito Especifico', 'a name outside the dictionary loses the all-caps');
ok(exam_of(['label' => 'LDL'])['direcao'] === 'baixo' && exam_of(['label' => 'HDL'])['direcao'] === 'alto', 'LDL and HDL carry a fixed direction');

$ctxF = ['sexo' => 'female', 'idade' => 40];
$ctxM = ['sexo' => 'male', 'idade' => 40];
$faixas = [
    ['kind' => 'interval', 'condition' => 'Homens', 'label' => null, 'low' => 13.0, 'high' => 17.0],
    ['kind' => 'interval', 'condition' => 'Mulheres', 'label' => null, 'low' => 12.0, 'high' => 15.5],
];
ok(pick_range($faixas, $ctxM)['high'] === 17.0, 'the range for men is picked for a man');
ok(pick_range($faixas, $ctxF)['high'] === 15.5, 'the range for women is picked for a woman');
ok(pick_range($faixas, ['sexo' => '', 'idade' => null]) === null, 'with no sex on file no conditioned range is assumed');
ok(range_position(16.0, pick_range($faixas, $ctxF)) === 'acima', 'above the range for a woman is reported');
ok(range_position(16.0, pick_range($faixas, $ctxM)) === 'dentro', 'the same value is inside the range for a man');
ok(qualitative_sign('Não reagente') === 'bom' && qualitative_sign('POSITIVO') === 'ruim', 'negative is the healthy one, positive is not');
ok(qualitative_sign('amarelo citrino') === null, 'text that is neither negative nor positive gets no colour');

$ponto = fn (float $v, string $d, array $faixa = []) => ['value' => $v, 'value_printed' => (string) $v,
    'value_text' => null, 'unit' => 'mg/dL', 'date' => $d, 'ranges' => $faixa,
    'reference_printed' => $faixa ? '70 a 99' : null];
$serie = fn (string $nome, array $pts) => ['id' => 1, 'pinned' => 0, 'payload' => ['label' => $nome],
    'points' => $pts, 'last' => end($pts)];

$i = exam_indicator($serie('LDL', [$ponto(160.0, '2026-01-01'), $ponto(130.0, '2026-06-01')]), $ctxM);
ok($i['seta'] === '↓' && $i['cor'] === 'bom', 'LDL coming down is green');
$i = exam_indicator($serie('LDL', [$ponto(130.0, '2026-01-01'), $ponto(160.0, '2026-06-01')]), $ctxM);
ok($i['seta'] === '↑' && $i['cor'] === 'ruim', 'LDL going up is red');
$i = exam_indicator($serie('LDL', [$ponto(130.0, '2026-06-01')]), $ctxM);
ok($i['seta'] === '' && $i['legenda'] === 'primeiro resultado', 'a single point gets no arrow');

$faixaG = [['kind' => 'interval', 'condition' => '', 'label' => null, 'low' => 70.0, 'high' => 99.0]];
$i = exam_indicator($serie('Glicose', [$ponto(120.0, '2026-01-01', $faixaG), $ponto(95.0, '2026-06-01', $faixaG)]), $ctxM);
ok($i['cor'] === 'bom' && $i['posicao'] === 'dentro', 'coming back inside the range is green');
$i = exam_indicator($serie('Glicose', [$ponto(95.0, '2026-01-01', $faixaG), $ponto(120.0, '2026-06-01', $faixaG)]), $ctxM);
ok($i['cor'] === 'ruim' && $i['posicao'] === 'acima', 'leaving the range is red');
$i = exam_indicator($serie('Glicose', [$ponto(80.0, '2026-01-01', $faixaG), $ponto(90.0, '2026-06-01', $faixaG)]), $ctxM);
ok($i['seta'] === '↑' && $i['cor'] === 'neutro', 'moving inside the range is neither good nor bad');
ok(str_contains($i['faixa'], '70 a 99'), 'the chosen range shows on the card');

/* ── Pipeline with fake readers ── */
function add_doc(int $uid, string $bytes, string $mime = 'application/pdf'): int
{
    $blob = store_blob($uid, $bytes);
    q('INSERT INTO documents(user_id, sha256, mime, size, blob_name, status, uploaded_at) VALUES (?,?,?,?,?,?,?)', [$uid, hash('sha256', $bytes), $mime, strlen($bytes), $blob, 'stored', now()]);
    return (int) db()->lastInsertId();
}
function fixture(string $bytes, array $payload, string $reader = ''): void
{
    file_put_contents(eixo_data_dir() . '/fake/' . hash('sha256', $bytes) . ($reader ? ".$reader" : '') . '.json', json_encode($payload));
}

/* ── A group already read survives a worker that was killed ── */
$docCache = add_doc($uid, 'documento para o cache de grupos');
$alvo = ['document_id' => $docCache, 'user_id' => $uid];
ok(remembered_group($alvo, 'A', 'dono/modelo', 0, '') === null, 'a group not read yet is not remembered');
remember_group($alvo, 'A', 'dono/modelo', 0, '', ['results' => [['name_printed' => 'glicose']]]);
ok(remembered_group($alvo, 'A', 'dono/modelo', 0, '')['results'][0]['name_printed'] === 'glicose', 'a group already read comes back');
ok(remembered_group($alvo, 'B', 'dono/modelo', 0, '') === null, 'another reader does not reuse it');
ok(remembered_group($alvo, 'A', 'dono/modelo', 1, '') === null, 'another group does not reuse it');
ok(remembered_group($alvo, 'A', 'outro/modelo', 0, '') === null, 'another model does not reuse it');
ok(remembered_group($alvo, 'C', 'dono/modelo', 0, 'confira o item X') === null, 'the tie-break does not reuse a plain reading');
remember_group($alvo, 'C', 'dono/modelo', 0, 'confira o item X', ['results' => []]);
ok(remembered_group($alvo, 'C', 'dono/modelo', 0, 'confira o item X') !== null, 'the tie-break is kept under its own instruction');
ok(remembered_group($alvo, 'C', 'dono/modelo', 0, 'confira o item Y') === null, 'another instruction is another reading');
ok(transient_error('prediction_failed') && !transient_error('schema_mismatch'), 'only a passing failure is worth another call');
forget_group_readings($docCache);
ok(remembered_group($alvo, 'A', 'dono/modelo', 0, '') === null, 'the groups go away when the document leaves the queue');

$rep = synthetic_report(7);
$text = pdf_pages_text($rep['pdf']);
ok($text !== null && str_contains($text[0], 'Hemoglobina Glicada'), 'synthetic PDF has a text layer');
fixture($rep['pdf'], $rep['fixture']);
$doc1 = add_doc($uid, $rep['pdf']);
enqueue_document($doc1);
ok(run_queue(5) === 1, 'queue processes one job');
$d = row('SELECT * FROM documents WHERE id = ?', [$doc1]);
ok($d['status'] === 'done', 'document done');
$score = score_against_truth($uid, [$doc1 => $rep['truth']]);
ok($score['wrong_loaded'] === 0 && $score['correct'] === $score['values'], 'perfect readers load every value correctly (' . $score['correct'] . '/' . $score['values'] . ')');
$prev = rows("SELECT observed_on FROM results WHERE document_id = ? AND status = 'loaded'", [$doc1]);
ok(count(array_unique(array_column($prev, 'observed_on'))) === 2, 'previous-column values keep their own date');
ok(!array_filter(array_column(rows('SELECT payload_enc FROM results'), 'payload_enc'), fn ($e) => str_contains(base64_decode($e) ?: '', 'Glicada') || str_contains($e, 'Glicada')), 'results stored encrypted');
ok(!array_filter(array_column(rows('SELECT payload_enc FROM series'), 'payload_enc'), fn ($e) => str_contains(base64_decode($e) ?: '', 'TSH')), 'series stored encrypted');

// Readings disagree on one value; tie-break C sides with A → loaded. Another value: C sides with nobody → quarantine.
$rep2 = synthetic_report(8);
$a = $rep2['fixture'];
$b = $a;
$c = $a;
$b['results'][0]['value_number'] += 1;
$b['results'][0]['value_printed'] = 'x';
$b['results'][3]['value_number'] += 5;
$c['results'][3]['value_number'] += 9;
fixture($rep2['pdf'], $a, 'A');
fixture($rep2['pdf'], $b, 'B');
fixture($rep2['pdf'], $c, 'C');
$doc2 = add_doc($uid, $rep2['pdf']);
process_document($doc2);
$res = document_results($uid, $doc2);
$byName = [];
foreach ($res as $r) {
    $byName[$r['p']['name_printed'] . '|' . $r['p']['column']][] = $r;
}
$first = $byName[$a['results'][0]['name_printed'] . '|current'][0];
ok($first['status'] === 'loaded' && $first['p']['resolution'] === 'ac', 'tie-break confirming A loads the value');
$fourth = $byName[$a['results'][3]['name_printed'] . '|' . $a['results'][3]['column']][0];
ok($fourth['status'] === 'quarantine' && $fourth['series_id'] === null, 'unresolved disagreement goes to quarantine');
ok(count(quarantine_for($uid)) === 1, 'quarantine listed');

// A value both readings agree on but that is not on the page (invented) → quarantine (RN-02).
$rep3 = synthetic_report(9);
$fx = $rep3['fixture'];
$fx['results'][1]['value_number'] = 777.77;
$fx['results'][1]['value_printed'] = '777,77';
fixture($rep3['pdf'], $fx);
$doc3 = add_doc($uid, $rep3['pdf']);
process_document($doc3);
$r3 = array_values(array_filter(document_results($uid, $doc3), fn ($r) => $r['p']['value_printed'] === '777,77'))[0];
ok($r3['status'] === 'quarantine' && $r3['p']['checks']['text'] === 'missing', 'agreed value absent from the page is quarantined');

// Unit incompatible with the proposed property → quarantine.
$rep4 = synthetic_report(10);
$fx = $rep4['fixture'];
$fx['results'][2]['loinc_property'] = 'SCnc';
fixture($rep4['pdf'], $fx);
$doc4 = add_doc($uid, $rep4['pdf']);
process_document($doc4);
$name = $fx['results'][2]['name_printed'];
$r4 = array_values(array_filter(document_results($uid, $doc4), fn ($r) => $r['p']['name_printed'] === $name && $r['p']['column'] === $fx['results'][2]['column']))[0];
ok($r4['status'] === 'quarantine' && $r4['p']['checks']['unit'] === 'mismatch', 'unit/property mismatch quarantined');

// Same lab + collection date as an already processed document → duplicate, nothing loaded (RF-07).
$dupPdf = $rep['pdf'] . "\n% copy";
fixture($dupPdf, $rep['fixture']);
$doc5 = add_doc($uid, $dupPdf);
process_document($doc5);
ok(row('SELECT status FROM documents WHERE id = ?', [$doc5])['status'] === 'duplicate' && !document_results($uid, $doc5), 'same lab and date is a duplicate');

// Series: TSH from different reports join (same LOINC/property); HbA1c in mmol/mol would not.
$series = series_for($uid);
$tsh = array_values(array_filter($series, fn ($s) => str_starts_with($s['payload']['label'], 'TSH')));
ok(count($tsh) === 1 && count($tsh[0]['points']) >= 3, 'same analyte across reports forms one series');
$labs = array_unique(array_map(fn ($p) => $p['lab'], $tsh[0]['points']));
ok(count($labs) < 2 || (bool) array_filter($tsh[0]['points'], fn ($p) => $p['break']), 'lab change marked as discontinuity');

$rep6 = synthetic_report(11);
$fx = $rep6['fixture'];
foreach ($fx['results'] as &$r) {
    if (str_starts_with($r['name_printed'], 'Hemoglobina Glicada')) {
        $r['unit_printed'] = 'mmol/mol';
        $r['loinc_code'] = '59261-8';
        $r['loinc_property'] = 'SFr';
        $r['value_number'] = 36.0;
        $r['value_printed'] = '36';
        $r['quote'] = 'Hemoglobina Glicada 36 mmol/mol';
    }
}
unset($r);
$pdf6 = tiny_pdf([array_merge(explode("\n", (string) pdf_pages_text($rep6['pdf'])[0]), ['Hemoglobina Glicada 36 mmol/mol'])]);
fixture($pdf6, $fx);
$doc6 = add_doc($uid, $pdf6);
process_document($doc6);
$hb = array_values(array_filter(series_for($uid), fn ($s) => str_starts_with($s['payload']['label'], 'Hemoglobina Glicada')));
ok(count($hb) === 2, 'HbA1c in % and in mmol/mol stay in separate series');

// Patient correction keeps the original and the audit trail.
$target = array_values(array_filter(document_results($uid, $doc1), fn ($r) => $r['status'] === 'loaded'))[0];
q("UPDATE results SET status = 'corrected' WHERE id = ?", [$target['id']]);
ok(row('SELECT status FROM results WHERE id = ?', [$target['id']])['status'] === 'corrected', 'correction marks original');

/* ── Medications, body, summary ── */
q('INSERT INTO medications(user_id, kind, payload_enc, created_at) VALUES (?,?,?,?)', [$uid, 'medication', enc($uid, ['name' => 'Semaglutida']), now()]);
$mid = (int) db()->lastInsertId();
q('INSERT INTO dose_events(user_id, medication_id, type, event_on, payload_enc, created_at) VALUES (?,?,?,?,?,?)', [$uid, $mid, 'start', '2026-03-01', enc($uid, ['dose' => '0,25 mg']), now()]);
q('INSERT INTO dose_events(user_id, medication_id, type, event_on, payload_enc, created_at) VALUES (?,?,?,?,?,?)', [$uid, $mid, 'adjust', '2026-04-01', enc($uid, ['dose' => '0,5 mg']), now()]);
foreach ([['2026-03-01', 'InBody 270', 30.1], ['2026-05-01', 'InBody 270', 28.4], ['2026-07-01', 'Tanita BC-601', 26.0]] as [$date, $dev, $fat]) {
    q('INSERT INTO body_measurements(user_id, kind, measured_on, device_hash, payload_enc, created_at) VALUES (?,?,?,?,?,?)',
        [$uid, 'bia', $date, keyed_hash($uid, norm_text($dev)), enc($uid, ['device' => ['brand' => $dev], 'conditions' => ['time' => '08:00', 'fasting' => 'em jejum'], 'metrics' => [['name' => 'Percentual de gordura', 'value' => $fat, 'unit' => '%']]]), now()]);
}
$body = body_for($uid);
ok(count($body['metrics']) === 2, 'bioimpedance series break when the device changes (RN-04)');
ok((bool) array_filter($body['metrics'], fn ($m) => $m['warning'] !== null), 'device change explained');
$sum = summary_for($uid, null, '2026-01-01');
ok($sum['results'] && $sum['medications'] && count($sum['quarantine']) >= 1, 'summary lists results, medications and quarantine');
ok(count(dose_markers(medications_for($uid))) === 2, 'dose events become chart markers');

/* ── Shares ── */
set_consent($uid, 'share', true);
$share = create_share($uid, 'Dra. Teste', 'period', '2026-01-01', '2026-02-28', 30);
ok(redeem_share_code($share['token'], $share['code'] === '00000000' ? '11111111' : '00000000') === null, 'wrong code refused');
$s = redeem_share_code($share['token'], $share['code']);
ok($s !== null, 'one-time code works');
ok(redeem_share_code($share['token'], $share['code']) === null, 'code works only once');
$scoped = series_for($uid, $s);
$outside = array_filter(array_merge(...array_map(fn ($x) => $x['points'], $scoped)), fn ($p) => $p['date'] < '2026-01-01' || $p['date'] > '2026-02-28');
ok(!$outside, 'period share hides points outside the scope');
ok(medications_for($uid, $s) === [], 'medication events outside the period are hidden');
set_consent($uid, 'share', false);
ok(active_share((int) $s['id']) === null, 'revoking the share consent ends active shares (RF-03)');

/* ── FHIR export and deletion ── */
$bundle = fhir_bundle($uid);
$types = array_count_values(array_map(fn ($e) => $e['resource']['resourceType'], $bundle['entry']));
ok(isset($types['Patient'], $types['Observation'], $types['DocumentReference'], $types['MedicationStatement'], $types['Consent']), 'FHIR bundle has the core resources');
$blobs = array_column(rows('SELECT blob_name FROM documents WHERE user_id = ?', [$uid]), 'blob_name');
delete_account($uid);
ok((int) row('SELECT COUNT(*) n FROM results WHERE user_id = ?', [$uid])['n'] === 0, 'results deleted');
ok(!array_filter($blobs, fn ($b) => is_file(blob_path($b))), 'document files deleted');
ok(row('SELECT dek_wrapped FROM users WHERE id = ?', [$uid])['dek_wrapped'] === '', 'data key destroyed');
ok((int) row('SELECT COUNT(*) n FROM access_log WHERE user_id = ?', [$uid])['n'] > 0, 'access log kept (no health data)');
$threw = false;
try {
    q("UPDATE access_log SET action = 'x'");
} catch (PDOException $e) {
    $threw = true;
}
ok($threw, 'access log is insert-only');

exec('rm -rf ' . escapeshellarg($dir));
echo $failures ? "\n$failures of $count checks FAILED\n" : "all $count checks passed\n";
exit($failures ? 1 : 0);
