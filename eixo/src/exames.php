<?php

declare(strict_types=1);

/* ── Nomes de exame e leitura do indicador ───────────────────────────────────
 * O rótulo de uma série é o nome como o laboratório imprimiu: vem com vírgulas,
 * método, material e, com frequência, a linha de pontos que o PDF usa entre o
 * nome e o valor ("CORTISOL........"). Aqui ele vira um nome curto e estável.
 *
 * O dicionário não é exaustivo, e não teria como ser: cobre o painel de rotina
 * brasileiro. O que não está nele cai na limpeza do nome impresso, que já
 * resolve a maior parte do que incomoda na tela. Acrescentar uma linha é barato.
 *
 * A direção diz o que é melhor para aquele exame:
 *   'faixa'    dentro da faixa é o bom (o padrão, e o único seguro na dúvida)
 *   'baixo'    menor é melhor, mesmo dentro da faixa
 *   'alto'     maior é melhor, mesmo dentro da faixa
 *   'negativo' qualitativo: negativo/não reagente é o saudável
 */

/**
 * Uma linha por exame: [nome exibido, direção, código LOINC ou '', ...apelidos].
 * Os apelidos são comparados depois de normalizados, então bastam as formas
 * mais comuns em que o nome aparece impresso.
 * @return list<array<int, string>>
 */
function exam_rows(): array
{
    return [
        // Hemograma
        ['Hemoglobina', 'faixa', '718-7', 'hemoglobina', 'hb'],
        ['Hematócrito', 'faixa', '4544-3', 'hematocrito', 'ht', 'hct'],
        ['Hemácias', 'faixa', '789-8', 'hemacias', 'eritrocitos', 'contagem de hemacias'],
        ['VCM', 'faixa', '787-2', 'vcm', 'volume corpuscular medio'],
        ['HCM', 'faixa', '785-6', 'hcm', 'hemoglobina corpuscular media'],
        ['CHCM', 'faixa', '786-4', 'chcm'],
        ['RDW', 'faixa', '788-0', 'rdw'],
        ['Leucócitos', 'faixa', '6690-2', 'leucocitos', 'globulos brancos', 'contagem de leucocitos'],
        ['Neutrófilos', 'faixa', '751-8', 'neutrofilos', 'segmentados'],
        ['Linfócitos', 'faixa', '731-0', 'linfocitos'],
        ['Monócitos', 'faixa', '742-7', 'monocitos'],
        ['Eosinófilos', 'faixa', '711-2', 'eosinofilos'],
        ['Basófilos', 'faixa', '704-7', 'basofilos'],
        ['Plaquetas', 'faixa', '777-3', 'plaquetas', 'contagem de plaquetas'],
        ['VPM', 'faixa', '32623-1', 'vpm', 'volume plaquetario medio'],

        // Glicemia
        ['Glicose', 'faixa', '2345-7', 'glicose', 'glicemia', 'glicemia de jejum', 'glicose em jejum'],
        ['Hemoglobina glicada', 'baixo', '4548-4', 'hemoglobina glicada', 'hba1c', 'a1c', 'glicada', 'hemoglobina glicosilada'],
        ['Insulina', 'faixa', '20448-7', 'insulina', 'insulina de jejum'],
        ['HOMA-IR', 'baixo', '', 'homa ir', 'homa', 'indice homa'],
        ['Peptídeo C', 'faixa', '1986-9', 'peptideo c'],
        ['Frutosamina', 'baixo', '1558-6', 'frutosamina'],

        // Lipídios
        ['Colesterol total', 'baixo', '2093-3', 'colesterol total', 'colesterol'],
        ['HDL', 'alto', '2085-9', 'hdl', 'colesterol hdl', 'hdl colesterol'],
        ['LDL', 'baixo', '13457-7', 'ldl', 'colesterol ldl', 'ldl colesterol'],
        ['VLDL', 'baixo', '13458-5', 'vldl', 'colesterol vldl'],
        ['Triglicérides', 'baixo', '2571-8', 'triglicerides', 'triglicerideos', 'trigliceridios'],
        ['Colesterol não-HDL', 'baixo', '43396-1', 'colesterol nao hdl', 'nao hdl'],
        ['Apolipoproteína A1', 'alto', '1869-7', 'apolipoproteina a1', 'apo a1'],
        ['Apolipoproteína B', 'baixo', '1884-6', 'apolipoproteina b', 'apo b'],
        ['Lipoproteína (a)', 'baixo', '10835-7', 'lipoproteina a', 'lp a'],

        // Tireoide
        ['TSH', 'faixa', '3016-3', 'tsh', 'hormonio tireoestimulante', 'tireotrofina'],
        ['T4 livre', 'faixa', '3024-7', 't4 livre', 'tiroxina livre', 'ft4'],
        ['T3', 'faixa', '3053-6', 't3', 'triiodotironina'],
        ['T3 livre', 'faixa', '3051-0', 't3 livre', 'ft3'],
        ['T4 total', 'faixa', '3026-2', 't4 total', 'tiroxina'],
        ['Anti-TPO', 'faixa', '8099-5', 'anti tpo', 'antitireoperoxidase', 'anticorpo antiperoxidase'],
        ['Anti-tireoglobulina', 'faixa', '8098-7', 'anti tireoglobulina', 'antitireoglobulina'],

        // Renal
        ['Creatinina', 'faixa', '2160-0', 'creatinina'],
        ['Ureia', 'faixa', '3094-0', 'ureia', 'uréia'],
        ['Taxa de filtração glomerular', 'alto', '33914-3', 'taxa de filtracao glomerular', 'tfg', 'egfr', 'ckd epi', 'filtracao glomerular estimada'],
        ['Ácido úrico', 'faixa', '3084-1', 'acido urico'],
        ['Cistatina C', 'faixa', '33863-2', 'cistatina c'],
        ['Microalbuminúria', 'baixo', '14957-5', 'microalbuminuria', 'albumina urinaria'],
        ['Relação albumina/creatinina', 'baixo', '9318-7', 'relacao albumina creatinina', 'rac'],

        // Hepático
        ['TGO (AST)', 'faixa', '1920-8', 'tgo', 'ast', 'aspartato aminotransferase', 'transaminase oxalacetica'],
        ['TGP (ALT)', 'faixa', '1742-6', 'tgp', 'alt', 'alanina aminotransferase', 'transaminase piruvica'],
        ['Gama GT', 'faixa', '2324-2', 'gama gt', 'ggt', 'gama glutamil transferase'],
        ['Fosfatase alcalina', 'faixa', '6768-6', 'fosfatase alcalina', 'fa'],
        ['Bilirrubina total', 'faixa', '1975-2', 'bilirrubina total'],
        ['Bilirrubina direta', 'faixa', '1968-7', 'bilirrubina direta'],
        ['Bilirrubina indireta', 'faixa', '1971-1', 'bilirrubina indireta'],
        ['Albumina', 'faixa', '1751-7', 'albumina', 'albumina serica'],
        ['Proteínas totais', 'faixa', '2885-2', 'proteinas totais'],
        ['INR', 'faixa', '6301-6', 'inr', 'rni'],
        ['Tempo de protrombina', 'faixa', '5902-2', 'tempo de protrombina', 'tap'],

        // Eletrólitos e minerais
        ['Sódio', 'faixa', '2951-2', 'sodio', 'na'],
        ['Potássio', 'faixa', '2823-3', 'potassio', 'k'],
        ['Cloro', 'faixa', '2075-0', 'cloro', 'cloretos'],
        ['Cálcio', 'faixa', '17861-6', 'calcio'],
        ['Cálcio iônico', 'faixa', '1994-3', 'calcio ionico', 'calcio ionizado'],
        ['Magnésio', 'faixa', '2601-3', 'magnesio'],
        ['Fósforo', 'faixa', '2777-1', 'fosforo', 'fosforo inorganico'],

        // Vitaminas e ferro
        ['Vitamina D', 'faixa', '1989-3', 'vitamina d', '25 hidroxivitamina d', '25 oh vitamina d', 'calcidiol'],
        ['Vitamina B12', 'faixa', '2132-9', 'vitamina b12', 'cobalamina', 'b12'],
        ['Ácido fólico', 'faixa', '2284-8', 'acido folico', 'folato'],
        ['Ferro', 'faixa', '2498-4', 'ferro', 'ferro serico'],
        ['Ferritina', 'faixa', '2276-4', 'ferritina'],
        ['Transferrina', 'faixa', '3034-6', 'transferrina'],
        ['Saturação de transferrina', 'faixa', '2502-3', 'saturacao de transferrina', 'indice de saturacao da transferrina'],
        ['Capacidade de ligação do ferro', 'faixa', '2500-7', 'capacidade de ligacao do ferro', 'tibc', 'ctlf'],
        ['Zinco', 'faixa', '5763-8', 'zinco'],

        // Inflamação
        ['Proteína C reativa', 'baixo', '1988-5', 'proteina c reativa', 'pcr'],
        ['PCR ultrassensível', 'baixo', '30522-7', 'pcr ultrassensivel', 'proteina c reativa ultrassensivel', 'pcr us'],
        ['VHS', 'baixo', '4537-7', 'vhs', 'velocidade de hemossedimentacao'],
        ['Fibrinogênio', 'faixa', '3255-7', 'fibrinogenio'],
        ['Homocisteína', 'baixo', '13965-9', 'homocisteina'],

        // Hormônios
        ['Testosterona total', 'faixa', '2986-8', 'testosterona total', 'testosterona'],
        ['Testosterona livre', 'faixa', '2991-8', 'testosterona livre'],
        ['SHBG', 'faixa', '13967-5', 'shbg', 'globulina ligadora de hormonios sexuais'],
        ['Estradiol', 'faixa', '2243-4', 'estradiol', 'e2'],
        ['Progesterona', 'faixa', '2839-9', 'progesterona'],
        ['FSH', 'faixa', '15067-2', 'fsh', 'hormonio foliculo estimulante'],
        ['LH', 'faixa', '10501-5', 'lh', 'hormonio luteinizante'],
        ['Prolactina', 'faixa', '2842-3', 'prolactina'],
        ['Cortisol', 'faixa', '2143-6', 'cortisol', 'cortisol basal', 'cortisol serico'],
        ['DHEA-S', 'faixa', '2191-5', 'dhea s', 'sulfato de dehidroepiandrosterona', 'dheas'],
        ['IGF-1', 'faixa', '2484-4', 'igf 1', 'somatomedina c'],
        ['PTH', 'faixa', '2731-8', 'pth', 'paratormonio'],
        ['Androstenediona', 'faixa', '1854-9', 'androstenediona'],

        // Cardíaco
        ['Troponina', 'baixo', '10839-9', 'troponina', 'troponina i', 'troponina t'],
        ['CPK', 'faixa', '2157-6', 'cpk', 'ck', 'creatinofosfoquinase'],
        ['CK-MB', 'faixa', '13969-1', 'ck mb'],
        ['NT-proBNP', 'baixo', '33762-6', 'nt probnp', 'probnp', 'bnp'],

        // Próstata
        ['PSA total', 'baixo', '2857-1', 'psa total', 'psa', 'antigeno prostatico especifico'],
        ['PSA livre', 'faixa', '10886-0', 'psa livre'],
        ['Relação PSA livre/total', 'faixa', '12841-3', 'relacao psa livre total', 'psa livre total'],

        // Pâncreas
        ['Amilase', 'faixa', '1798-8', 'amilase'],
        ['Lipase', 'faixa', '3040-3', 'lipase'],

        // Urina
        ['Densidade urinária', 'faixa', '5811-5', 'densidade', 'densidade urinaria'],
        ['pH urinário', 'faixa', '5803-2', 'ph', 'ph urinario'],
        ['Proteínas na urina', 'negativo', '20454-5', 'proteinas na urina', 'proteinuria'],
        ['Nitrito', 'negativo', '5802-4', 'nitrito'],
        ['Leucócitos na urina', 'faixa', '5821-4', 'leucocitos na urina', 'piocitos'],
        ['Hemácias na urina', 'faixa', '13945-1', 'hemacias na urina', 'hematuria'],

        // Sorologias
        ['HIV', 'negativo', '75622-1', 'hiv', 'anti hiv', 'hiv 1 2'],
        ['HBsAg', 'negativo', '5196-1', 'hbsag', 'antigeno de superficie hepatite b'],
        ['Anti-HBs', 'alto', '16935-9', 'anti hbs'],
        ['Anti-HBc', 'negativo', '13952-7', 'anti hbc'],
        ['Anti-HCV', 'negativo', '13955-0', 'anti hcv', 'hepatite c'],
        ['VDRL', 'negativo', '20507-0', 'vdrl', 'sifilis'],
        ['FAN', 'negativo', '5048-4', 'fan', 'fator antinuclear', 'anticorpo antinuclear'],
        ['Fator reumatoide', 'negativo', '11572-5', 'fator reumatoide'],
        ['Toxoplasmose IgG', 'faixa', '22577-1', 'toxoplasmose igg'],
        ['Toxoplasmose IgM', 'negativo', '8039-1', 'toxoplasmose igm'],
        ['Rubéola IgG', 'faixa', '25514-1', 'rubeola igg'],
        ['CMV IgG', 'faixa', '22239-8', 'cmv igg', 'citomegalovirus igg'],

        // Marcadores
        ['CEA', 'baixo', '2039-6', 'cea', 'antigeno carcinoembrionario'],
        ['CA 125', 'baixo', '10334-1', 'ca 125'],
        ['Alfafetoproteína', 'baixo', '1834-1', 'alfafetoproteina', 'afp'],
    ];
}

/**
 * O nome impresso reduzido ao que serve de chave: sem acento, sem a linha de
 * pontos do PDF, sem o material e o método que o laboratório anexa ao nome.
 */
function exam_key(string $printed): string
{
    $s = mb_strtolower(trim($printed));
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);
    // A linha de pontos que liga o nome ao valor, e o que vem depois de vírgula ou travessão.
    $s = (string) preg_replace('/\.{2,}.*$/', '', $s);
    $s = (string) preg_replace('/\s*[,\-–—:]\s*(soro|plasma|sangue|urina|urina de 24h|metodo|método|dosagem).*$/u', '', $s);
    $s = (string) preg_replace('/\b(dosagem|pesquisa|determinacao|quantificacao)\s+(de\s+)?/', '', $s);
    $s = (string) preg_replace('/\((soro|plasma|sangue|urina)\)/', '', $s);
    $s = (string) preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim((string) preg_replace('/\s+/', ' ', $s));
}

/** Nome impresso arrumado para a tela quando o exame não está no dicionário. */
function exam_fallback_name(string $printed): string
{
    $s = trim((string) preg_replace('/\.{2,}\s*$/', '', trim($printed)));
    $s = trim((string) preg_replace('/\s*[:\-–—]\s*$/u', '', $s));
    if ($s === '') {
        return 'Exame sem nome';
    }
    // TUDO EM MAIÚSCULA, como muitos laudos imprimem, fica ilegível em cartão.
    return mb_strtoupper($s, 'UTF-8') === $s ? mb_convert_case($s, MB_CASE_TITLE, 'UTF-8') : $s;
}

/**
 * O exame de uma série: nome curto e direção. A busca é pelo LOINC quando a
 * série tem um forte, e pelo nome impresso normalizado em seguida.
 * @return array{nome:string,direcao:string,conhecido:bool}
 */
function exam_of(array $payload): array
{
    static $porLoinc = null;
    static $porNome = null;
    if ($porLoinc === null) {
        $porLoinc = [];
        $porNome = [];
        foreach (exam_rows() as $linha) {
            [$nome, $direcao, $loinc] = $linha;
            if ($loinc !== '') {
                $porLoinc[$loinc] = [$nome, $direcao];
            }
            foreach (array_slice($linha, 3) as $apelido) {
                $porNome[$apelido] ??= [$nome, $direcao];
            }
        }
    }
    $printed = (string) ($payload['label'] ?? '');
    $loinc = (string) ($payload['loinc'] ?? '');
    $achado = $porLoinc[$loinc] ?? $porNome[exam_key($printed)] ?? null;
    return $achado
        ? ['nome' => $achado[0], 'direcao' => $achado[1], 'conhecido' => true]
        : ['nome' => exam_fallback_name($printed), 'direcao' => 'faixa', 'conhecido' => false];
}

/* ── Faixa de referência e indicador ────────────────────────────────────────
 * A faixa vem sempre do laudo. O laboratório imprime uma ou várias, e quando
 * são várias o campo `condition` diz a quem cada uma se aplica — "mulheres",
 * "acima de 50 anos", "fase folicular". Aqui escolhemos a que casa com o
 * paciente; nada é completado de fora do laudo.
 */

/** Sexo e idade do paciente, só para escolher entre as faixas impressas. */
function patient_context(int $userId): array
{
    $u = row('SELECT profile_enc FROM users WHERE id = ?', [$userId]);
    $p = $u ? (dec($userId, $u['profile_enc']) ?? []) : [];
    $nascimento = (string) ($p['birth_date'] ?? '');
    $idade = null;
    if ($nascimento !== '' && ($d = date_create($nascimento))) {
        $idade = (int) date_diff($d, date_create('today'))->y;
    }
    return ['sexo' => (string) ($p['sex'] ?? ''), 'idade' => $idade, 'user_id' => $userId];
}

/**
 * A condição impressa combina com este paciente? Devolve 1 quando combina por
 * algo dito, 0 quando a condição é vazia (serve para todos) e -1 quando exclui.
 */
function condition_match(string $condition, array $ctx): int
{
    $c = exam_key($condition);
    if ($c === '') {
        return 0;
    }
    $pontos = 0;
    $masculino = (bool) preg_match('/\b(masculino|masculinos|homens|homem|sexo masculino)\b/', $c);
    $feminino = (bool) preg_match('/\b(feminino|femininos|mulheres|mulher|sexo feminino)\b/', $c);
    if ($masculino || $feminino) {
        if ($ctx['sexo'] === '') {
            return -1;
        }
        if (($masculino && $ctx['sexo'] !== 'male') || ($feminino && $ctx['sexo'] !== 'female')) {
            return -1;
        }
        $pontos++;
    }
    if ($ctx['idade'] !== null && preg_match_all('/(\d{1,3})\s*(?:a|ate|e)\s*(\d{1,3})\s*anos|(?:acima de|maior que|apos|>)\s*(\d{1,3})\s*anos|(?:ate|abaixo de|menor que|<)\s*(\d{1,3})\s*anos/', $c, $m, PREG_SET_ORDER)) {
        $idade = $ctx['idade'];
        foreach ($m as $faixa) {
            if ($faixa[1] !== '' && $faixa[2] !== '') {
                if ($idade < (int) $faixa[1] || $idade > (int) $faixa[2]) {
                    return -1;
                }
            } elseif (($faixa[3] ?? '') !== '') {
                if ($idade <= (int) $faixa[3]) {
                    return -1;
                }
            } elseif (($faixa[4] ?? '') !== '') {
                if ($idade >= (int) $faixa[4]) {
                    return -1;
                }
            }
            $pontos++;
        }
    }
    return $pontos > 0 ? 1 : 0;
}

/**
 * A faixa impressa que se aplica ao paciente: a mais específica que não o
 * exclui. Sem nenhuma que sirva, devolve null e o cartão mostra o texto impresso.
 */
function pick_range(array $ranges, array $ctx): ?array
{
    $melhor = null;
    $melhorPonto = -1;
    foreach ($ranges as $r) {
        if (!is_array($r) || !in_array($r['kind'] ?? '', ['interval', 'upper', 'lower'], true)) {
            continue;
        }
        if (($r['low'] ?? null) === null && ($r['high'] ?? null) === null) {
            continue;
        }
        $ponto = condition_match((string) ($r['condition'] ?? ''), $ctx);
        if ($ponto < 0 || $ponto <= $melhorPonto) {
            continue;
        }
        $melhor = $r;
        $melhorPonto = $ponto;
    }
    return $melhor;
}

/** Quanto o valor está fora da faixa: 0 quando dentro. */
function range_distance(float $v, array $r): float
{
    $low = $r['low'] ?? null;
    $high = $r['high'] ?? null;
    if ($low !== null && $v < (float) $low) {
        return (float) $low - $v;
    }
    if ($high !== null && $v > (float) $high) {
        return $v - (float) $high;
    }
    return 0.0;
}

/** 'dentro', 'acima', 'abaixo' — ou null quando não há faixa utilizável. */
function range_position(?float $v, ?array $r): ?string
{
    if ($v === null || $r === null) {
        return null;
    }
    if (($r['high'] ?? null) !== null && $v > (float) $r['high']) {
        return 'acima';
    }
    if (($r['low'] ?? null) !== null && $v < (float) $r['low']) {
        return 'abaixo';
    }
    return 'dentro';
}

/** Negativo/não reagente é o saudável; positivo/reagente, não. */
function qualitative_sign(string $texto): ?string
{
    $t = exam_key($texto);
    if ($t === '') {
        return null;
    }
    if (preg_match('/\b(negativo|negativa|nao reagente|nao reativo|ausente|ausencia|nao detectado|indetectavel|normal)\b/', $t)) {
        return 'bom';
    }
    if (preg_match('/\b(positivo|positiva|reagente|reativo|presente|detectado|detectavel|anormal)\b/', $t)) {
        return 'ruim';
    }
    return null;
}

/** A faixa escolhida em texto curto; sem faixa estruturada, o que o laudo imprimiu. */
function range_text(?array $faixa, ?string $impresso, string $unidade = ''): string
{
    if ($faixa === null) {
        return trim((string) $impresso) !== '' ? trim((string) $impresso) : '';
    }
    $low = $faixa['low'] ?? null;
    $high = $faixa['high'] ?? null;
    $u = $unidade !== '' ? ' ' . $unidade : '';
    $texto = match (true) {
        $low !== null && $high !== null => br_number((float) $low) . ' a ' . br_number((float) $high) . $u,
        $high !== null => 'até ' . br_number((float) $high) . $u,
        default => 'acima de ' . br_number((float) $low) . $u,
    };
    $cond = trim((string) ($faixa['condition'] ?? ''));
    return $cond !== '' ? $texto . ' (' . $cond . ')' : $texto;
}

/**
 * O indicador de um cartão: o valor de agora, a seta contra o resultado
 * anterior e a cor que diz se a mudança foi para melhor ou para pior.
 *
 * A cor não sai de uma opinião por exame. Quando o laudo traz faixa, ela sai
 * de ter andado para dentro ou para fora dela — o que acerta sozinho LDL que
 * sobe e HDL que sobe. Só onde a direção é inequívoca o dicionário a fixa.
 * Sem faixa e sem direção fixada, a seta vai sem cor: não dá para dizer.
 *
 * @return array{tipo:string,valor:string,unidade:string,seta:string,cor:string,legenda:string,faixa:string,posicao:?string}
 */
function exam_indicator(array $series, array $ctx): array
{
    $exame = exam_of($series['payload'] ?? []);
    $pontos = $series['points'] ?? [];
    $ultimo = $series['last'] ?? null;
    $anterior = count($pontos) >= 2 ? $pontos[count($pontos) - 2] : null;
    $unidade = trim((string) ($ultimo['unit'] ?? ''));
    $faixa = pick_range($ultimo['ranges'] ?? [], $ctx);
    // Laudo sem faixa: vale a que este mesmo laboratório imprimiu antes, dito na tela.
    $doCatalogo = false;
    if ($faixa === null && ($ctx['user_id'] ?? 0) > 0) {
        $faixa = pick_range(catalogued_ranges((int) $ctx['user_id'], $ultimo['lab'] ?? null,
            (string) ($series['payload']['label'] ?? ''), $unidade), $ctx);
        $doCatalogo = $faixa !== null;
    }
    $textoFaixa = range_text($faixa, $ultimo['reference_printed'] ?? null, $unidade)
        . ($doCatalogo ? ' · de um laudo anterior deste laboratório' : '');

    $agora = is_numeric($ultimo['value'] ?? null) ? (float) $ultimo['value'] : null;
    if ($agora === null) {
        $impresso = trim((string) ($ultimo['value_printed'] ?? $ultimo['value_text'] ?? ''));
        $sinal = qualitative_sign($impresso) ?? ($exame['direcao'] === 'negativo' ? 'neutro' : 'neutro');
        return ['tipo' => 'qualitativo', 'valor' => $impresso !== '' ? $impresso : '—', 'unidade' => '',
            'seta' => '', 'cor' => $sinal, 'legenda' => $sinal === 'bom' ? 'resultado negativo' : ($sinal === 'ruim' ? 'resultado positivo' : 'resultado sem leitura automática'),
            'faixa' => $textoFaixa, 'posicao' => null];
    }

    $posicao = range_position($agora, $faixa);
    $antes = $anterior !== null && is_numeric($anterior['value'] ?? null) ? (float) $anterior['value'] : null;
    $seta = '';
    $cor = 'neutro';
    $legenda = 'primeiro resultado';
    if ($antes !== null) {
        $delta = $agora - $antes;
        $igual = abs($delta) <= 1e-9 * max(1.0, abs($agora));
        $seta = $igual ? '→' : ($delta > 0 ? '↑' : '↓');
        $legenda = $igual ? 'igual ao anterior' : ($delta > 0 ? 'subiu' : 'desceu')
            . ' desde ' . br_date((string) ($anterior['date'] ?? ''));
        if ($igual) {
            $cor = 'neutro';
        } elseif ($exame['direcao'] === 'baixo') {
            $cor = $delta < 0 ? 'bom' : 'ruim';
        } elseif ($exame['direcao'] === 'alto') {
            $cor = $delta > 0 ? 'bom' : 'ruim';
        } elseif ($faixa !== null) {
            $dAgora = range_distance($agora, $faixa);
            $dAntes = range_distance($antes, $faixa);
            $cor = $dAgora < $dAntes ? 'bom' : ($dAgora > $dAntes ? 'ruim' : 'neutro');
        }
    }
    return ['tipo' => 'numero', 'valor' => br_number($agora), 'unidade' => $unidade,
        'seta' => $seta, 'cor' => $cor, 'legenda' => $legenda, 'faixa' => $textoFaixa, 'posicao' => $posicao];
}


/* ── Catálogo de faixas por laboratório ─────────────────────────────────────
 * O mesmo laboratório imprime a mesma faixa para o mesmo exame em todo laudo.
 * Guardar o que ele já imprimiu deixa o cartão mostrar uma faixa quando um
 * laudo chega sem ela — e continua sendo faixa do laboratório, não minha.
 */

/** A chave de laboratório: o nome como impresso, normalizado. */
function lab_key(?string $laboratory): string
{
    return exam_key((string) $laboratory);
}

/** Guarda as faixas estruturadas que este resultado trouxe. */
function remember_lab_ranges(int $userId, array $payload): void
{
    $lab = lab_key($payload['laboratory'] ?? null);
    $exame = exam_key((string) ($payload['name_printed'] ?? ''));
    $unidade = exam_key((string) ($payload['unit_printed'] ?? ''));
    if ($lab === '' || $exame === '') {
        return;
    }
    foreach ($payload['reference_ranges'] ?? [] as $r) {
        if (!is_array($r) || !in_array($r['kind'] ?? '', ['interval', 'upper', 'lower'], true)) {
            continue;
        }
        if (($r['low'] ?? null) === null && ($r['high'] ?? null) === null) {
            continue;
        }
        $cond = exam_key((string) ($r['condition'] ?? ''));
        q('INSERT INTO lab_ranges(user_id, lab_key, exam_key, unit_key, condition_key, payload_enc, seen, first_seen, last_seen)
            VALUES (?,?,?,?,?,?,1,?,?)
            ON CONFLICT(user_id, lab_key, exam_key, unit_key, condition_key)
            DO UPDATE SET payload_enc = excluded.payload_enc, seen = seen + 1, last_seen = excluded.last_seen',
            [$userId, $lab, $exame, $unidade, $cond, enc($userId, $r), now(), now()]);
    }
}

/**
 * As faixas que este laboratório já imprimiu para este exame, mais vistas
 * primeiro. Serve de recurso quando o laudo da vez não trouxe faixa nenhuma.
 * @return list<array>
 */
function catalogued_ranges(int $userId, ?string $laboratory, string $printedName, ?string $unit): array
{
    $lab = lab_key($laboratory);
    $exame = exam_key($printedName);
    if ($lab === '' || $exame === '') {
        return [];
    }
    $out = [];
    foreach (rows('SELECT payload_enc FROM lab_ranges WHERE user_id = ? AND lab_key = ? AND exam_key = ? AND unit_key = ?
        ORDER BY seen DESC, last_seen DESC', [$userId, $lab, $exame, exam_key((string) $unit)]) as $r) {
        $out[] = dec($userId, $r['payload_enc']);
    }
    return $out;
}
