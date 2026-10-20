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
    $u = str_replace(['µ', 'μ', '³', '²', ' '], ['u', 'u', '3', '2', ''], $u);
    $lower = mb_strtolower($u);
    $special = [
        'mmhg' => '[mm_Hg]', 'kg/m2' => 'kg/m2', 'anos' => 'a', 'ano' => 'a', 'graus' => 'deg', '°' => 'deg', 'º' => 'deg',
        'ohm' => 'Ohm', 'ohms' => 'Ohm', 'ω' => 'Ohm', 'kcal' => 'kcal', 'kcal/dia' => 'kcal/d', 'kcal/d' => 'kcal/d',
        'mil/mm3' => '10*3/mm3', 'milhões/mm3' => '10*6/mm3', 'milhoes/mm3' => '10*6/mm3', '/mm3' => '/mm3',
        'mm/h' => 'mm/h', 'mm/1ahora' => 'mm/h', 'pol' => '[in_i]', 'lb' => '[lb_av]', 'nível' => '1', 'nivel' => '1', 'índice' => '1',
    ];
    if (isset($special[$lower])) {
        return $special[$lower];
    }
    // International units: UI / IU in any case, with prefixes µ (u) or m.
    $u = preg_replace('/(?<![A-Za-z])([um]?)(UI|IU|ui)(?![A-Za-z])/', '$1[IU]', $u);
    $u = preg_replace('/m[Ee][Qq]/', 'meq', $u);
    $u = preg_replace('/(?<![A-Za-z])(\d+)\^(\d+)/', '$1*$2', $u);
    // Litre terms: dl → dL, ml → mL, l → L (but never the "l" of "mol").
    $u = preg_replace('/(^|[\/.])([dmunpckf]?)l(?=$|[\/.])/', '$1$2L', $u);
    return ucum_parse($u) === null ? null : $u;
}

/** @return array{0:float,1:array<string,int>}|null */
function ucum_parse(string $unit): ?array
{
    $unit = trim($unit);
    if ($unit === '') {
        return null;
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
