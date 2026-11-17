<?php
declare(strict_types=1);

require_once __DIR__ . '/score.php';

function lead_body(string $email, float $points, array $failedIds, string $title, string $consent, string $when): string
{
    $failed = [];
    foreach ($failedIds as $id) {
        $failed[] = (string) $id;
    }

    return implode("\n", [
        'Data: ' . $when,
        'E-mail: ' . $email,
        'Nota: ' . format_points($points),
        'Checagens: ' . implode(', ', $failed),
        'Cargo: ' . $title,
        'Termo: ' . $consent,
    ]) . "\n";
}

function rate_limit_allow(string $store, string $ip, int $now): bool
{
    $path = rate_limit_path($store, $ip);
    if ($path === null) {
        return false;
    }
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        return false;
    }

    $locked = false;
    try {
        if (!flock($handle, LOCK_EX)) {
            return false;
        }
        $locked = true;
        $raw = stream_get_contents($handle);
        if (!is_string($raw)) {
            $raw = '';
        }
        $kept = rate_limit_kept($raw, $now);
        $inWindow = 0;
        foreach ($kept as $stamp) {
            if ($stamp <= $now) {
                $inWindow++;
            }
        }
        if ($inWindow >= 10) {
            return false;
        }
        $kept[] = $now;
        $payload = implode("\n", $kept) . "\n";
        rewind($handle);
        if (!ftruncate($handle, 0)) {
            return false;
        }
        rewind($handle);
        $written = fwrite($handle, $payload);
        if ($written !== strlen($payload)) {
            return false;
        }
        fflush($handle);
        return true;
    } finally {
        if ($locked) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
}

/** The IP is hashed so slashes or ".." in it cannot leave $store. */
function rate_limit_path(string $store, string $ip): ?string
{
    if ($store === '' || !is_dir($store)) {
        return null;
    }
    $dir = realpath($store);
    if ($dir === false) {
        return null;
    }
    $name = hash('sha256', $ip);
    if (preg_match('/\A[0-9a-f]{64}\z/', $name) !== 1) {
        return null;
    }
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    if (dirname($path) !== $dir) {
        return null;
    }
    return $path;
}

/** @return list<int> */
function rate_limit_kept(string $raw, int $now): array
{
    $lines = preg_split('/\R/', $raw);
    if ($lines === false) {
        return [];
    }
    $floor = $now - 3600;
    $kept = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/\A[0-9]{1,18}\z/', $line) !== 1) {
            continue;
        }
        $stamp = (int) $line;
        if ($stamp >= $floor) {
            $kept[] = $stamp;
        }
    }
    return $kept;
}
