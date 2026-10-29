<?php
declare(strict_types=1);

/*
 * A small UCUM subset: parses the units that show up on Brazilian lab and body
 * composition reports into (factor, dimension vector) and converts only between
 * commensurable units. Conversion also requires the same LOINC property (RN-05):
 * the algebra alone would turn 6.5 % into 65 mmol/mol, which is wrong (it is 48).
 */

/** Base units: symbol => [factor to coherent unit, dimensions]. */
function ucum_atoms(): array
{
    return [
        'g' => [1.0, ['M' => 1]],
        'L' => [1.0, ['V' => 1]],
        'l' => [1.0, ['V' => 1]],
        'm' => [1.0, ['Len' => 1]],
        'mol' => [1.0, ['N' => 1]],
        'eq' => [1.0, ['EQ' => 1]],
        '[IU]' => [1.0, ['IU' => 1]],
        'U' => [1.0, ['U' => 1]],
        'kat' => [1.0, ['KAT' => 1]],
        's' => [1.0, ['T' => 1]],
        'min' => [60.0, ['T' => 1]],
        'h' => [3600.0, ['T' => 1]],
        'd' => [86400.0, ['T' => 1]],
        'mo' => [2629800.0, ['T' => 1]],
        'a' => [31557600.0, ['T' => 1]],
        '%' => [0.01, []],
        '1' => [1.0, []],
        '[mm_Hg]' => [1.0, ['PRESS' => 1]],
        'Ohm' => [1.0, ['OHM' => 1]],
        'kcal' => [1.0, ['ENERGY' => 1]],
        'cal' => [0.001, ['ENERGY' => 1]],
        'deg' => [1.0, ['ANGLE' => 1]],
        '[in_i]' => [0.0254, ['Len' => 1]],
        '[lb_av]' => [453.59237, ['M' => 1]],
        '{cells}' => [1.0, []],
    ];
}

function ucum_prefixes(): array
{
    return ['f' => 1e-15, 'p' => 1e-12, 'n' => 1e-9, 'u' => 1e-6, 'm' => 1e-3, 'c' => 1e-2, 'd' => 1e-1, 'k' => 1e3, 'M' => 1e6];
}

/**
 * Turns a unit as printed on a Brazilian report into UCUM, or null when unknown.
 * µUI/mL → u[IU]/mL, mil/mm³ → 10*3/mm3, mEq/L → meq/L.
 */
function ucum_from_printed(?string $printed): ?string
{
    if ($printed === null) {
        return null;
    }
    $u = trim($printed);
    if ($u === '') {
        return null;
    }
    // O leitor às vezes traz a conversão entre parênteses junto da unidade: "mg/L (0,876 mg/dL)".
    $u = trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $u));
    // Potência de dez com expoente alto, e o "x" que o laudo põe antes dela: "x 10³/mm³".
    $u = strtr($u, ['⁰' => '^0', '¹' => '^1', '²' => '^2', '³' => '^3', '⁴' => '^4', '⁵' => '^5',
        '⁶' => '^6', '⁷' => '^7', '⁸' => '^8', '⁹' => '^9']);
    $u = (string) preg_replace('/^\s*[xX×]\s*(?=\d)/u', '', $u);
    $u = str_replace(['µ', 'μ', ' '], ['u', 'u', ''], $u);
    // 10^3 é a potência de dez do UCUM; mm^3 e m^2 são expoentes presos à letra.
    $u = (string) preg_replace('/(?<![A-Za-z])(\d+)\^(\d+)/', '$1*$2', $u);
    $u = (string) preg_replace('/([A-Za-z\]}%])\^(-?\d+)/', '$1$2', $u);
    $lower = mb_strtolower($u);
    $special = [
        'mmhg' => '[mm_Hg]', 'kg/m2' => 'kg/m2', 'anos' => 'a', 'ano' => 'a', 'graus' => 'deg', '°' => 'deg', 'º' => 'deg',
        'ohm' => 'Ohm', 'ohms' => 'Ohm', 'ω' => 'Ohm', 'kcal' => 'kcal', 'kcal/dia' => 'kcal/d', 'kcal/d' => 'kcal/d',
        'mil/mm3' => '10*3/mm3', 'milhões/mm3' => '10*6/mm3', 'milhoes/mm3' => '10*6/mm3', '/mm3' => '/mm3',
        'mm/h' => 'mm/h', 'mm/1ahora' => 'mm/h', 'mm/1h' => 'mm/h', 'mm/1hora' => 'mm/h',
        'mm/1ªhora' => 'mm/h', 'mm/1ª.hora' => 'mm/h', 'mm/hora' => 'mm/h', 'mm/primeirahora' => 'mm/h',
        // A área corporal de 1,73 m² é anotação, não dimensão: diz a quem a taxa foi normalizada.
        'ml/min/1,73m2' => 'mL/min/{1.73_m2}', 'ml/min/1.73m2' => 'mL/min/{1.73_m2}',
        'ml/min/1,73' => 'mL/min/{1.73_m2}', 'ml/min/1,73m' => 'mL/min/{1.73_m2}',
        'pol' => '[in_i]', 'lb' => '[lb_av]', 'nível' => '1', 'nivel' => '1', 'índice' => '1',
    ];
    if (isset($special[$lower])) {
        return $special[$lower];
    }
    // International units: UI / IU in any case, with prefixes µ (u) or m.
    $u = preg_replace('/(?<![A-Za-z])([um]?)(UI|IU|ui)(?![A-Za-z])/', '$1[IU]', $u);
    $u = preg_replace('/m[Ee][Qq]/', 'meq', $u);
    // Litre terms: dl → dL, ml → mL, l → L (but never the "l" of "mol").
    $u = preg_replace('/(^|[\/.])([dmunpckf]?)l(?=$|[\/.])/', '$1$2L', $u);
    return ucum_parse($u) === null ? null : $u;
}

/* ── A dimensão na tela e o número dentro do que foi impresso ───────────── */

/**
 * A tabela de dimensões: uma grafia de tela por unidade. O mesmo exame chega escrito
 * "g/dl", "g/dL" e "G/DL" em laudos diferentes, e o cartão precisa mostrar uma forma só.
 * A chave é o UCUM, de modo que todas as grafias de uma unidade caem na mesma linha.
 *
 * @return array<string,string> UCUM => grafia
 */
function unit_table(): array
{
    return [
        'mg/dL' => 'mg/dL', 'g/dL' => 'g/dL', 'mg/L' => 'mg/L', 'g/L' => 'g/L', 'ug/dL' => 'µg/dL',
        'ug/L' => 'µg/L', 'ng/mL' => 'ng/mL', 'ng/dL' => 'ng/dL', 'pg/mL' => 'pg/mL', 'pg' => 'pg',
        'fL' => 'fL', 'mg/mL' => 'mg/mL', 'ug/mL' => 'µg/mL',
        'U/L' => 'U/L', 'mU/L' => 'mU/L', 'U/mL' => 'U/mL', 'kat/L' => 'kat/L',
        '[IU]/mL' => 'UI/mL', 'm[IU]/mL' => 'mUI/mL', 'u[IU]/mL' => 'µUI/mL',
        '[IU]/L' => 'UI/L', 'm[IU]/L' => 'mUI/L', 'u[IU]/L' => 'µUI/L',
        'mol/L' => 'mol/L', 'mmol/L' => 'mmol/L', 'umol/L' => 'µmol/L', 'nmol/L' => 'nmol/L',
        'pmol/L' => 'pmol/L', 'mmol/mol' => 'mmol/mol', 'meq/L' => 'mEq/L',
        '%' => '%', '1' => '',
        '/mm3' => '/mm³', '10*3/mm3' => 'mil/mm³', '10*6/mm3' => 'milhões/mm³',
        '10*3/uL' => 'mil/µL', '10*6/uL' => 'milhões/µL', '/uL' => '/µL',
        'mm/h' => 'mm/h', 'mL/min/{1.73_m2}' => 'mL/min/1,73m²', 'mL/min' => 'mL/min',
        'g' => 'g', 'kg' => 'kg', 'cm' => 'cm', 'm' => 'm', 'kg/m2' => 'kg/m²',
        '[mm_Hg]' => 'mmHg', 'Ohm' => 'Ω', 'kcal' => 'kcal', 'kcal/d' => 'kcal/dia',
        's' => 's', 'min' => 'min', 'h' => 'h', 'd' => 'dias', 'a' => 'anos', 'deg' => 'graus',
    ];
}

/**
 * A grafia de tela desta unidade. Quando a tabela não conhece a dimensão, vale o que o
 * laboratório imprimiu: inventar uma grafia seria afirmar o que o laudo não diz.
 */
function unit_label(?string $printed): string
{
    $p = trim((string) $printed);
    if ($p === '') {
        return '';
    }
    $ucum = ucum_from_printed($p);
    return $ucum === null ? $p : (unit_table()[$ucum] ?? $p);
}

/**
 * Separa um resultado como impresso em rótulo, operador, número e dimensão. O laudo
 * imprime as quatro coisas numa célula só ("RESULTADO: < 0,01 µUI/mL") e cada leitor
 * divide isso de um jeito. A dimensão é aceita apenas quando a tabela de unidades a
 * reconhece, para que um trecho de frase ou o início de uma faixa de referência não passe
 * por unidade.
 *
 * numbers conta quantos números o texto traz: um resultado tem um; uma linha de tabela que
 * desabou numa célula tem vários, e aí não se pode dizer qual deles é o resultado.
 *
 * @return array{operator:string,number:?float,number_printed:string,unit:string,rest:string,clean:string,numbers:int}
 */
function split_printed(?string $printed): array
{
    $texto = trim((string) $printed);
    // O rótulo da célula vem colado no valor em alguns laudos.
    $limpo = trim((string) preg_replace('/^\s*(resultados?|valor(?:es)?|result)\s*[:.\x{2013}-]\s*/iu', '', $texto));
    $op = '';
    if (preg_match('/^(<=|>=|<|>|\x{2264}|\x{2265})\s*/u', $limpo, $m)) {
        $op = ["\u{2264}" => '<=', "\u{2265}" => '>='][$m[1]] ?? $m[1];
        $limpo = trim(mb_substr($limpo, mb_strlen($m[0])));
    }
    $conta = fn (string $t): int => (int) preg_match_all('/\d+(?:[.,]\d+)*/u', $t);
    if (!preg_match('/^(\d+(?:[.,]\d+)*)/u', $limpo, $m)) {
        return ['operator' => $op, 'number' => null, 'number_printed' => '', 'unit' => '',
            'rest' => $limpo, 'clean' => $limpo, 'numbers' => $conta($limpo)];
    }
    $impresso = $m[1];
    $resto = trim(mb_substr($limpo, mb_strlen($impresso)));
    $unidade = '';
    if (unit_like($resto)) {
        $unidade = $resto;
        $resto = '';
    }
    return ['operator' => $op, 'number' => br_parse_number($impresso), 'number_printed' => $impresso,
        'unit' => $unidade, 'rest' => $resto, 'clean' => $limpo, 'numbers' => 1 + $conta($resto)];
}

/**
 * Este trecho de texto é uma unidade? Exige uma letra ou um por cento, nenhum número solto
 * e o aval do parser. Sem a regra do número solto, o "a 99" de uma faixa de referência
 * passaria por unidade: o UCUM lê "a99" como ano elevado a 99.
 */
function unit_like(string $trecho): bool
{
    $t = trim($trecho);
    if ($t === '' || mb_strlen($t) > 24 || !preg_match('/[A-Za-z\x{00b5}\x{03bc}%]/u', $t)) {
        return false;
    }
    if (preg_match('/(^|\s)\d+(?:[.,]\d+)*(\s|$)/u', $t)) {
        return false;
    }
    return ucum_from_printed($t) !== null;
}

/**
 * O valor como o laboratório imprimiu, com a dimensão uma vez só. Muitos laudos imprimem
 * valor e unidade na mesma célula ("182 mg/dL") e o leitor copia as duas coisas, de modo
 * que repetir o campo da unidade ao lado dava "182 mg/dL mg/dL" na tela.
 */
function printed_with_unit(?string $printed, ?string $unit): string
{
    $texto = trim((string) $printed);
    $u = trim((string) $unit);
    if ($u === '' || $texto === '') {
        return $texto !== '' ? $texto : $u;
    }
    if (stripos($texto, $u) !== false) {
        return $texto;
    }
    $dentro = split_printed($texto)['unit'];
    $ucum = ucum_from_printed($u);
    if ($dentro !== '' && $ucum !== null && ucum_from_printed($dentro) === $ucum) {
        return $texto;
    }
    return $texto . ' ' . $u;
}

/** @return array{0:float,1:array<string,int>}|null */
function ucum_parse(string $unit): ?array
{
    $unit = trim($unit);
    if ($unit === '') {
        return null;
    }
    // Anotações entre chaves não têm dimensão nem fator: "{1.73_m2}" apenas diz a quem o valor
    // foi normalizado. Saem antes da divisão em termos, que usa o ponto como produto.
    $unit = (string) preg_replace('/\{[^}]*\}/', '', $unit);
    $unit = rtrim($unit, '/.');
    if ($unit === '') {
        return [1.0, []];
    }
    $factor = 1.0;
    $dims = [];
    // Split on "/" keeping the first term in the numerator; "." multiplies.
    $parts = explode('/', $unit);
    foreach ($parts as $i => $part) {
        $sign = $i === 0 ? 1 : -1;
        if ($part === '' && $i === 0) {
            continue; // "/mm3"
        }
        foreach (explode('.', $part) as $term) {
            $t = ucum_term($term);
            if ($t === null) {
                return null;
            }
            $factor = $sign > 0 ? $factor * $t[0] : $factor / $t[0];
            foreach ($t[1] as $d => $e) {
                $dims[$d] = ($dims[$d] ?? 0) + $sign * $e;
                if ($dims[$d] === 0) {
                    unset($dims[$d]);
                }
            }
        }
    }
    ksort($dims);
    return [$factor, $dims];
}

/** @return array{0:float,1:array<string,int>}|null */
function ucum_term(string $term): ?array
{
    if ($term === '') {
        return null;
    }
    if (preg_match('/^10\*(-?\d+)$/', $term, $m)) {
        return [10 ** (int) $m[1], []];
    }
    if (preg_match('/^\d+(\.\d+)?$/', $term)) {
        return [(float) $term, []];
    }
    $exp = 1;
    if (preg_match('/^(.*?[A-Za-z\]}%])(-?\d+)$/', $term, $m)) {
        $term = $m[1];
        $exp = (int) $m[2];
    }
    $atoms = ucum_atoms();
    $base = null;
    if (isset($atoms[$term])) {
        $base = $atoms[$term];
    } else {
        foreach (ucum_prefixes() as $p => $pf) {
            if (str_starts_with($term, $p) && isset($atoms[substr($term, strlen($p))]) && !in_array(substr($term, strlen($p)), ['%', '1', '{cells}'], true)) {
                $a = $atoms[substr($term, strlen($p))];
                $base = [$a[0] * $pf, $a[1]];
                break;
            }
        }
    }
    if ($base === null) {
        return null;
    }
    $dims = [];
    foreach ($base[1] as $d => $e) {
        $dims[$d] = $e * $exp;
    }
    // Length cubed is volume for the purposes of concentration (mm3 = µL).
    // 1 m3 = 1000 L, so mm3 = 1e-9 m3 = 1e-6 L (= µL).
    if (count($dims) === 1 && isset($dims['Len']) && $dims['Len'] % 3 === 0) {
        $k = intdiv($dims['Len'], 3);
        return [($base[0] ** $exp) * (1000 ** $k), ['V' => $k]];
    }
    return [$base[0] ** $exp, $dims];
}

/** Dimension each LOINC property may carry. Unknown properties are not checked. */
function property_dims(?string $property): ?array
{
    $map = [
        'MCnc' => ['M' => 1, 'V' => -1],
        'SCnc' => ['N' => 1, 'V' => -1],
        'CCnc' => ['U' => 1, 'V' => -1],
        'ACnc' => ['IU' => 1, 'V' => -1],
        'NCnc' => ['V' => -1],
        'MFr' => [], 'NFr' => [], 'SFr' => [], 'VFr' => [], 'Ratio' => [], 'MRto' => [], 'SRto' => [], 'Rto' => [],
        'Mass' => ['M' => 1],
        'Len' => ['Len' => 1],
        'EntVol' => ['V' => 1],
        'VRat' => ['V' => 1, 'T' => -1],
        'Time' => ['T' => 1],
        'Vel' => ['Len' => 1, 'T' => -1],
        'Pres' => ['PRESS' => 1],
        'EntMCnc' => ['M' => 1, 'V' => -1],
        'EntMass' => ['M' => 1],
        'SubstConcRto' => [],
    ];
    if ($property === null || !isset($map[$property])) {
        return null;
    }
    $d = $map[$property];
    ksort($d);
    return $d;
}

/**
 * Unit/property compatibility for the cross-check (RN-02).
 * 'ok' | 'mismatch' | 'unverified' (unit or property unknown).
 */
function unit_matches_property(?string $ucum, ?string $property): string
{
    $parsed = $ucum ? ucum_parse($ucum) : null;
    $want = property_dims($property);
    if ($parsed === null || $want === null) {
        return 'unverified';
    }
    $have = $parsed[1];
    // Enzyme activity is reported in U (µmol/min) or kat; both count as catalytic.
    if ($property === 'CCnc' && isset($have['KAT'])) {
        $have['U'] = $have['KAT'];
        unset($have['KAT']);
        ksort($have);
    }
    // Arbitrary-unit concentrations (ACnc) also come as {index}/mL or U/mL on some kits.
    if ($property === 'ACnc' && (isset($have['U']) || ($have === ['V' => -1]))) {
        return 'ok';
    }
    return $have == $want ? 'ok' : 'mismatch';
}

/**
 * Converts a value only when both units parse, share dimensions and the LOINC
 * properties are equal and known. Returns null when conversion is not allowed.
 */
function ucum_convert(float $value, ?string $from, ?string $to, ?string $propertyFrom, ?string $propertyTo): ?float
{
    if ($from === null || $to === null || $propertyFrom === null || $propertyFrom !== $propertyTo || property_dims($propertyFrom) === null) {
        return null;
    }
    if ($from === $to) {
        return $value;
    }
    $a = ucum_parse($from);
    $b = ucum_parse($to);
    if ($a === null || $b === null || $a[1] != $b[1]) {
        return null;
    }
    return $value * $a[0] / $b[0];
}
