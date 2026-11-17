<?php
declare(strict_types=1);

/**
 * @return array{text:string, pages:int, linear:bool, readable:bool, valid_docx:bool}
 */
function extract_file(string $path, string $extension, ?callable $runner = null): array
{
    // Docx ignores $runner. A null runner reads PDF with proc_open.
    if ($extension === 'docx') {
        return extract_docx($path);
    }
    if ($extension === 'pdf') {
        return extract_pdf($path, $runner ?? extract_command(...));
    }
    return extract_unreadable();
}

function docx_is_linear(string $documentXml): bool
{
    if (str_contains($documentXml, 'w:tbl') || str_contains($documentXml, 'w:drawing')) {
        return false;
    }
    $found = preg_match_all('/<w:cols\b[^>]*>/', $documentXml, $tags);
    if (!is_int($found) || $found < 1) {
        return true;
    }
    foreach ($tags[0] as $tag) {
        if (preg_match('/\bw:num=(["\'])(\d+)\1/', $tag, $num) === 1 && (int) $num[2] > 1) {
            return false;
        }
    }
    return true;
}

/** @return array{text:string, pages:int, linear:bool, readable:bool, valid_docx:bool} */
function extract_unreadable(): array
{
    return [
        'text' => '',
        'pages' => 0,
        'linear' => false,
        'readable' => false,
        'valid_docx' => false,
    ];
}

/** @return array{text:string, pages:int, linear:bool, readable:bool, valid_docx:bool} */
function extract_docx(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return extract_unreadable();
    }
    $zip = new ZipArchive();
    $opened = $zip->open($path);
    if ($opened !== true) {
        return extract_unreadable();
    }
    $stat = $zip->statName('word/document.xml');
    $size = is_array($stat) ? ($stat['size'] ?? null) : null;
    // Zip64 sizes above the signed range come back negative and would skip a "> cap" check.
    if (docx_stat_refused($size)) {
        $zip->close();
        return extract_unreadable();
    }
    $xml = $zip->getFromName('word/document.xml', docx_xml_limit());
    $zip->close();
    if (!is_string($xml) || docx_declares_entity($xml)) {
        return extract_unreadable();
    }
    $text = docx_plain_text($xml);
    if (!is_string($text) || strlen($text) > docx_xml_limit()) {
        return extract_unreadable();
    }
    return [
        'text' => $text,
        'pages' => 0,
        'linear' => docx_is_linear($xml),
        'readable' => true,
        'valid_docx' => true,
    ];
}

function docx_xml_limit(): int
{
    return 8388608;
}

function docx_stat_refused(mixed $size): bool
{
    return !is_int($size) || $size < 0 || $size > docx_xml_limit();
}

function docx_declares_entity(string $xml): bool
{
    if (strlen($xml) > docx_xml_limit()) {
        return true;
    }
    // UTF-16 stores ASCII as interleaved NUL bytes. Stripping them reveals the declaration.
    $scan = str_contains($xml, "\0") ? str_replace("\0", '', $xml) : $xml;
    return stripos($scan, '<!DOCTYPE') !== false || stripos($scan, '<!ENTITY') !== false;
}

function docx_plain_text(string $documentXml): ?string
{
    if (trim($documentXml) === '') {
        return '';
    }
    if (docx_declares_entity($documentXml)) {
        return null;
    }
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($documentXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if ($loaded !== true) {
        $stripped = docx_strip_tags($documentXml);
        return strlen($stripped) > docx_xml_limit() ? null : $stripped;
    }
    $doctype = $dom->doctype;
    if ($doctype instanceof DOMDocumentType && $doctype->parentNode !== null) {
        $doctype->parentNode->removeChild($doctype);
    }

    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $lines = [];
    $used = 0;
    $limit = docx_xml_limit();
    foreach ($dom->getElementsByTagNameNS($ns, 'p') as $paragraph) {
        $line = docx_node_text($paragraph, $ns, true);
        if ($paragraph instanceof DOMElement && docx_paragraph_numbered($paragraph, $ns)) {
            $line = docx_list_line($line);
        }
        $used += strlen($line) + 1;
        if ($used > $limit) {
            return null;
        }
        $lines[] = $line;
    }
    return implode("\n", $lines);
}

function docx_paragraph_numbered(DOMElement $paragraph, string $ns): bool
{
    foreach ($paragraph->childNodes as $child) {
        if (!$child instanceof DOMElement || $child->namespaceURI !== $ns || $child->localName !== 'pPr') {
            continue;
        }
        if ($child->getElementsByTagNameNS($ns, 'numPr')->length > 0) {
            return true;
        }
    }
    return false;
}

function docx_list_line(string $text): string
{
    $raw = ltrim($text);
    foreach (['•', '-', '*', 'N.'] as $marker) {
        if (str_starts_with($raw, $marker)) {
            return $text;
        }
    }
    if (preg_match('/^\d+\./u', $raw) === 1) {
        return $text;
    }
    return '• ' . $text;
}

function docx_node_text(DOMNode $node, string $ns, bool $root = false): string
{
    // An entity reference is not walked. Its replacement text is the expansion.
    if ($node instanceof DOMEntityReference) {
        return docx_predefined_entity($node->nodeName);
    }
    if ($node instanceof DOMElement && $node->namespaceURI === $ns) {
        if ($node->localName === 't') {
            return docx_text_run($node);
        }
        if ($node->localName === 'tab') {
            return ' ';
        }
        if ($node->localName === 'br' || $node->localName === 'cr') {
            return "\n";
        }
        if ($node->localName === 'p' && !$root) {
            return '';
        }
    }
    $text = '';
    foreach ($node->childNodes as $child) {
        $text .= docx_node_text($child, $ns);
    }
    return $text;
}

function docx_text_run(DOMElement $node): string
{
    $text = '';
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMText) {
            $text .= (string) $child->nodeValue;
            continue;
        }
        if ($child instanceof DOMEntityReference) {
            $text .= docx_predefined_entity($child->nodeName);
        }
    }
    return $text;
}

function docx_predefined_entity(string $name): string
{
    return match ($name) {
        'amp' => '&',
        'lt' => '<',
        'gt' => '>',
        'quot' => '"',
        'apos' => "'",
        default => '',
    };
}

function docx_strip_tags(string $documentXml): string
{
    $marked = preg_replace(
        ['/<\/w:p>/', '/<w:(?:br|cr)\b[^>]*\/>/', '/<w:tab\b[^>]*\/>/'],
        ["\n", "\n", ' '],
        $documentXml
    );
    if (!is_string($marked)) {
        $marked = $documentXml;
    }
    return html_entity_decode(strip_tags($marked), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** @return array{text:string, pages:int, linear:bool, readable:bool, valid_docx:bool} */
function extract_pdf(string $path, callable $runner): array
{
    $info = $runner(['pdfinfo', $path]);
    $layout = $runner(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);
    $infoCode = is_array($info) && is_int($info['code'] ?? null) ? $info['code'] : 1;
    $layoutCode = is_array($layout) && is_int($layout['code'] ?? null) ? $layout['code'] : 1;
    $infoOut = is_array($info) && is_string($info['stdout'] ?? null) ? $info['stdout'] : '';
    $layoutOut = is_array($layout) && is_string($layout['stdout'] ?? null) ? $layout['stdout'] : '';
    $text = $layoutCode === 0 ? $layoutOut : '';
    $pages = $infoCode === 0 ? pdfinfo_pages($infoOut) : 0;
    // A blank page is a form feed, which trim() does not strip.
    $readable = $infoCode === 0 && $layoutCode === 0 && trim($text, " \n\r\t\v\0\f") !== '';
    return [
        'text' => $text,
        'pages' => $pages,
        'linear' => $readable && !layout_has_column($text) && !layout_has_blank_page($text, $pages),
        'readable' => $readable,
        'valid_docx' => false,
    ];
}

function pdfinfo_pages(string $info): int
{
    if (preg_match('/^Pages:[ \t]*([0-9]+)/m', $info, $match) !== 1) {
        return 0;
    }
    return (int) $match[1];
}

function layout_has_blank_page(string $layout, int $pages): bool
{
    $segments = explode("\f", $layout);
    $last = array_key_last($segments);
    // pdftotext ends with a form feed. That empty tail is not another page.
    if ($last !== null && trim($segments[$last]) === '') {
        array_pop($segments);
    }
    $filled = 0;
    foreach ($segments as $segment) {
        if (trim($segment) === '') {
            return true;
        }
        $filled++;
    }
    return $pages > 0 && $filled < $pages;
}

function layout_has_column(string $layout): bool
{
    $lines = preg_split("/\r\n|\n|\r|\f/", $layout);
    if (!is_array($lines)) {
        return false;
    }
    $starts = [];
    foreach ($lines as $line) {
        $start = layout_gap_start($line);
        if ($start !== null) {
            $starts[] = $start;
        }
    }
    $count = count($starts);
    if ($count < 4) {
        return false;
    }
    sort($starts, SORT_NUMERIC);
    for ($i = 0; $i <= $count - 4; $i++) {
        if ($starts[$i + 3] - $starts[$i] <= 2) {
            return true;
        }
    }
    return false;
}

function layout_gap_start(string $line): ?int
{
    $length = strlen($line);
    $i = 0;
    $column = 0;
    while ($i < $length) {
        if ($line[$i] !== ' ') {
            $column++;
            $i += layout_utf8_width($line, $i);
            continue;
        }
        $start = $column;
        $spaces = 0;
        while ($i < $length && $line[$i] === ' ') {
            $spaces++;
            $column++;
            $i++;
        }
        if ($spaces >= 8 && $start > 0 && $i < $length) {
            return $start;
        }
    }
    return null;
}

function layout_utf8_width(string $text, int $i): int
{
    $byte = ord($text[$i]);
    $width = 1;
    if ($byte >= 0xF0) {
        $width = 4;
    } elseif ($byte >= 0xE0) {
        $width = 3;
    } elseif ($byte >= 0xC0) {
        $width = 2;
    }
    $remain = strlen($text) - $i;
    return $width < $remain ? $width : $remain;
}

function extract_command_on_path(string $name): bool
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

function extract_pdf_available(): bool
{
    return extract_command_on_path('pdfinfo') && extract_command_on_path('pdftotext');
}

/** @return array{code:int, stdout:string, stderr:string} */
function extract_command(array $argv): array
{
    $failed = ['code' => 127, 'stdout' => '', 'stderr' => ''];
    $errPath = tempnam(sys_get_temp_dir(), 'avaliar-err');
    if (!is_string($errPath)) {
        return $failed;
    }
    $env = getenv();
    if (!is_array($env)) {
        $env = [];
    }
    // LANG=C keeps pdfinfo's Pages: label in English. pdftotext gets -enc UTF-8.
    $env['LC_ALL'] = 'C';
    $env['LANG'] = 'C';
    $env['LANGUAGE'] = 'C';
    $proc = proc_open($argv, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', $errPath, 'w'],
    ], $pipes, null, $env);
    if (!is_resource($proc)) {
        if (is_file($errPath)) {
            unlink($errPath);
        }
        return $failed;
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    $stdout = '';
    $finished = null;
    $deadline = microtime(true) + 15;
    while (true) {
        $chunk = fread($pipes[1], 65536);
        if (is_string($chunk) && $chunk !== '') {
            $stdout .= $chunk;
        }
        $status = proc_get_status($proc);
        if (!is_array($status) || ($status['running'] ?? false) !== true) {
            $finished = is_array($status) ? $status : null;
            break;
        }
        if (microtime(true) >= $deadline) {
            $finished = extract_command_stop($proc);
            break;
        }
        if (!is_string($chunk) || $chunk === '') {
            usleep(20000);
        }
    }
    $rest = stream_get_contents($pipes[1]);
    if (is_string($rest)) {
        $stdout .= $rest;
    }
    fclose($pipes[1]);
    $closed = proc_close($proc);
    $stderr = file_get_contents($errPath);
    if (is_file($errPath)) {
        unlink($errPath);
    }
    return [
        'code' => extract_command_code($finished, $closed),
        'stdout' => $stdout,
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

/** @param resource $proc */
function extract_command_stop($proc): ?array
{
    $status = proc_get_status($proc);
    if (!is_array($status)) {
        return null;
    }
    if (($status['running'] ?? false) !== true) {
        return $status;
    }
    // 9 is SIGKILL. SIGTERM can be ignored and would leave the child running.
    proc_terminate($proc, 9);
    $pid = is_int($status['pid'] ?? null) ? $status['pid'] : 0;
    usleep(50000);
    $after = proc_get_status($proc);
    if (is_array($after) && ($after['running'] ?? false) === true && $pid > 0 && function_exists('posix_kill')) {
        posix_kill($pid, 9);
        usleep(50000);
        $again = proc_get_status($proc);
        if (is_array($again)) {
            return $again;
        }
    }
    return is_array($after) ? $after : $status;
}

function extract_command_code(?array $status, int $closed): int
{
    if (is_array($status) && ($status['running'] ?? false) !== true) {
        $exit = $status['exitcode'] ?? null;
        if (is_int($exit) && $exit >= 0) {
            return $exit;
        }
        if (($status['signaled'] ?? false) === true) {
            $sig = $status['termsig'] ?? 0;
            if (is_int($sig) && $sig > 0) {
                return 128 + $sig;
            }
        }
    }
    if ($closed >= 0) {
        return $closed;
    }
    return 1;
}
