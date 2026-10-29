<?php
declare(strict_types=1);

/* Layout and shared view pieces. Copy is Portuguese; the product page links to English. */

function layout(string $title, string $body, array $opts = []): void
{
    security_headers();
    $nav = '';
    if (($opts['nav'] ?? null) === 'patient') {
        $items = ['' => 'Visão geral', 'timeline' => 'Linha do tempo', 'docs' => 'Documentos', 'meds' => 'Medicações', 'body' => 'Corpo', 'shares' => 'Compartilhar', 'account' => 'Conta'];
        $cur = $opts['active'] ?? '';
        foreach ($items as $r => $label) {
            $nav .= '<a href="' . h(url($r)) . '"' . ($cur === $r ? ' aria-current="page"' : '') . '>' . h($label) . '</a>';
        }
        $nav = '<nav class="app-nav" aria-label="Eixo">' . $nav . '</nav>'
            . '<form method="post" action="' . h(url('logout')) . '" class="inline">' . csrf_field() . '<button class="link">Sair</button></form>';
    } elseif (($opts['nav'] ?? null) === 'doctor') {
        $nav = '<nav class="app-nav" aria-label="Eixo"><a href="' . h(url('doctor')) . '">Pacientes</a><a href="' . h(url('ai')) . '">Ficha da IA</a></nav>'
            . '<form method="post" action="' . h(url('dlogout')) . '" class="inline">' . csrf_field() . '<button class="link">Sair</button></form>';
    }
    $flash = session_status() === PHP_SESSION_ACTIVE ? flash() : null;
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . h($title) . ' · Eixo</title>'
        . '<link rel="icon" href="/assets/favicon.svg"><link rel="manifest" href="' . h(url() . 'assets/manifest.webmanifest') . '">'
        . '<link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="' . h(url() . 'assets/eixo.css?v=1') . '">'
        . '<script src="' . h(url() . 'assets/eixo.js?v=1') . '" defer></script></head><body>'
        . '<header class="app-bar"><a class="site-brand" href="' . h(url()) . '"><span class="site-mark" aria-hidden="true">ei</span>Eixo</a>' . $nav . '</header>'
        . '<main class="app" id="main">' . ($flash ? '<p class="flash" role="status">' . h($flash) . '</p>' : '') . $body . '</main>'
        . '<footer class="app-foot"><p>O Eixo organiza e mostra seus documentos. Não interpreta exames, não diagnostica e não sugere doses: leve seus dados ao seu médico.</p>'
        . '<p><a href="' . h(url('privacy')) . '">Privacidade</a> · <a href="' . h(url('ai')) . '">Como a IA é usada</a> · <a href="/">andreycosta.com</a></p></footer>'
        . '</body></html>';
}

function deny(int $code = 404, string $msg = 'Não encontrado.'): never
{
    http_response_code($code);
    layout('Erro', '<h1>' . h($msg) . '</h1><p><a href="' . h(url()) . '">Voltar</a></p>');
    exit;
}

/** Value as the lab printed it, with the unit, plus a note when the series shows it converted. */
function value_html(array $p): string
{
    $v = h(trim(($p['value_printed'] ?? '') . ' ' . ($p['unit_printed'] ?? $p['unit'] ?? '')));
    if (!empty($p['converted'])) {
        $v .= ' <span class="muted">(' . h(br_number((float) $p['converted']['value']) . ' ' . $p['converted']['unit']) . ' na série)</span>';
    }
    return $v;
}

function ranges_html(?string $printed): string
{
    return $printed ? h($printed) : '<span class="muted">sem faixa impressa</span>';
}

function flag_html(?string $flag): string
{
    return $flag ? ' <span class="lab-flag" title="Marcação impressa pelo próprio laboratório">' . h($flag) . '</span>' : '';
}

/** Provenance chip: page and verbatim quote from the report (RF-11). */
function provenance_html(array $p, string $fileUrl): string
{
    $page = (int) ($p['page'] ?? 0);
    return '<details class="prov"><summary>Origem: página ' . ($page ?: '?') . '</summary>'
        . '<blockquote>' . h($p['quote'] ?? '') . '</blockquote>'
        . '<a href="' . h($fileUrl . ($page ? '#page=' . $page : '')) . '" target="_blank" rel="noopener">Abrir o laudo nesta página ↗</a></details>';
}

function status_label(string $s): string
{
    return ['stored' => 'Guardado', 'queued' => 'Na fila', 'processing' => 'Lendo', 'done' => 'Lido', 'failed' => 'Não foi possível ler', 'duplicate' => 'Duplicado'][$s] ?? $s;
}

function doc_type_label(?string $t): string
{
    return ['lab_report' => 'Laudo laboratorial', 'bioimpedance' => 'Bioimpedância', 'prescription' => 'Receita', 'imaging_report' => 'Laudo de imagem', 'other' => 'Outro'][$t ?? ''] ?? 'Documento';
}

function check_label(string $k, string $v): string
{
    $labels = [
        'text' => ['ok' => 'valor presente no texto da página', 'quotes' => 'valor presente nos trechos das duas leituras (foto sem camada de texto)', 'missing' => 'valor não encontrado no texto da página'],
        'unit' => ['ok' => 'unidade compatível com o exame', 'unverified' => 'unidade não verificável', 'mismatch' => 'unidade incompatível com o exame', 'n/a' => 'resultado não numérico'],
        'magnitude' => ['ok' => 'ordem de grandeza coerente', 'range' => 'ordem de grandeza incoerente com a faixa impressa', 'history' => 'ordem de grandeza incoerente com seu histórico neste laboratório'],
        'date' => ['ok' => 'data da coleta identificada', 'missing' => 'data da coleta não identificada'],
    ];
    return $labels[$k][$v] ?? "$k: $v";
}

function resolution_label(string $r): string
{
    return ['ab' => 'as duas leituras concordaram', 'ac' => 'desempate confirmou a leitura A', 'bc' => 'desempate confirmou a leitura B', 'unresolved' => 'as leituras não concordaram'][$r] ?? $r;
}

/** Chart container: data is JSON in a non-executed script tag, drawn by eixo.js. */
function chart_html(array $series, array $markers, string $id = 'chart', array $ctx = []): string
{
    $points = [];
    foreach ($series['points'] as $p) {
        // A faixa sombreada é a que vale para este paciente, como no cartão.
        $escolhida = pick_range($p['ranges'] ?? [], $ctx + ['sexo' => '', 'idade' => null]);
        $range = $escolhida ? ['low' => $escolhida['low'] ?? null, 'high' => $escolhida['high'] ?? null] : null;
        if ($range && !empty($p['converted']) && ($p['value'] ?? null) !== null && ($p['converted']['value'] ?? 0) != 0) {
            // Range printed in the original unit: scale with the same factor as the value.
            $factor = $p['value'] ? (float) $p['converted']['value'] / (float) $p['value'] : 1.0;
            $range = ['low' => $range['low'] !== null ? $range['low'] * $factor : null, 'high' => $range['high'] !== null ? $range['high'] * $factor : null];
        }
        if (($p['value'] ?? null) === null) {
            continue;
        }
        $points[] = ['d' => $p['date'], 'v' => (float) $p['value'], 'op' => $p['operator'], 'r' => $range, 'b' => $p['break'], 'lab' => $p['lab']];
    }
    $data = ['unit' => $series['payload']['unit_printed'] ?? '', 'points' => $points, 'markers' => $markers];
    return '<figure class="chart" data-chart="' . h($id) . '"><div class="chart-canvas" aria-hidden="true"></div>'
        . '<script type="application/json" id="' . h($id) . '">' . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '</script>'
        . '<figcaption>Faixa sombreada: faixa de referência impressa pelo próprio laboratório em cada laudo. Linha tracejada vertical: mudança de laboratório ou de método. Marcas no topo: mudanças de medicação.</figcaption></figure>';
}

function series_table_html(array $series, callable $docUrl): string
{
    $rows = '';
    foreach (array_reverse($series['points']) as $p) {
        $rows .= '<tr' . ($p['break'] ? ' class="break"' : '') . '><td>' . h(br_date($p['date'])) . '</td><td>' . value_html(['value_printed' => $p['value_printed'], 'unit_printed' => $p['unit'], 'converted' => $p['converted']])
            . flag_html($p['flag']) . ($p['origin'] === 'patient_correction' ? ' <span class="muted">(corrigido pelo paciente)</span>' : '') . '</td><td>' . ranges_html($p['reference_printed']) . '</td><td>'
            . h($p['lab'] ?? '—') . ($p['method'] ? '<br><span class="muted">' . h($p['method']) . '</span>' : '') . '</td><td>'
            . ($p['document_id'] ? '<a href="' . h($docUrl($p['document_id'], $p['page'])) . '">pág. ' . (int) $p['page'] . '</a>' : '—') . '</td></tr>';
    }
    return '<div class="table-wrap"><table><caption class="sr-only">Valores da série</caption><thead><tr><th>Data</th><th>Valor como impresso</th><th>Faixa do laboratório</th><th>Laboratório e método</th><th>Origem</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

function summary_html(array $s, callable $docUrl, callable $seriesUrl): string
{
    $out = '<section class="summary"><h2>Novos resultados desde ' . h(br_date($s['since'])) . '</h2>';
    if (!$s['results']) {
        $out .= '<p class="muted">Nenhum resultado novo no período.</p>';
    } else {
        $out .= '<div class="table-wrap"><table><thead><tr><th>Exame</th><th>Resultado(s) no período</th><th>Resultado anterior</th><th>Faixa do laboratório</th></tr></thead><tbody>';
        foreach ($s['results'] as $r) {
            $vals = [];
            foreach ($r['recent'] as $p) {
                $vals[] = h(br_date($p['date'])) . ': ' . value_html(['value_printed' => $p['value_printed'], 'unit_printed' => $p['unit'], 'converted' => $p['converted']]) . flag_html($p['flag'])
                    . ($p['document_id'] ? ' <a class="src" href="' . h($docUrl($p['document_id'], $p['page'])) . '">[pág. ' . (int) $p['page'] . ']</a>' : '')
                    . ($p['origin'] === 'patient_correction' ? ' <span class="muted">(corrigido pelo paciente; original no laudo)</span>' : '')
                    . ($p['break'] ? ' <span class="muted">(laboratório ou método diferente do ponto anterior)</span>' : '');
            }
            $prev = $r['previous'];
            $last = end($r['recent']);
            $out .= '<tr><td><a href="' . h($seriesUrl($r['series_id'])) . '">' . h($r['label']) . '</a>' . ($r['section'] ? '<br><span class="muted">' . h($r['section']) . '</span>' : '') . '</td><td>' . implode('<br>', $vals) . '</td><td>'
                . ($prev ? h(br_date($prev['date'])) . ': ' . value_html(['value_printed' => $prev['value_printed'], 'unit_printed' => $prev['unit'], 'converted' => $prev['converted']]) : '—')
                . '</td><td>' . ranges_html($last['reference_printed'] ?? null) . '</td></tr>';
        }
        $out .= '</tbody></table></div>';
    }
    $out .= '<h2>Medicações e suplementos</h2>';
    if (!$s['medications']) {
        $out .= '<p class="muted">Nenhuma medicação registrada no período.</p>';
    } else {
        $types = ['start' => 'início', 'adjust' => 'ajuste', 'pause' => 'pausa', 'resume' => 'retomada', 'stop' => 'suspensão'];
        $out .= '<ul class="plain">';
        foreach ($s['medications'] as $m) {
            $ev = array_map(fn ($e) => h(br_date($e['event_on']) . ' — ' . $types[$e['type']] . (($e['p']['dose'] ?? '') !== '' ? ': ' . $e['p']['dose'] : '') . (($e['p']['posology'] ?? '') !== '' ? ', ' . $e['p']['posology'] : '')), $m['events']);
            $cur = $m['current'];
            $out .= '<li><strong>' . h($m['name']) . '</strong> ' . ($m['kind'] === 'supplement' ? '(suplemento)' : '')
                . ($cur ? ' · situação atual: ' . h($types[$cur['type']] . (($cur['p']['dose'] ?? '') !== '' ? ' ' . $cur['p']['dose'] : '')) : '')
                . ($ev ? '<br><span class="muted">Mudanças no período:</span> ' . implode('; ', $ev) : '<br><span class="muted">Sem mudanças no período.</span>') . '</li>';
        }
        $out .= '</ul>';
    }
    $out .= '<h2>Composição corporal</h2>';
    if (!$s['body'] && !$s['anthro']) {
        $out .= '<p class="muted">Nenhuma medida no período.</p>';
    } else {
        $out .= '<ul class="plain">';
        foreach ($s['body'] as $b) {
            $single = $b['first']['date'] === $b['last']['date'];
            $out .= '<li><strong>' . h($b['label']) . '</strong> (' . h($b['device']) . '): ' . h(br_date($b['first']['date']) . ' ' . br_number($b['first']['value']) . ' ' . $b['unit'])
                . ($single ? '' : ' → ' . h(br_date($b['last']['date']) . ' ' . br_number($b['last']['value']) . ' ' . $b['unit']))
                . ($b['warning'] ? '<br><span class="muted">' . h($b['warning']) . '</span>' : '')
                . ($b['condition_change'] ? '<br><span class="muted">As condições de medição (horário, jejum, atividade ou hidratação) mudaram entre medições.</span>' : '') . '</li>';
        }
        foreach ($s['anthro'] as $a) {
            $out .= '<li>' . h(br_date($a['measured_on'])) . ': ' . h(anthro_text($a['p'])) . '</li>';
        }
        $out .= '</ul>';
    }
    $out .= '<h2>Valores não confirmados (quarentena)</h2>';
    if (!$s['quarantine']) {
        $out .= '<p class="muted">Nenhum.</p>';
    } else {
        $out .= '<p class="muted">As leituras não concordaram ou o valor não passou na checagem. Ficam fora das séries e dos gráficos; confira no laudo original.</p><ul class="plain">';
        foreach ($s['quarantine'] as $q) {
            $p = $q['p'];
            $out .= '<li>' . h(($p['name_printed'] ?? '') . ': ' . ($p['value_printed'] ?? '') . ' ' . ($p['unit_printed'] ?? '')) . ' <a class="src" href="' . h($docUrl((int) $q['document_id'], $p['page'] ?? 0)) . '">[laudo, pág. ' . (int) ($p['page'] ?? 0) . ']</a></li>';
        }
        $out .= '</ul>';
    }
    return $out . '</section>';
}

function anthro_text(array $p): string
{
    $parts = [];
    foreach (['weight' => ['Peso', 'kg'], 'height' => ['Altura', 'cm'], 'waist' => ['Cintura', 'cm'], 'sbp' => ['PA sistólica', 'mmHg'], 'dbp' => ['PA diastólica', 'mmHg']] as $k => [$l, $u]) {
        if (($p[$k] ?? null) !== null && $p[$k] !== '') {
            $parts[] = "$l " . br_number((float) $p[$k]) . " $u";
        }
    }
    return implode(' · ', $parts);
}

function ai_disclosure_html(): string
{
    return '<p class="note">Estes dados foram organizados com apoio de inteligência artificial: dois modelos de fornecedores diferentes transcrevem cada laudo, e o código só carrega o que as duas leituras confirmam e o que passa na checagem contra o texto do laudo. Nenhum número é calculado ou interpretado pela IA. <a href="' . h(url('ai')) . '">Ficha de transparência</a>.</p>';
}

/**
 * Um exame na visão geral: nome curto, valor de agora com a seta da última
 * mudança, e a faixa que o laboratório imprimiu para este paciente. O resto
 * (histórico, laudo de origem, conversões) vive na página da série.
 */
function exam_card_html(array $series, array $ctx): string
{
    $exame = exam_of($series['payload'] ?? []);
    $ind = exam_indicator($series, $ctx);
    $titulo = $exame['nome'];
    $classe = 'card exam' . ($ind['cor'] !== 'neutro' ? ' is-' . $ind['cor'] : '');

    if ($ind['tipo'] === 'qualitativo') {
        $marca = '<span class="dot dot-' . h($ind['cor']) . '" aria-hidden="true"></span>';
        $valor = '<span class="exam-value">' . $marca . h($ind['valor']) . '</span>';
    } else {
        // Triângulo cheio num chip colorido, não a seta tipográfica: tem de se ler de longe.
        $glifo = ['↑' => '▲', '↓' => '▼', '→' => '='][$ind['seta']] ?? '';
        $seta = $glifo !== '' ? '<span class="arrow" aria-hidden="true">' . $glifo . '</span>' : '';
        $valor = '<span class="exam-value">' . h($ind['valor'])
            . ($ind['unidade'] !== '' ? ' <span class="exam-unit">' . h($ind['unidade']) . '</span>' : '')
            . $seta . '</span>';
    }

    $rodape = $ind['faixa'] !== ''
        ? '<span class="exam-range">faixa ' . h($ind['faixa']) . '</span>'
        : '<span class="exam-range none">sem faixa no laudo</span>';

    return '<a class="' . $classe . '" href="' . h(url('series', ['id' => $series['id']])) . '">'
        . '<span class="exam-name" title="' . h((string) ($series['payload']['label'] ?? '')) . '">' . h($titulo) . '</span>'
        . $valor
        . '<span class="sr-only">' . h($ind['legenda']) . '</span>'
        . $rodape . '</a>';
}
